<?php

namespace Pterodactyl\Console\Commands\Rolexdev;

use Illuminate\Console\Command;
use Pterodactyl\Models\Egg;

class FixThemeSetupCommand extends Command
{
    protected $signature = 'hyperv1:fix-setup';
    protected $description = 'Fix all eggs features and theme setup after HyperV1 installation';

    public function handle(): void
    {
        $this->info('Fixing egg features for all eggs...');

        $features = [
            'eula',
            'java_version',
            'pid_limit',
            'minecraft-version',
            'minecraft-version-changer',
            'minecraft-player-manager',
            'minecraft-mod-installer',
            'minecraft-plugin-installer',
            'minecraft-modpack-installer',
            'minecraft-configuration',
            'minecraft-bedrock-version-changer',
            'minecraft-bedrock-addon-installer',
            'minecraft-bedrock-map-manager',
            'minecraft-bedrock-pack-installer',
            'minecraft-bedrock-script-installer',
            'minecraft-world-manager',
            'minecraft-icon-changer',
            'minecraft-votifier-tester',
            'minecraft-motd-changer',
            'minecraft-player-counter',
        ];

        $eggs = Egg::all();
        $count = 0;
        foreach ($eggs as $egg) {
            $existing = is_array($egg->features) ? $egg->features : array_values((array) $egg->features);
            $merged = array_values(array_unique(array_merge($existing, $features)));
            $egg->features = $merged;
            $egg->save();
            $count++;
        }

        $this->info("Updated {$count} eggs.");

        // Update addon settings — set all minecraft addons with all egg/nest IDs allowed
        $this->info('Updating addon settings with all egg/nest permissions...');
        try {
            $settingsRepo = app(\Pterodactyl\Repositories\Eloquent\SettingsRepository::class);
            $raw = $settingsRepo->get('settings::app:addons:hyperv1', '{}');
            $data = json_decode($raw ?: '{}', true) ?: [];

            $eggIds  = Egg::pluck('id')->toArray();
            $nestIds = \Pterodactyl\Models\Nest::pluck('id')->toArray();

            $minecraftAddons = [
                'minecraft-player-manager'         => ['name' => 'Player Manager',              'category' => 'Minecraft'],
                'minecraft-version'                => ['name' => 'Minecraft Version',           'category' => 'Minecraft'],
                'minecraft-version-changer'        => ['name' => 'Java Version Changer',        'category' => 'Minecraft'],
                'minecraft-mod-installer'          => ['name' => 'Java Mod Installer',          'category' => 'Minecraft'],
                'minecraft-plugin-installer'       => ['name' => 'Java Plugin Manager',         'category' => 'Minecraft'],
                'minecraft-modpack-installer'      => ['name' => 'Java ModPack Installer',      'category' => 'Minecraft'],
                'minecraft-configuration'          => ['name' => 'Config Editor',               'category' => 'Minecraft'],
                'minecraft-bedrock-version-changer'=> ['name' => 'Bedrock Version Changer',     'category' => 'Minecraft'],
                'minecraft-bedrock-addon-installer'=> ['name' => 'Bedrock Addon Installer',     'category' => 'Minecraft'],
                'minecraft-bedrock-map-manager'    => ['name' => 'Bedrock Map Manager',         'category' => 'Minecraft'],
                'minecraft-bedrock-pack-installer' => ['name' => 'Bedrock Pack Installer',      'category' => 'Minecraft'],
                'minecraft-bedrock-script-installer'=> ['name' => 'Bedrock Script Installer',   'category' => 'Minecraft'],
                'minecraft-world-manager'          => ['name' => 'Java World Manager',          'category' => 'Minecraft'],
                'minecraft-icon-changer'           => ['name' => 'Icon Changer',                'category' => 'Minecraft'],
                'minecraft-votifier-tester'        => ['name' => 'Votifier Tester',             'category' => 'Minecraft'],
                'minecraft-motd-changer'           => ['name' => 'MOTD Changer',                'category' => 'Minecraft'],
                'minecraft-player-counter'         => ['name' => 'Player Counter',              'category' => 'Minecraft'],
            ];

            foreach ($minecraftAddons as $addon => $meta) {
                $existing = $data['addons'][$addon] ?? [];
                $data['addons'][$addon] = array_merge($existing, [
                    'enabled'        => true,
                    'name'           => $meta['name'],
                    'category'       => $meta['category'],
                    'allowed_eggs'   => $eggIds,
                    'allowed_nests'  => $nestIds,
                    'blocked_eggs'   => [],
                    'blocked_nests'  => [],
                ]);
            }

            $settingsRepo->set('settings::app:addons:hyperv1', json_encode($data));
            \Illuminate\Support\Facades\Cache::flush();
            $this->info('Addon settings updated with all egg/nest permissions.');
        } catch (\Throwable $e) {
            $this->warn('Could not update addon settings: ' . $e->getMessage());
        }

        // Ensure RolexDev directory and DiscordBotService stub exist
        $dir = app_path('Services/RolexDev');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stub = $dir . '/DiscordBotService.php';
        if (!file_exists($stub) || filesize($stub) < 100) {
            file_put_contents($stub, <<<'PHP'
<?php

namespace Pterodactyl\Services\RolexDev;

use Pterodactyl\Models\User;

class DiscordBotService
{
    public function syncUserRole(User $user): void {}
    public function syncAllRoles(): void {}
    public function sendMessage(string $channel, string $message): void {}
    public function __call(string $name, array $args): mixed { return null; }
}
PHP);
            $this->info('Created DiscordBotService stub.');
        }

        $this->info('HyperV1 setup fix complete.');
    }
}
