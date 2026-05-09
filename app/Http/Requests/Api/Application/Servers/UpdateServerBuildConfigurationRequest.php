<?php

namespace Pterodactyl\Http\Requests\Api\Application\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;

class UpdateServerBuildConfigurationRequest extends ServerWriteRequest
{
    
    public function rules(): array
    {
        $rules = Server::getRulesForUpdate($this->parameter('server', Server::class));

        return [
            'allocation' => $rules['allocation_id'],
            'oom_disabled' => $rules['oom_disabled'],

            'limits' => 'sometimes|array',
            'limits.memory' => $this->requiredToOptional('memory', $rules['memory'], true),
            'limits.swap' => $this->requiredToOptional('swap', $rules['swap'], true),
            'limits.io' => $this->requiredToOptional('io', $rules['io'], true),
            'limits.cpu' => $this->requiredToOptional('cpu', $rules['cpu'], true),
            'limits.threads' => $this->requiredToOptional('threads', $rules['threads'], true),
            'limits.disk' => $this->requiredToOptional('disk', $rules['disk'], true),

            
            
            
            
            'memory' => $this->requiredToOptional('memory', $rules['memory']),
            'swap' => $this->requiredToOptional('swap', $rules['swap']),
            'io' => $this->requiredToOptional('io', $rules['io']),
            'cpu' => $this->requiredToOptional('cpu', $rules['cpu']),
            'threads' => $this->requiredToOptional('threads', $rules['threads']),
            'disk' => $this->requiredToOptional('disk', $rules['disk']),

            'add_allocations' => 'bail|array',
            'add_allocations.*' => 'integer',
            'remove_allocations' => 'bail|array',
            'remove_allocations.*' => 'integer',

            'feature_limits' => 'required|array',
            'feature_limits.databases' => $rules['database_limit'],
            'feature_limits.allocations' => $rules['allocation_limit'],
            'feature_limits.backups' => $rules['backup_limit'],
            'feature_limits.splitter_limit' => 'sometimes|integer|min:0',
            'feature_limits.reverse_proxy_limit' => 'sometimes|integer|min:0',
            'feature_limits.server_type_changer_allowed' => 'sometimes|boolean',
        ];
    }

    
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        $data['allocation_id'] = $data['allocation'];
        $data['database_limit'] = $data['feature_limits']['databases'] ?? null;
        $data['allocation_limit'] = $data['feature_limits']['allocations'] ?? null;
        $data['backup_limit'] = $data['feature_limits']['backups'] ?? null;
        $data['splits'] = $data['feature_limits']['splitter_limit'] ?? null;
        $data['reverse_proxy_limit'] = $data['feature_limits']['reverse_proxy_limit'] ?? null;
        $data['server_type_changer_allowed'] = $data['feature_limits']['server_type_changer_allowed'] ?? null;
        unset($data['allocation'], $data['feature_limits']);

        
        if (!empty($data['limits'])) {
            foreach ($data['limits'] as $key => $value) {
                $data[$key] = $value;
            }

            unset($data['limits']);
        }

        return $data;
    }

    
    public function attributes(): array
    {
        return [
            'add_allocations' => 'allocations to add',
            'remove_allocations' => 'allocations to remove',
            'add_allocations.*' => 'allocation to add',
            'remove_allocations.*' => 'allocation to remove',
            'feature_limits.databases' => 'Database Limit',
            'feature_limits.allocations' => 'Allocation Limit',
            'feature_limits.backups' => 'Backup Limit',
        ];
    }

    
    protected function requiredToOptional(string $field, array $rules, bool $limits = false): array
    {
        if (!in_array('required', $rules)) {
            return $rules;
        }

        return (new Collection($rules))
            ->filter(function ($value) {
                return $value !== 'required';
            })
            ->prepend($limits ? 'required_with:limits' : 'required_without:limits')
            ->toArray();
    }
}
