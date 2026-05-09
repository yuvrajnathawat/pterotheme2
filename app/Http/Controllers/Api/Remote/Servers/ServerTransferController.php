<?php

namespace Pterodactyl\Http\Controllers\Api\Remote\Servers;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Services\SubdomainManager\CloudflareService;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class ServerTransferController extends Controller
{
    
    public function __construct(
        private ConnectionInterface $connection,
        private ServerRepository $repository,
        private DaemonServerRepository $daemonServerRepository,
        private CloudflareService $cloudflareService
    ) {
    }

    
    public function failure(Request $request, string $uuid): JsonResponse
    {
        $server = $this->repository->getByUuid($uuid);
        $transfer = $server->transfer;
        if (is_null($transfer)) {
            throw new ConflictHttpException('Server is not being transferred.');
        }

        // Either node can tell the panel that the transfer has failed. Only the new node
        // can tell the panel that it was successful.
        // Use the authenticated node from the request (not $server->node which is always the old node).
        $node = $request->attributes->get('node');
        if (! $node->is($transfer->newNode) && ! $node->is($transfer->oldNode)) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        return $this->processFailedTransfer($transfer);
    }

    
    public function success(Request $request, string $uuid): JsonResponse
    {
        $server = $this->repository->getByUuid($uuid);
        $transfer = $server->transfer;
        if (is_null($transfer)) {
            throw new ConflictHttpException('Server is not being transferred.');
        }

        // Only the new node communicates a successful state to the panel, so we should
        // not allow the old node to hit this endpoint.
        // IMPORTANT: Use the authenticated node from the request attributes, NOT $server->node.
        // At this point, $server->node_id still points to the OLD node (it hasn't been updated yet),
        // so comparing $server->node against $transfer->newNode would always return false,
        // causing a 403 and leaving the transfer stuck at 100%.
        $node = $request->attributes->get('node');
        if (! $node->is($transfer->newNode)) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        $server = $this->connection->transaction(function () use ($server, $transfer) {
            $allocations = array_merge([$transfer->old_allocation], $transfer->old_additional_allocations);

            // Remove the old allocations for the server and re-assign the server to the new
            // primary allocation and node.
            Allocation::query()->whereIn('id', $allocations)->update(['server_id' => null]);
            $server->update([
                'allocation_id' => $transfer->new_allocation,
                'node_id' => $transfer->new_node,
            ]);

            $server = $server->fresh();
            $server->transfer->update(['successful' => true]);

            return $server;
        });

        // Delete Cloudflare/SRV subdomain records AFTER the transaction commits.
        // Doing this inside the transaction risks rolling back the node_id update if
        // the Cloudflare API throws an unexpected exception.
        $subdomains = ServerSubdomain::where('server_id', $server->id)->get();
        foreach ($subdomains as $subdomain) {
            if ($subdomain->cloudflare_record && is_array($subdomain->cloudflare_record)) {
                $cloudflareRecord = $subdomain->cloudflare_record;
                $zoneId = $cloudflareRecord['zone_id'] ?? null;
                $recordId = $cloudflareRecord['record_id'] ?? $cloudflareRecord['id'] ?? null;

                if ($zoneId && $recordId) {
                    try {
                        $this->cloudflareService->deleteDNSRecord($zoneId, $recordId);
                    } catch (Exception $e) {
                        Log::error('Failed to delete Cloudflare DNS record during server transfer: ' . $e->getMessage());
                    }
                }
            }

            if ($subdomain->srv_record && is_array($subdomain->srv_record)) {
                $srvRecord = $subdomain->srv_record;
                $zoneId = $srvRecord['zone_id'] ?? null;
                $recordId = $srvRecord['record_id'] ?? $srvRecord['id'] ?? null;

                if ($zoneId && $recordId) {
                    try {
                        $this->cloudflareService->deleteSRVRecord($zoneId, $recordId);
                    } catch (Exception $e) {
                        Log::error('Failed to delete Cloudflare SRV record during server transfer: ' . $e->getMessage());
                    }
                }
            }

            $subdomain->delete();
        }

        // Delete the server from the old node making sure to point it to the old node so
        // that we do not delete it from the new node the server was transferred to.
        try {
            $this->daemonServerRepository
                ->setServer($server)
                ->setNode($transfer->oldNode)
                ->delete();
        } catch (DaemonConnectionException $exception) {
            Log::warning($exception, ['transfer_id' => $server->transfer->id]);
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    
    protected function processFailedTransfer(ServerTransfer $transfer): JsonResponse
    {
        $this->connection->transaction(function () use (&$transfer) {
            $transfer->forceFill(['successful' => false])->saveOrFail();

            $allocations = array_merge([$transfer->new_allocation], $transfer->new_additional_allocations);
            Allocation::query()->whereIn('id', $allocations)->update(['server_id' => null]);
        });

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
