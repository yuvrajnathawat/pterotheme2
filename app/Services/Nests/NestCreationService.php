<?php

namespace Pterodactyl\Services\Nests;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Nest;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;

class NestCreationService
{
    
    /**
     * NestCreationService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private NestRepositoryInterface $repository,
        private ConfigRepository $config
    ) {
    }

    
    /**
     * Create a new nest on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(array $data, ?string $author = null): Nest
    {
        return $this->repository->create([
            'uuid' => Uuid::uuid4()->toString(),
            'author' => $author ?? $this->config->get('pterodactyl.service.author'),
            'name' => array_get($data, 'name'),
            'description' => array_get($data, 'description'),
        ], true, true);
    }
}
