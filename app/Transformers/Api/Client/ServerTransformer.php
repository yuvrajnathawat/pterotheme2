<?php
namespace Pterodactyl\Transformers\Api\Client;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\Subuser;
use League\Fractal\Resource\Item;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Permission;
use Illuminate\Container\Container;
use Pterodactyl\Models\EggVariable;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\NullResource;
use Pterodactyl\Services\Servers\StartupCommandService;
class ServerTransformer extends BaseClientTransformer
{
    protected array $defaultIncludes = ['allocations', 'variables'];
    protected array $availableIncludes = ['egg', 'subusers'];
    public function getResourceName(): string
    {
        return Server::RESOURCE_NAME;
    }
    public function transform(Server $server): array
    {
        $service = Container::getInstance()->make(StartupCommandService::class);
        $user = $this->request->user();
        $masterserver = $server->masterserver ?: ($server->subSplit ? $server->subSplit->masterServer->uuid : null);
        
        // Get subdomain for primary allocation
        $primarySubdomain = ServerSubdomain::where('allocation_id', $server->allocation_id)->first();
        
        // Calculate properly based on prioritization
        $settingsRepository = Container::getInstance()->make(\Pterodactyl\Repositories\Eloquent\SettingsRepository::class);
        $raw = $settingsRepository->get('settings::app:addons:hyperv1', '{}');
        $addonSettings = json_decode($raw ?: '{}', true);
        $stcSettings = $addonSettings['addons']['server-type-changer'] ?? [];

        $isServerTypeChangerAllowed = false;
        $whitelistEnabled = $stcSettings['whitelist_enabled'] ?? false;

        if ($whitelistEnabled) {
            $isServerTypeChangerAllowed = (bool) $server->server_type_changer_allowed;
        } else {
            $rules = $stcSettings['type_changer_rules'] ?? [];
            $hasMatchingRule = false;
            foreach ($rules as $rule) {
                $matchNest = $rule['match_nest_id'] ?? null;
                $matchEgg = $rule['match_egg_id'] ?? null;
                if ($matchEgg && (int) $matchEgg === $server->egg_id) {
                    $hasMatchingRule = true; break;
                } elseif ($matchNest && (int) $matchNest === $server->nest_id) {
                    $hasMatchingRule = true; break;
                } elseif (empty($matchNest) && empty($matchEgg)) {
                    $hasMatchingRule = true; break;
                }
            }

            if (count($rules) > 0 && $hasMatchingRule) {
                $isServerTypeChangerAllowed = true;
            } else {
                $allowNest = $stcSettings['allow_nest_changes'] ?? false;
                $allowEgg = $stcSettings['allow_egg_changes'] ?? false;
                $isServerTypeChangerAllowed = $allowNest || $allowEgg;
            }
        }

        return [
            'server_owner' => $user->id === $server->owner_id,
            'identifier' => config('pterodactyl.features.new_server_identifiers', false)
                ? $server->identifier
                : $server->uuidShort,
            'server_identifier' => $server->identifier,
            '__deprecated_uuid_short' => $server->uuidShort,
            'internal_id' => $server->id,
            'uuid' => $server->uuid,
            'name' => $server->name,
            'node' => $server->node->name,
            'is_node_under_maintenance' => $server->node->isUnderMaintenance(),
            'sftp_details' => [
                'ip' => $server->node->sftp_alias ?? $server->node->fqdn,
                'port' => $server->node->sftp_port_alias ?? $server->node->daemonSFTP,
            ],
            'description' => $server->description,
            'limits' => [
                'memory' => $server->memory,
                'swap' => $server->swap,
                'disk' => $server->disk,
                'io' => $server->io,
                'cpu' => $server->cpu,
                'threads' => $server->threads,
                'oom_disabled' => $server->oom_disabled,
            ],
            'upload_size' => $server->node->upload_size,
            'invocation' => $service->handle($server, !$user->can(Permission::ACTION_STARTUP_READ, $server)),
            'docker_image' => $server->image,
            'egg_features' => $server->egg->inherit_features,
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
                'server_type_changer_allowed' => $isServerTypeChangerAllowed,
            ],
            'status' => $server->status,
            'is_suspended' => $server->isSuspended(),
            'is_installing' => !$server->isInstalled(),
            'is_transferring' => !is_null($server->transfer),
            'egg_id' => $server->egg_id,
            'egg_name' => $server->egg->name,
            'nest_id' => $server->nest_id,
            'nest_name' => $server->egg->nest->name,
            'exp_date' => $server->exp_date,
            'masterserver' => $masterserver,
            'primary_subdomain' => $primarySubdomain ? [
                'subdomain' => $primarySubdomain->subdomain,
                'domain' => $primarySubdomain->domain,
                'full_domain' => $primarySubdomain->subdomain . '.' . $primarySubdomain->domain,
                'game_type' => $primarySubdomain->game_type,
            ] : null,
        ];
    }
    public function includeAllocations(Server $server): Collection
    {
        $transformer = $this->makeTransformer(AllocationTransformer::class);
        $user = $this->request->user();
        if (!$user->can(Permission::ACTION_ALLOCATION_READ, $server)) {
            $primary = clone $server->allocation;
            $primary->notes = null;
            return $this->collection([$primary], $transformer, Allocation::RESOURCE_NAME);
        }
        return $this->collection($server->allocations, $transformer, Allocation::RESOURCE_NAME);
    }
    public function includeVariables(Server $server): Collection|NullResource
    {
        if (!$this->request->user()->can(Permission::ACTION_STARTUP_READ, $server)) {
            return $this->null();
        }
        return $this->collection(
            $server->variables->where('user_viewable', true),
            $this->makeTransformer(EggVariableTransformer::class),
            EggVariable::RESOURCE_NAME
        );
    }
    public function includeEgg(Server $server): Item
    {
        return $this->item($server->egg, $this->makeTransformer(EggTransformer::class), Egg::RESOURCE_NAME);
    }
    public function includeSubusers(Server $server): Collection|NullResource
    {
        if (!$this->request->user()->can(Permission::ACTION_USER_READ, $server)) {
            return $this->null();
        }
        return $this->collection($server->subusers, $this->makeTransformer(SubuserTransformer::class), Subuser::RESOURCE_NAME);
    }
}
