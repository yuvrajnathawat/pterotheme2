<?php
namespace Pterodactyl\Http\Controllers\Auth;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Pterodactyl\Models\UserIntegration;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Str;
use Pterodactyl\Helpers\UserAgentHelper;
use Pterodactyl\Helpers\IpDetailsHelper;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cookie;

abstract class AbstractLoginController extends Controller
{
    use AuthenticatesUsers;
    protected AuthManager $auth;

    /**
     * Lockout time for failed login requests.
     */
    protected int $lockoutTime;

    /**
     * After how many attempts should logins be throttled and locked.
     */
    protected int $maxLoginAttempts;

    /**
     * Where to redirect users after login / registration.
     */
    protected string $redirectTo = '/';

    /**
     * LoginController constructor.
     */
    public function __construct()
    {
        $this->lockoutTime = config('auth.lockout.time');
        $this->maxLoginAttempts = config('auth.lockout.attempts');
        $this->auth = Container::getInstance()->make(AuthManager::class);
    }

    /**
     * Get the failed login response instance.
     *
     * @return never-return
     *
     * @throws DisplayException
     */
    protected function sendFailedLoginResponse(Request $request, ?Authenticatable $user = null, ?string $message = null)
    {
        $this->incrementLoginAttempts($request);
        $this->fireFailedLoginEvent($user, [
            $this->getField($request->input('user')) => $request->input('user'),
        ]);

        if ($request->route()->named('auth.login-checkpoint')) {
            throw new DisplayException($message ?? trans('auth.two_factor.checkpoint_failed'));
        }

        throw new DisplayException(trans('auth.failed'));
    }

    /**
     * Send the response after the user was authenticated.
     */
    protected function sendLoginResponse(User $user, Request $request): JsonResponse
    {
        $this->performLogin($user, $request);

        return new JsonResponse([
            'data' => [
                'complete' => true,
                'intended' => $this->redirectPath(),
                'user' => $user->toVueObject(),
            ],
        ]);
    }

    /**
     * Detailed login logic for the user, including session tracking and
     * custom cookie generation for the theme.
     */
    protected function performLogin(User $user, Request $request): void
    {
        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();
        $this->clearLoginAttempts($request);
        $this->auth->guard()->login($user, true);

        $loginToken = Str::random(64);
        $sessionId = $request->session()->getId();

        // HIGH-03: Use only $request->ip() which respects trusted proxy config
        // instead of iterating $request->ips() which can be spoofed via X-Forwarded-For
        $ip = $request->ip();

        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        // HIGH-02: Truncate User-Agent to prevent oversized DB writes
        $userAgent = mb_substr((string) $request->header('User-Agent', ''), 0, 512);
        require_once app_path('Helpers/ActivityHelpers.php');

        $uaData = UserAgentHelper::parse($userAgent);

        // HIGH-08: Wrap geolocation lookup in try-catch, don't let it block login
        $ipDetails = ['is_vpn' => false, 'city' => null, 'state' => null, 'country' => null];
        try {
            $ipDetails = IpDetailsHelper::getDetails($ip);
        } catch (\Throwable $e) {
            // Non-fatal: log and continue with defaults
        }

        DB::table('user_active_sessions')
            ->where('user_id', $user->id)
            ->where('user_agent', $userAgent)
            ->where('ip_address', $ip)
            ->delete();

        $pendingSso = $request->session()->pull('sso_pending_link');
        if ($pendingSso) {
            UserIntegration::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $pendingSso['provider'],
                    'provider_id' => $pendingSso['provider_id']
                ],
                [
                    'provider_email' => $pendingSso['provider_email'],
                    'provider_name' => $pendingSso['provider_name'],
                    'provider_avatar' => $pendingSso['provider_avatar'],
                    'access_token' => $pendingSso['access_token'],
                    'refresh_token' => $pendingSso['refresh_token'],
                ]
            );
        }

        DB::table('user_active_sessions')->insert([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'login_token' => $loginToken,
            'device_type' => $uaData['deviceType'],
            'platform' => $uaData['platform'],
            'browser' => $uaData['browser'],
            'is_vpn' => $ipDetails['is_vpn'],
            'city' => $ipDetails['city'],
            'state' => $ipDetails['state'],
            'country' => $ipDetails['country'],
            'is_revoked' => false,
            'last_active_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
        ]);

        // HIGH-04: Reduced cookie TTL from ~5 years (2628000 min) to 30 days (43200 min)
        Cookie::queue('hyper_session_token', $loginToken, 43200);
        Event::dispatch(new DirectLogin($user, true));
    }

    /**
     * Determine if the user is logging in using an email or username.
     */
    protected function getField(?string $input = null): string
    {
        return (filter_var($input, FILTER_VALIDATE_EMAIL)) ? 'email' : 'username';
    }

    /**
     * Fire a failed login event.
     */
    protected function fireFailedLoginEvent(?Authenticatable $user = null, array $credentials = [])
    {
        Event::dispatch(new Failed('auth', $user, $credentials));
    }
}
