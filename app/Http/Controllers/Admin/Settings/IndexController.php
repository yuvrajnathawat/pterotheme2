<?php
namespace Pterodactyl\Http\Controllers\Admin\Settings;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\BaseSettingsFormRequest;
class IndexController extends Controller
{
    use AvailableLanguages;
    /**
     * IndexController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private SoftwareVersionService $versionService,
        private ViewFactory $view
    ) {
    }

    /**
     * Render the UI for basic Panel settings.
     *
     * We intentionally read every displayed value directly from the repository
     * rather than from config() so that the form shows fresh DB values even
     * when the in-memory config on this Octane worker is still stale from
     * before the last save.
     */
    public function index(): View
    {
        return $this->view->make('admin.settings.index', [
            'version'        => $this->versionService,
            'languages'      => $this->getAvailableLanguages(true),
            'adminTheme'     => $this->settings->get('settings::app:admin_theme', 'default'),
            'appName'        => $this->settings->get('settings::app:name', config('app.name')),
            'appLocale'      => $this->settings->get('settings::app:locale', config('app.locale')),
            'appTheme'       => $this->settings->get('settings::app:theme', config('app.theme', 'default')),
            'twoFaRequired'  => (int) $this->settings->get('settings::pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required', 0)),
        ]);
    }

    /**
     * Handle settings update.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(BaseSettingsFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');
        $this->kernel->call('config:clear');
        $this->alert->success('Panel settings have been updated successfully and the queue worker was restarted to apply these changes.')->flash();

        return redirect()->route('admin.settings');
    }
}
