<?php
namespace Pterodactyl\Models;

use Illuminate\Notifications\Notifiable;
use Pterodactyl\Models\Traits\HasRealtimeIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Container\Container;
use Pterodactyl\Contracts\Models\Identifiable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
#[Attributes\Identifiable('node')]
class Node extends Model implements Identifiable
{
    use HasFactory;
    use Notifiable;
    use HasRealtimeIdentifier;
    public const RESOURCE_NAME = 'node';
    public const DAEMON_TOKEN_ID_LENGTH = 16;
    public const DAEMON_TOKEN_LENGTH = 64;
    protected $table = 'nodes';
    protected $hidden = ['daemon_token_id', 'daemon_token'];
    protected $casts = [
        'location_id' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'daemonListen' => 'integer',
        'daemonSFTP' => 'integer',
        'sftp_port_alias' => 'integer',
        'behind_proxy' => 'boolean',
        'public' => 'boolean',
        'maintenance_mode' => 'boolean',
        'server_limit' => 'integer',
        'sort' => 'integer',
    ];
    protected $fillable = [
        'public', 'name', 'location_id',
        'fqdn', 'sftp_alias', 'sftp_port_alias', 'scheme', 'behind_proxy',
        'memory', 'memory_overallocate', 'disk',
        'disk_overallocate', 'upload_size', 'daemonBase',
        'daemonSFTP', 'daemonListen',
        'description', 'maintenance_mode',
        'app_name',
        'server_limit',
    ];
    public static array $validationRules = [
        'name' => 'required|regex:/^([\w .-]{1,100})$/',
        'description' => 'string|nullable',
        'app_name' => 'string|nullable',
        'location_id' => 'required|exists:locations,id',
        'public' => 'boolean',
        'fqdn' => 'required|string',
        'sftp_alias' => 'nullable|string',
        'sftp_port_alias' => 'nullable|numeric|between:1,65535',
        'scheme' => 'required',
        'behind_proxy' => 'boolean',
        'memory' => 'required|numeric|min:1',
        'memory_overallocate' => 'required|numeric|min:-1',
        'disk' => 'required|numeric|min:1',
        'disk_overallocate' => 'required|numeric|min:-1',
        'daemonBase' => 'sometimes|required|regex:/^([\/][\d\w.\-\/]+)$/',
        'daemonSFTP' => 'required|numeric|between:1,65535',
        'daemonListen' => 'required|numeric|between:1,65535',
        'maintenance_mode' => 'boolean',
        'upload_size' => 'int|between:1,1024',
        'server_limit' => 'nullable|integer|min:1',
    ];
    protected $attributes = [
        'public' => true,
        'behind_proxy' => false,
        'memory_overallocate' => 0,
        'disk_overallocate' => 0,
        'daemonBase' => '/var/lib/pterodactyl/volumes',
        'daemonSFTP' => 2022,
        'daemonListen' => 8080,
        'maintenance_mode' => false,
    ];
    public function getConnectionAddress(): string
    {
        return sprintf('%s://%s:%s', $this->scheme, $this->fqdn, $this->daemonListen);
    }
    public function getConfiguration(): array
    {
        return [
            'debug' => false,
            'app_name' => $this->app_name,
            'uuid' => $this->uuid,
            'token_id' => $this->daemon_token_id,
            'token' => $this->getDecryptedKey(),
            'api' => [
                'host' => '0.0.0.0',
                'port' => $this->daemonListen,
                'ssl' => [
                    'enabled' => (!$this->behind_proxy && $this->scheme === 'https'),
                    'cert' => '/etc/letsencrypt/live/' . Str::lower($this->fqdn) . '/fullchain.pem',
                    'key' => '/etc/letsencrypt/live/' . Str::lower($this->fqdn) . '/privkey.pem',
                ],
                'upload_limit' => $this->upload_size,
            ],
            'system' => [
                'app_name' => $this->app_name,
                'data' => $this->daemonBase,
                'sftp' => [
                    'bind_port' => $this->daemonSFTP,
                ],
            ],
            'allowed_mounts' => $this->mounts->pluck('source')->toArray(),
            'remote' => route('index'),
        ];
    }
    public function getYamlConfiguration(): string
    {
        return Yaml::dump($this->getConfiguration(), 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
    public function getJsonConfiguration(bool $pretty = false): string
    {
        return json_encode($this->getConfiguration(), $pretty ? JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT : JSON_UNESCAPED_SLASHES);
    }
    public function getDecryptedKey(): string
    {
        try {
            return (string) Container::getInstance()->make(Encrypter::class)->decrypt(
                $this->daemon_token
            );
        } catch (DecryptException) {
            return '';
        }
    }
    public function isUnderMaintenance(): bool
    {
        return $this->maintenance_mode;
    }
    public function mounts(): HasManyThrough
    {
        return $this->hasManyThrough(Mount::class, MountNode::class, 'node_id', 'id', 'id', 'mount_id');
    }
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
    public function incidents(): MorphMany
    {
        return $this->morphMany(StatusIncident::class, 'subject');
    }
    /**
     * Returns whether a node has enough free resources to host a server
     * requiring the provided memory/disk values. This method is used by
     * a variety of features such as the billing addon and automatic
     * deployments.
     */
    public function isViable(int $memory, int $disk): bool
    {
        // If a hard server limit has been configured we treat the node as
        // non-viable once the limit has been reached.
        if (!is_null($this->server_limit) && $this->servers()->count() >= $this->server_limit) {
            return false;
        }

        $memoryLimit = $this->memory * (1 + ($this->memory_overallocate / 100));
        $diskLimit = $this->disk * (1 + ($this->disk_overallocate / 100));

        return ($this->sum_memory + $memory) <= $memoryLimit && ($this->sum_disk + $disk) <= $diskLimit;
    }

    /**
     * Determine whether the node has space for an additional number of
     * servers (defaults to just one). Returns true when no limit has been
     * set or the current count plus the requested amount is less than or
     * equal to the limit.
     */
    public function hasServerCapacity(int $additional = 1): bool
    {
        if (is_null($this->server_limit)) {
            return true;
        }

        $current = $this->servers()->count();
        return ($current + $additional) <= $this->server_limit;
    }
}
