<?php
namespace Pterodactyl\Http\ViewComposers;

use Throwable;
use Illuminate\View\View;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
class AssetComposer
{
    use \Pterodactyl\Traits\Helpers\ThemeLanguages;
    /**
     * AssetComposer constructor.
     */
    public function __construct(private AssetHashService $assetHashService)
    {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $locale = config('app.locale') ?? 'en';
        try {
            $settingsRepository = app(SettingsRepository::class);
            $addonConfig = json_decode($settingsRepository->get('settings::app:addons:hyperv1', '{}'), true);
            $locale = $addonConfig['addons']['LanguageTranslations']['defaultLanguage'] ?? $locale;
        } catch (Throwable $e) {
        }
        $view->with('asset', $this->assetHashService);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'locale' => $locale,
            'theme' => config('app.theme') ?? 'default',
            'languages' => $this->getLanguagesSafe(),
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
        ]);
    }
    private function getLanguagesSafe(): array
    {
        try {
            $langs = $this->getThemeLanguages(true);
            return is_array($langs) && !empty($langs) ? $langs : ['en' => 'English'];
        } catch (Throwable) {
            return ['en' => 'English'];
        }
    }
}
