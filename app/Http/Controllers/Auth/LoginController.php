<?php
namespace Pterodactyl\Http\Controllers\Auth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
class LoginController extends AbstractLoginController
{
    /**
     * Handle all incoming requests for the authentication routes and render the
     * base authentication view component. React will take over at this point and
     * turn the login area into an SPA.
     */
    public function index(Request $request): View
    {
        $ssoData = $request->session()->get('sso_pending_link');
        return view('templates/auth.core', [
            'ssoData' => $ssoData ? json_encode($ssoData) : null
        ]);
    }

    /**
     * Handle a login request to the application.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        try {
            $username = $request->input('user');
            $field = $this->getField($username);

            if ($field === 'username') {
                /** @var User $user */
                $user = User::query()->whereRaw('LOWER(username) = ?', [strtolower($username)])->firstOrFail();
            } else {
                /** @var User $user */
                $user = User::query()->where($field, $username)->firstOrFail();
            }
        } catch (ModelNotFoundException) {
            $this->sendFailedLoginResponse($request);
        }

        // Ensure that the account is using a valid username and password before trying to
        // continue. Previously this was handled in the 2FA checkpoint, however that has
        // a flaw in which you can discover if an account exists simply by seeing if you
        // can proceed to the next step in the login process.
        if (!password_verify($request->input('password'), $user->password)) {
            $this->sendFailedLoginResponse($request, $user);
        }

        // Prevent banned or suspended users from logging in.
        if ($user->is_banned) {
            // MED-04: Don't expose ban reason to potential attackers
            throw ValidationException::withMessages(['user' => 'This account has been banned. Please contact support for more information.']);
        }

        if ($user->suspended_until) {
            $now = CarbonImmutable::now();
            if ($now->lessThan($user->suspended_until)) {
                // MED-04: Don't expose suspension reason or exact time details
                throw ValidationException::withMessages(['user' => 'This account is temporarily suspended. Please contact support for more information.']);
            }

            // Clear expired suspension automatically.
            $user->forceFill(['suspended_until' => null, 'suspension_reason' => null])->save();
        }

        if (!$user->use_totp) {
            return $this->sendLoginResponse($user, $request);
        }

        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => $token = Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        return new JsonResponse([
            'data' => [
                'complete' => false,
                'confirmation_token' => $token,
            ],
        ]);
    }

    /**
     * Check if a user exists by email or username.
     */
    public function checkUser(Request $request): JsonResponse
    {
        $request->validate([
            'user' => 'required|string',
        ]);

        $username = $request->input('user');
        $field = $this->getField($username);

        try {
            if ($field === 'username') {
                $user = User::query()->whereRaw('LOWER(username) = ?', [strtolower($username)])->firstOrFail();
            } else {
                $user = User::query()->where($field, $username)->firstOrFail();
            }
            return new JsonResponse(['exists' => true]);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['exists' => false]);
        }
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        if ($request->session()->has('impersonator_id')) {
            // When using Axios (JSON), tell the frontend to navigate to the stop-impersonating GET route.
            // This ensures the browser navigation properly delivers Set-Cookie headers.
            return $request->wantsJson()
                ? new JsonResponse(['redirect' => route('auth.stop-impersonating')], 200)
                : redirect()->route('auth.stop-impersonating');
        }

        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
    }

    /**
     * Stop impersonating and restore the admin session.
     * This must be a GET (browser navigation) so cookies are properly set via Set-Cookie headers.
     */
    public function stopImpersonating(Request $request)
    {
        if (!$request->session()->has('impersonator_id')) {
            return redirect('/');
        }

        $impersonatorId = $request->session()->get('impersonator_id');
        $impersonatorToken = $request->session()->get('impersonator_token');

        // Delete the impersonated user's temp session row
        $currentToken = $request->cookie('hyper_session_token');
        if ($currentToken) {
            DB::table('user_active_sessions')
                ->where('user_id', Auth::id())
                ->where('login_token', $currentToken)
                ->delete();
        }

        $request->session()->forget(['impersonator_id', 'impersonator_token']);

        // Bypass Auth::login() entirely to prevent session regeneration by Fortify.
        // We simply update the session auth identifier directly and wipe the password hash.
        $request->session()->put($this->auth->guard()->getName(), $impersonatorId);
        $request->session()->forget('password_hash_' . Auth::getDefaultDriver());
        $this->auth->guard()->setUser(User::find($impersonatorId));

        // Restore the admin's original hyper_session_token cookie
        if ($impersonatorToken) {
            Cookie::queue(
                Cookie::make('hyper_session_token', $impersonatorToken, 2628000, '/', null, true, true, false, 'lax')
            );
            // Touch the admin's session row so middleware accepts it instantly
            DB::table('user_active_sessions')
                ->where('user_id', $impersonatorId)
                ->where('login_token', $impersonatorToken)
                ->update(['last_active_at' => CarbonImmutable::now()]);
        }

        return redirect()->route('admin.users');
    }
}
