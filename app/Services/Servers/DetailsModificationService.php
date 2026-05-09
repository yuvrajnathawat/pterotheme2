<?php
namespace Pterodactyl\Services\Servers;

use Pterodactyl\Traits\Services\ReturnsUpdatedModels;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
class DetailsModificationService
{
    use ReturnsUpdatedModels;
    /**
     * DetailsModificationService constructor.
     */
    public function __construct(private ConnectionInterface $connection)
    {
    }
    /**
     * Update the details for a server.
     *
     * @throws \Throwable
     */
    public function handle(Server $server, array $data): Server
    {
        return $this->connection->transaction(function () use ($data, $server) {
            $owner = $server->owner_id;
            $server->forceFill([
                'external_id' => Arr::get($data, 'external_id'),
                'owner_id' => Arr::get($data, 'owner_id'),
                'name' => Arr::get($data, 'name'),
                'description' => Arr::get($data, 'description') ?? '',
                'exp_date' => Arr::get($data, 'exp_date') ?: null,
                'product_id' => Arr::get($data, 'product_id'),
            ])->saveOrFail();
            if ($server->owner_id !== $owner) {
                if ($oldOwner = User::find($owner)) {
                    RevokeSftpAccessJob::dispatch($oldOwner->uuid, $server);
                }
            }
            return $server;
        });
    }
}
