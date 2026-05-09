<?php
namespace Pterodactyl\Http\Controllers\Admin;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Pterodactyl\Models\UserLoginHistory;
use Exception;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Admin\UserFormRequest;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Helpers\UserAgentHelper;
use Pterodactyl\Http\Requests\Admin\NewUserFormRequest;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;
class UserController extends Controller
{
    use AvailableLanguages;
    /**
     * UserController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected UserCreationService $creationService,
        protected UserDeletionService $deletionService,
        protected Translator $translator,
        protected UserUpdateService $updateService,
        protected SuspensionService $suspensionService,
        protected UserRepositoryInterface $repository,

        protected SettingsRepository $settingsRepository
    ) {
    }

    /**
     * Display user index page.
     */
    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()->select('users.*')
                ->selectRaw('COUNT(DISTINCT(subusers.id)) as subuser_of_count')
                ->selectRaw('COUNT(DISTINCT(servers.id)) as servers_count')
                ->addSelect(['last_login_at' => DB::table('user_active_sessions')->select('last_active_at')->whereColumn('user_id', 'users.id')->orderBy('last_active_at', 'desc')->take(1)])
                ->addSelect(['last_login_ip' => UserLoginHistory::select('ip_address')->whereColumn('user_id', 'users.id')->latest()->take(1)])
                ->leftJoin('subusers', 'subusers.user_id', '=', 'users.id')
                ->leftJoin('servers', 'servers.owner_id', '=', 'users.id')
                ->groupBy('users.id')
                // ensure admins appear first
                ->orderByDesc('root_admin')
        )
            ->allowedFilters(['username', 'email', 'uuid'])
            ->allowedSorts(['id', 'uuid'])
            ->paginate(50);

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Display new user page.
     */
    public function create(): View
    {
        return view('admin.users.new', [
            'languages' => $this->getAvailableLanguages(true),
        ]);
    }

    /**
     * Display user view page.
     */
    public function view(User $user): View
    {
        $activeSessions = DB::table('user_active_sessions')
            ->where('user_id', $user->id)
            ->where('is_revoked', false)
            ->orderBy('last_active_at', 'desc')
            ->get();
        $loginHistory = DB::table('user_login_history')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        $settingsRaw = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
        $settings = json_decode($settingsRaw, true);
        $currency = $settings['addons']['billing']['currency_symbol'] ?? '$';
        
        $loginAsUserEnabled = $settings['addons']['login-as-user']['enabled'] ?? false;

        return view('admin.users.view', [
            'user' => $user,
            'languages' => $this->getAvailableLanguages(true),
            'activeSessions' => $activeSessions,
            'loginHistory' => $loginHistory,
            'currency' => $currency,
            'loginAsUserEnabled' => $loginAsUserEnabled,
        ]);
    }

    /**
     * Delete a user from the system.
     *
     * @throws \Exception
     * @throws DisplayException
     */
    public function delete(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            throw new DisplayException($this->translator->get('admin/user.exceptions.user_has_servers'));
        }

        $this->deletionService->handle($user);

        return redirect()->route('admin.users');
    }

    /**
     * Create a user.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function store(NewUserFormRequest $request): RedirectResponse
    {
        $data = $request->normalize();
        if (!$request->user()->root_admin) {
            unset($data['root_admin']);
        }

        $user = $this->creationService->handle($data);
        $this->alert->success($this->translator->get('admin/user.notices.account_created'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Update a user on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        if ($user->root_admin && !$request->user()->root_admin) {
            throw new DisplayException($this->translator->get('admin/user.exceptions.user_has_servers'));
        }

        $wasSuspended = $user->isSuspended();

        $data = $request->normalize();
        if (!$request->user()->root_admin) {
            unset($data['root_admin']);
        }

        if (array_key_exists('suspended_until', $data) && $data['suspended_until'] === '') {
            $data['suspended_until'] = null;
        }

        $user = $this->updateService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($user, $data);

        $isSuspended = $user->isSuspended();

        // If the account's suspension state has changed, log the user out and update their servers.
        if ($wasSuspended !== $isSuspended) {
            // Log the user out of any active sessions.
            $this->revokeAllSessions($request, $user);

            // Suspend servers that are currently active (if account is being suspended) and
            // only unsuspend those that were suspended because the account was suspended.
            foreach ($user->servers as $server) {
                try {
                    if ($isSuspended) {
                        if (!$server->isSuspended()) {
                            $this->suspensionService->toggle($server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_SUSPEND);
                            $server->update(['suspended_by_account' => true]);
                        }
                    } else {
                        if ($server->suspended_by_account) {
                            $this->suspensionService->toggle($server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_UNSUSPEND);
                            $server->update(['suspended_by_account' => false]);
                        }
                    }
                } catch (Exception $e) {
                    // Swallow exceptions to avoid breaking the user update flow.
                }
            }
        }

        $this->alert->success(trans('admin/user.notices.account_updated'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Get a JSON response of users on the system.
     */
    public function json(Request $request): Model|Collection
    {
        $users = QueryBuilder::for(User::query())->allowedFilters(['email'])->paginate(25);
        if ($request->query('user_id')) {
            $user = User::query()->findOrFail($request->input('user_id'));
            $user->md5 = md5(strtolower($user->email));

            return $user;
        }

        return $users->map(function ($item) {
            $item->md5 = md5(strtolower($item->email));

            return $item;
        });
    }
    public function revokeSession(Request $request, User $user, string $session): RedirectResponse
    {
        DB::table('user_active_sessions')
            ->where('user_id', $user->id)
            ->where('session_id', $session)
            ->delete();
        try {
            $driver = config('session.driver');
            Session::getHandler()->destroy($session);
            if ($driver === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('id', $session)
                    ->delete();
            }
        } catch (Exception $e) {
        }
        $this->alert->success('Target session has been marked for revocation.')->flash();
        return redirect()->route('admin.users.view', $user->id);
    }

    public function revokeAllSessions(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($user) {
            $sessions = DB::table('user_active_sessions')
                ->where('user_id', $user->id)
                ->get();

            DB::table('user_active_sessions')
                ->where('user_id', $user->id)
                ->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->whereIn('id', $sessions->pluck('session_id'))
                    ->delete();
            }
        });

        $this->alert->success('All active sessions for this user have been revoked.')->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $settingsRaw = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
        $settings = json_decode($settingsRaw, true);
        $enabled = $settings['addons']['login-as-user']['enabled'] ?? false;

        if (!$enabled) {
            throw new DisplayException('The Login As User addon is not enabled.');
        }

        if ($user->root_admin) {
            throw new DisplayException('You cannot impersonate another administrator.');
        }

        // Save admin's identity and original session token
        $adminToken = $request->cookie('hyper_session_token');
        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->put('impersonator_token', $adminToken);
        
        // Switch to the impersonated user (bypass Auth::login() to prevent session regeneration by Fortify)
        $request->session()->put(Auth::guard()->getName(), $user->id);
        $request->session()->forget('password_hash_' . Auth::getDefaultDriver());
        Auth::guard()->setUser($user);

        // Generate a brand-new session token for the impersonated user
        $newToken = bin2hex(random_bytes(32));
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent') ?? 'Unknown';
        require_once app_path('Helpers/ActivityHelpers.php');
        $uaData = UserAgentHelper::parse($userAgent);

        DB::table('user_active_sessions')->insert([
            'user_id' => $user->id,
            // Use a pseudo-session ID here because the real session ID is still the admin's (which is already in the DB and must be uniquely constrained)
            'session_id' => 'imp_' . bin2hex(random_bytes(16)),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'login_token' => $newToken,
            'device_type' => $uaData['deviceType'] ?? 'Unknown',
            'platform' => $uaData['platform'] ?? 'Unknown',
            'browser' => $uaData['browser'] ?? 'Unknown',
            'is_vpn' => false,
            'city' => 'Unknown',
            'state' => 'Unknown',
            'country' => 'Unknown',
            'is_revoked' => false,
            'last_active_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
        ]);

        // Issue new cookie for the impersonated user (admin's original is untouched)
        Cookie::queue('hyper_session_token', $newToken, 2628000);

        return redirect('/');
    }
}
