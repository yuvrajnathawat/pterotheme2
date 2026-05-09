<?php

namespace Pterodactyl\Services\Nodes;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;

class NodeCreationService
{
    
    /**
     * NodeCreationService constructor.
     */
    public function __construct(
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private NodeRepositoryInterface $repository
    ) {
    }

    
    /**
     * Create a new node on the panel.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(array $data): Node
    {
        $data['uuid'] = Uuid::uuid4()->toString();
        $data['daemon_token'] = app(Encrypter::class)->encrypt(Str::random(Node::DAEMON_TOKEN_LENGTH));
        $data['daemon_token_id'] = Str::random(Node::DAEMON_TOKEN_ID_LENGTH);

        // Ensure new nodes default to the end of the list when ordering is enabled.
        $data['sort'] = (int) Node::max('sort') + 1;

        return $this->repository->create($data, true, true);
    }
}
