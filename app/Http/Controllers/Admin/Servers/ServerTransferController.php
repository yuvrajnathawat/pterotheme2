<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\AgentServerTransfer;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\GlobalStorageBackend;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class ServerTransferController extends Controller
{
    /**
     * ServerTransferController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private AllocationRepositoryInterface $allocationRepository,
        private ConnectionInterface $connection,
        private DaemonTransferRepository $daemonTransferRepository,
        private DaemonServerRepository $daemonServerRepository,
        private NodeJWTService $nodeJWTService,
        private NodeRepository $nodeRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    private const ADDON_SETTINGS_KEY = 'settings::app:addons:hyperv1';

    /**
     * Starts a transfer of a server to a new node.
     *
     * @throws \Throwable
     */
    public function transfer(Request $request, Server $server): RedirectResponse
    {
        $validatedData = $request->validate([
            'node_id' => 'required|exists:nodes,id',
            'allocation_id' => 'required|bail|unique:servers|exists:allocations,id',
            'allocation_additional' => 'nullable',
            'use_agent_transfer' => 'nullable|boolean',
            'include_native_backups' => 'nullable|boolean',
        ]);

        // If agent transfer is selected, use the agent-powered P2P migration
        if (!empty($validatedData['use_agent_transfer'])) {
            return $this->agentTransfer($request, $server, $validatedData);
        }

        $node_id = $validatedData['node_id'];
        $allocation_id = intval($validatedData['allocation_id']);
        $additional_allocations = array_map('intval', $validatedData['allocation_additional'] ?? []);

        // Check if the node is viable for the transfer.
        $node = $this->nodeRepository->getNodeWithResourceUsage($node_id);
        if (!$node->isViable($server->memory, $server->disk)) {
            $this->alert->danger(trans('admin/server.alerts.transfer_not_viable'))->flash();

            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        $server->validateTransferState();

        $this->connection->transaction(function () use ($server, $node_id, $allocation_id, $additional_allocations) {
            // Create a new ServerTransfer entry.
            $transfer = new ServerTransfer();

            $transfer->server_id = $server->id;
            $transfer->old_node = $server->node_id;
            $transfer->new_node = $node_id;
            $transfer->old_allocation = $server->allocation_id;
            $transfer->new_allocation = $allocation_id;
            $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id')->values()->toArray();
            $transfer->new_additional_allocations = $additional_allocations;

            $transfer->save();

            // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
            $this->assignAllocationsToServer($server, $node_id, $allocation_id, $additional_allocations);

            // Generate a token for the destination node that the source node can use to authenticate with.
            $token = $this->nodeJWTService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setSubject($server->uuid)
                ->handle($transfer->newNode, $server->uuid, 'sha256');

            // Notify the source node of the pending outgoing transfer.
            $this->daemonTransferRepository->setServer($server)->notify($transfer->newNode, $token);

            return $transfer;
        });

        $this->alert->success(trans('admin/server.alerts.transfer_started'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Assigns the specified allocations to the specified server.
     */
    private function assignAllocationsToServer(Server $server, int $node_id, int $allocation_id, array $additional_allocations)
    {
        $allocations = $additional_allocations;
        $allocations[] = $allocation_id;

        $unassigned = $this->allocationRepository->getUnassignedAllocationIds($node_id);

        $updateIds = [];
        foreach ($allocations as $allocation) {
            if (!in_array($allocation, $unassigned)) {
                continue;
            }

            $updateIds[] = $allocation;
        }

        if (!empty($updateIds)) {
            $this->allocationRepository->updateWhereIn('id', $updateIds, ['server_id' => $server->id]);
        }
    }

    /**
     * Get the wings-agent endpoint for a given node.
     */
    private function getAgentEndpoint(int $nodeId): ?string
    {
        try {
            $raw = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data = json_decode($raw, true) ?: [];
            $endpoints = $data['addons']['wings-addon']['node_endpoints'] ?? [];
            foreach ($endpoints as $ep) {
                if (((int) ($ep['node_id'] ?? 0)) === $nodeId) {
                    $ip = $ep['ip'] ?? '';
                    try { $ip = Crypt::decryptString($ip); } catch (Throwable) {}
                    $port = $ep['port'] ?? '';
                    try { $port = Crypt::decryptString($port); } catch (Throwable) {}
                    $port = !empty($port) ? (int) $port : 8443;
                    return "https://{$ip}:{$port}";
                }
            }
        } catch (Throwable) {}
        return null;
    }

    /**
     * Resolve the native backup backend config for a given node.
     * Returns the backend array to send to the agent (same shape as UploadNativeBackupExternally),
     * or null if there is nothing to send (no external storage and dedup disabled).
     *
     * When $hasResticBackups is true, always returns at least a local backend
     * so the agent can export restic snapshots during transfer staging.
     */
    private function getNativeBackupBackend(int $nodeId, bool $hasResticBackups = false): ?array
    {
        try {
            $raw  = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data = json_decode($raw, true) ?: [];
        } catch (Throwable) {
            return null;
        }

        $nativeExt    = $data['addons']['wings-addon']['native_backup_external'] ?? [];
        $dedupEnabled = (bool) ($data['addons']['wings-addon']['dedup_native_backups'] ?? false);
        $extEnabled   = !empty($nativeExt['enabled']);

        // Priority 1: external backend mapped for this node
        if ($extEnabled) {
            $backendId = null;
            foreach ($nativeExt['mappings'] ?? [] as $m) {
                if ((int) ($m['node_id'] ?? 0) === $nodeId) {
                    $backendId = (int) ($m['backend_id'] ?? 0);
                    break;
                }
            }
            if ($backendId) {
                $global = GlobalStorageBackend::find($backendId);
                if ($global) {
                    $creds = json_decode($global->credentials, true) ?? [];
                    foreach (['secret_key', 'password', 'ssh_key', 'encryption_key'] as $field) {
                        if (!empty($creds[$field])) {
                            try { $creds[$field] = Crypt::decryptString($creds[$field]); } catch (Throwable) {}
                        }
                    }
                    return array_merge(['type' => $global->type], $creds);
                }
            }
        }

        // Priority 2: local restic dedup (no external backend)
        if ($dedupEnabled || $hasResticBackups) {
            return ['type' => 'local', 'local_path' => ''];
        }

        return null;
    }

    /**
     * Get the wings-agent access token.
     */
    private function getAgentToken(): ?string
    {
        try {
            $raw = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data = json_decode($raw, true) ?: [];
            $enc = $data['addons']['wings-addon']['access_token'] ?? '';
            return empty($enc) ? null : Crypt::decryptString($enc);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Perform an agent-powered P2P server migration.
     */
    private function agentTransfer(Request $request, Server $server, array $validatedData): RedirectResponse
    {
        $nodeId = $validatedData['node_id'];
        $allocationId = intval($validatedData['allocation_id']);
        $additionalAllocations = array_map('intval', $validatedData['allocation_additional'] ?? []);
        $includeNativeBackups = !empty($validatedData['include_native_backups']);

        // Check if the destination node is viable
        $destNode = $this->nodeRepository->getNodeWithResourceUsage($nodeId);
        if (!$destNode->isViable($server->memory, $server->disk)) {
            $this->alert->danger(trans('admin/server.alerts.transfer_not_viable'))->flash();
            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        $server->validateTransferState();

        // Check agent endpoints are available
        $sourceEndpoint = $this->getAgentEndpoint($server->node_id);
        $destEndpoint = $this->getAgentEndpoint($nodeId);
        $agentToken = $this->getAgentToken();

        if (!$sourceEndpoint || !$destEndpoint || !$agentToken) {
            $this->alert->danger('Wings agent is not configured for one or both nodes. Cannot use agent transfer.')->flash();
            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        // Pre-flight: verify both source and destination agents are reachable before committing anything.
        $unhealthy = [];
        foreach (['source' => $sourceEndpoint, 'destination' => $destEndpoint] as $label => $endpoint) {
            try {
                $resp = Http::withoutVerifying()->timeout(5)
                    ->withToken($agentToken)
                    ->get("{$endpoint}/health");
                if (!$resp->successful()) {
                    $unhealthy[] = "{$label} agent returned HTTP " . $resp->status();
                }
            } catch (Throwable $e) {
                $unhealthy[] = "{$label} agent unreachable: " . $e->getMessage();
            }
        }
        if (!empty($unhealthy)) {
            $this->alert->danger('Pre-flight check failed: ' . implode('; ', $unhealthy) . '. Transfer aborted.')->flash();
            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        try {
            $this->connection->transaction(function () use (
                $server, $nodeId, $allocationId, $additionalAllocations,
                $includeNativeBackups, $sourceEndpoint, $destEndpoint, $agentToken
            ) {
                // Create server_transfers record (for historical compatibility)
                $serverTransfer = new ServerTransfer();
                $serverTransfer->server_id = $server->id;
                $serverTransfer->old_node = $server->node_id;
                $serverTransfer->new_node = $nodeId;
                $serverTransfer->old_allocation = $server->allocation_id;
                $serverTransfer->new_allocation = $allocationId;
                $serverTransfer->old_additional_allocations = $server->allocations
                    ->where('id', '!=', $server->allocation_id)->pluck('id')->values()->toArray();
                $serverTransfer->new_additional_allocations = $additionalAllocations;
                $serverTransfer->save();

                // Assign allocations so they can't be taken
                $this->assignAllocationsToServer($server, $nodeId, $allocationId, $additionalAllocations);

                // Generate transfer token (32 bytes random)
                $transferToken = bin2hex(random_bytes(32));
                $transferId = Str::uuid()->toString();

                // Create agent_server_transfers record
                $agentTransfer = AgentServerTransfer::create([
                    'transfer_id' => $transferId,
                    'server_id' => $server->id,
                    'source_node_id' => $server->node_id,
                    'dest_node_id' => $nodeId,
                    'old_allocation_id' => $server->allocation_id,
                    'new_allocation_id' => $allocationId,
                    'old_additional_allocations' => $server->allocations
                        ->where('id', '!=', $server->allocation_id)->pluck('id')->values()->toArray(),
                    'new_additional_allocations' => $additionalAllocations,
                    'include_native_backups' => $includeNativeBackups,
                    'status' => 'pending',
                    'transfer_token' => Crypt::encryptString($transferToken),
                    'started_at' => now(),
                ]);

                // Get native backup file paths if needed.
                // Values are agent_external_path so the source agent can distinguish
                // restic dedup backups (value starts with "restic:") from plain tar.gz ones.
                $backupPaths = [];

                // First pass: collect all backup paths to detect restic usage.
                $allBackupEntries = [];
                if ($includeNativeBackups) {
                    $server->backups()
                        ->where('is_successful', true)
                        ->whereNull('deleted_at')
                        ->select(['uuid', 'disk', 'agent_external_path'])
                        ->each(function ($b) use (&$allBackupEntries) {
                            $allBackupEntries[] = [
                                'uuid' => $b->uuid,
                                'ext_path' => (string) ($b->agent_external_path ?? ''),
                            ];
                        });
                }

                $hasResticBackups = collect($allBackupEntries)
                    ->contains(fn ($e) => str_starts_with($e['ext_path'], 'restic:'));

                // Resolve the native backup backend so the source agent can export
                // restic snapshots during the transfer staging phase.
                $nativeBackupBackend = $includeNativeBackups
                    ? $this->getNativeBackupBackend($server->node_id, $hasResticBackups)
                    : null;

                // Determine if the source node's backup storage is external (S3/SFTP).
                // If external, restic backups live on the remote storage and should NOT
                // be transferred — the dest node accesses the same storage directly.
                $sourceBackendIsExternal = $nativeBackupBackend
                    && !in_array($nativeBackupBackend['type'] ?? '', ['local', ''], true);

                foreach ($allBackupEntries as $entry) {
                    // Skip restic backups on external storage — they stay remote.
                    if ($sourceBackendIsExternal && str_starts_with($entry['ext_path'], 'restic:')) {
                        continue;
                    }
                    $backupPaths[$entry['uuid']] = $entry['ext_path'];
                }

                // Extract dest agent IP for source to allow
                $destIp = parse_url($destEndpoint, PHP_URL_HOST);

                // IMPORTANT: json_encode([]) produces "[]" (JSON array) but the agent
                // expects a JSON object for backup_paths. Use stdClass for empty maps.
                $backupPathsJson = empty($backupPaths) ? new \stdClass() : $backupPaths;

                // POST to source agent: prepare the transfer
                $preparePayload = json_encode([
                    'transfer_id'           => $transferId,
                    'server_uuid'           => $server->uuid,
                    'volume_path'           => "/var/lib/pterodactyl/volumes/{$server->uuid}",
                    'transfer_token'        => $transferToken,
                    'dest_agent_ip'         => $destIp,
                    'include_native_backups' => $includeNativeBackups,
                    'backup_paths'          => $backupPathsJson,
                    'backup_backend'        => $nativeBackupBackend ?? new \stdClass(),
                ]);

                Log::debug('[AgentTransfer] Calling source /transfer/prepare', [
                    'transfer_id'    => $transferId,
                    'server_uuid'    => $server->uuid,
                    'source'         => $sourceEndpoint,
                    'dest_ip'        => $destIp,
                    'include_backups' => $includeNativeBackups,
                    'backup_count'   => count($backupPaths),
                ]);

                $sourceResponse = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders($this->agentHeaders($agentToken, $preparePayload))
                    ->withBody($preparePayload, 'application/json')
                    ->post("{$sourceEndpoint}/transfer/prepare");

                Log::debug('[AgentTransfer] Source /transfer/prepare response', [
                    'status' => $sourceResponse->status(),
                    'body'   => $sourceResponse->body(),
                ]);

                if (!$sourceResponse->successful()) {
                    throw new \RuntimeException('Source agent prepare failed: ' . $sourceResponse->body());
                }

                $sourceData = $sourceResponse->json();

                // 200 = synchronous (legacy), 202 = async prepare (agent archives in background).
                // For 202 the checksum/chunks are not known yet — the dest agent will verify
                // against the manifest checksum it downloads directly from the source.
                $agentTransfer->update([
                    'status' => 'preparing',
                    'file_checksum' => $sourceData['checksum'] ?? null,
                    'total_chunks' => $sourceData['total_chunks'] ?? 0,
                    'total_bytes' => $sourceData['total_bytes'] ?? 0,
                ]);

                // Extract source agent IP+port for dest to pull from
                $sourceIp = parse_url($sourceEndpoint, PHP_URL_HOST);
                $sourcePort = parse_url($sourceEndpoint, PHP_URL_PORT) ?: 8443;

                // POST to destination agent: start receiving
                $receivePayload = json_encode([
                    'transfer_id'      => $transferId,
                    'source_agent_ip'  => $sourceIp,
                    'source_agent_port' => (int) $sourcePort,
                    'transfer_token'   => $transferToken,
                    'expected_checksum' => $sourceData['checksum'] ?? '',
                    'server_uuid'      => $server->uuid,
                ]);

                Log::debug('[AgentTransfer] Calling dest /transfer/receive', [
                    'transfer_id' => $transferId,
                    'dest'        => $destEndpoint,
                    'source_ip'   => $sourceIp,
                    'source_port' => $sourcePort,
                    'checksum'    => $sourceData['checksum'] ?? '',
                ]);

                $destResponse = Http::withoutVerifying()
                    ->timeout(15)
                    ->withHeaders($this->agentHeaders($agentToken, $receivePayload))
                    ->withBody($receivePayload, 'application/json')
                    ->post("{$destEndpoint}/transfer/receive");

                Log::debug('[AgentTransfer] Dest /transfer/receive response', [
                    'status' => $destResponse->status(),
                    'body'   => $destResponse->body(),
                ]);

                if (!$destResponse->successful()) {
                    throw new \RuntimeException('Dest agent receive failed: ' . $destResponse->body());
                }

                // Only advance to 'transferring' if the transfer hasn't already been
                // completed/failed by the dest agent callback. This prevents a race
                // condition where very fast transfers (< 2 seconds) call notifyTransferComplete
                // before this line executes, which would overwrite 'awaiting_source_cleanup'
                // back to 'transferring', leaving the UI stuck at "Transferring / Pending / 0/0".
                AgentServerTransfer::where('transfer_id', $transferId)
                    ->whereIn('status', ['pending', 'preparing'])
                    ->update(['status' => 'transferring']);
            });

            $this->alert->success('Agent-powered transfer initiated successfully.')->flash();
        } catch (Throwable $e) {
            Log::error("Agent transfer failed for server {$server->id}: {$e->getMessage()}");
            $this->alert->danger('Agent transfer failed: ' . $e->getMessage())->flash();
        }

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Build authentication headers for a wings-agent POST request.
     * The agent requires Bearer token auth + HMAC-SHA256 request signing.
     */
    private function agentHeaders(string $token, string $body): array
    {
        $ts  = (string) time();
        $sig = 'sha256=' . hash_hmac('sha256', $ts . $body, $token);
        return [
            'Authorization'     => "Bearer {$token}",
            'X-Hyper-Timestamp' => $ts,
            'X-Hyper-Signature' => $sig,
        ];
    }

    /**
     * Returns the latest transfer progress data as JSON for AJAX polling.
     */
    public function transferProgress(Request $request, Server $server): \Illuminate\Http\JsonResponse
    {
        $xfer = AgentServerTransfer::where('server_id', $server->id)
            ->latest()
            ->first();

        if (!$xfer) {
            return response()->json(['error' => 'No transfer found'], 404);
        }

        $bytesTotal = $xfer->total_bytes ?? 0;
        $bytesXferred = $xfer->bytes_transferred ?? 0;
        // Always show 100% for terminal-success states regardless of tracked bytes
        $terminalSuccess = in_array($xfer->status, ['completed', 'awaiting_source_cleanup']);
        $pct = $terminalSuccess ? 100 : ($bytesTotal > 0 ? round($bytesXferred / $bytesTotal * 100, 1) : 0);

        return response()->json([
            'status' => $xfer->status,
            'phase' => $xfer->phase ?? $xfer->status,
            'bytes_transferred' => $bytesXferred,
            'total_bytes' => $bytesTotal,
            'progress_pct' => $pct,
            'files_completed' => $xfer->files_completed ?? 0,
            'total_files' => $xfer->total_files ?? 0,
            'files_failed' => $xfer->files_failed ?? 0,
            'current_file' => $xfer->current_file,
            'error_message' => $xfer->error_message,
            'started_at' => $xfer->started_at?->toIso8601String(),
            'completed_at' => $xfer->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Force-clears a stuck transfer record for a server.
     * Marks the AgentServerTransfer as failed/cancelled, clears the native ServerTransfer,
     * notifies wings-agent to cancel the actual transfer, and resets the server's transferring flag.
     */
    public function forceClearTransfer(Request $request, Server $server): RedirectResponse
    {
        $agentTransfer = AgentServerTransfer::where('server_id', $server->id)
            ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->latest()
            ->first();

        if ($agentTransfer) {
            // Notify both source and destination wings-agents to cancel the transfer
            $this->notifyAgentCancelTransfer($agentTransfer);

            // Free new allocations that were reserved for the transfer
            $newAllocations = array_merge(
                [$agentTransfer->new_allocation_id],
                $agentTransfer->new_additional_allocations ?? []
            );
            \Pterodactyl\Models\Allocation::whereIn('id', $newAllocations)
                ->where('server_id', $server->id)
                ->update(['server_id' => null]);

            $agentTransfer->update([
                'status'       => 'cancelled',
                'completed_at' => now(),
            ]);
        }

        // Also clear any native Pterodactyl ServerTransfer record that is still pending
        $nativeTransfer = $server->transfer;
        if ($nativeTransfer) {
            $nativeTransfer->update(['successful' => false]);
        }

        // Reset the server's status if it is stuck as "transferring"
        if ($server->status === 'transferring') {
            $server->fill(['status' => null])->saveOrFail();
        }

        $this->alert->success('Transfer has been force-cancelled. Both agent and native transfer records have been cleared.')->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Notify wings-agent(s) to cancel an active transfer.
     */
    private function notifyAgentCancelTransfer(AgentServerTransfer $transfer): void
    {
        $agentToken = $this->getAgentToken();
        if (!$agentToken) {
            return;
        }

        // Cancel on destination agent
        $destEndpoint = $this->getAgentEndpoint($transfer->dest_node_id);
        if ($destEndpoint) {
            try {
                $payload = json_encode(['transfer_id' => $transfer->transfer_id]);
                Http::withoutVerifying()
                    ->timeout(5)
                    ->withHeaders($this->agentHeaders($agentToken, $payload))
                    ->withBody($payload, 'application/json')
                    ->post("{$destEndpoint}/transfer/{$transfer->transfer_id}/cancel");
            } catch (Throwable $e) {
                Log::warning("[AgentTransfer] Could not notify dest agent to cancel: {$e->getMessage()}");
            }
        }

        // Cancel/cleanup on source agent
        $sourceEndpoint = $this->getAgentEndpoint($transfer->source_node_id);
        if ($sourceEndpoint) {
            try {
                Http::withoutVerifying()
                    ->timeout(5)
                    ->withHeaders($this->agentHeaders($agentToken, ''))
                    ->delete("{$sourceEndpoint}/transfer/{$transfer->transfer_id}");
            } catch (Throwable $e) {
                Log::warning("[AgentTransfer] Could not notify source agent to cleanup: {$e->getMessage()}");
            }
        }
    }

    /**
     * Query the destination agent for real-time file count + byte total
     * of the server's volume. Returns JSON for the admin verification UI.
     * GET /admin/servers/{server}/manage/transfer/verify-dest
     */
    public function verifyTransferDest(Request $request, Server $server): \Illuminate\Http\JsonResponse
    {
        $transfer = AgentServerTransfer::where('server_id', $server->id)
            ->whereIn('status', ['awaiting_source_cleanup', 'completed'])
            ->latest()
            ->first();

        if (!$transfer) {
            return response()->json(['error' => 'No completed transfer found for this server'], 404);
        }

        $agentToken = $this->getAgentToken();
        $destEndpoint = $this->getAgentEndpoint($transfer->dest_node_id);

        if (!$agentToken || !$destEndpoint) {
            return response()->json(['error' => 'Destination agent not configured'], 500);
        }

        try {
            $resp = Http::withoutVerifying()
                ->timeout(10)
                ->withToken($agentToken)
                ->get("{$destEndpoint}/volume-stats", ['uuid' => $server->uuid]);

            if (!$resp->successful()) {
                return response()->json(['error' => 'Agent returned ' . $resp->status() . ': ' . $resp->body()], 502);
            }

            $data = $resp->json();

            return response()->json([
                'transfer_id'          => $transfer->transfer_id,
                'manifest_files'       => $transfer->total_files ?? 0,
                'manifest_bytes'       => $transfer->total_bytes ?? 0,
                'dest_file_count'      => $data['file_count'] ?? 0,
                'dest_total_bytes'     => $data['total_bytes'] ?? 0,
                'source_cleaned'       => (bool) $transfer->source_cleaned,
                'status'               => $transfer->status,
            ]);
        } catch (Throwable $e) {
            Log::error('[AgentTransfer] verifyTransferDest failed: ' . $e->getMessage(), [
                'server_id'   => $server->id,
                'transfer_id' => $transfer->transfer_id,
            ]);
            return response()->json(['error' => 'Failed to contact destination agent: ' . $e->getMessage()], 502);
        }
    }

    /**
     * Admin confirms the transfer is complete and approves deletion of source data.
     * Calls Wings on the source node to delete the server container + volume, then
     * marks the transfer as completed and source_cleaned = true.
     * POST /admin/servers/{server}/manage/transfer/confirm-delete-source
     */
    public function confirmDeleteSource(Request $request, Server $server): \Illuminate\Http\JsonResponse
    {
        $transfer = AgentServerTransfer::where('server_id', $server->id)
            ->where('status', 'awaiting_source_cleanup')
            ->latest()
            ->first();

        if (!$transfer) {
            return response()->json(['error' => 'No transfer awaiting source cleanup for this server'], 404);
        }

        if ($transfer->source_cleaned) {
            return response()->json(['error' => 'Source has already been cleaned up'], 409);
        }

        // Re-verify destination file count before deleting (safety check).
        // Admin can pass force=1 to skip this check.
        $force = (bool) $request->input('force', false);
        if (!$force) {
            $agentToken = $this->getAgentToken();
            $destEndpoint = $this->getAgentEndpoint($transfer->dest_node_id);

            if ($agentToken && $destEndpoint) {
                try {
                    $resp = Http::withoutVerifying()
                        ->timeout(30)
                        ->withToken($agentToken)
                        ->get("{$destEndpoint}/volume-stats", ['uuid' => $server->uuid]);

                    if ($resp->successful()) {
                        $destData = $resp->json();
                        $destFiles = (int) ($destData['file_count'] ?? 0);
                        $manifestFiles = (int) ($transfer->total_files ?? 0);

                        // Save verified stats for the record
                        $transfer->update([
                            'dest_verified_files' => $destFiles,
                            'dest_verified_bytes'  => (int) ($destData['total_bytes'] ?? 0),
                        ]);

                        // If manifest had files and destination has 0, block deletion.
                        if ($manifestFiles > 0 && $destFiles === 0) {
                            return response()->json([
                                'error'          => 'Safety abort: destination has 0 files but manifest expected ' . $manifestFiles . '. Pass force=1 to override.',
                                'manifest_files' => $manifestFiles,
                                'dest_files'     => $destFiles,
                            ], 409);
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('[AgentTransfer] confirmDeleteSource pre-verify failed (non-fatal): ' . $e->getMessage());
                    // Non-fatal: proceed if we can't reach the dest agent
                }
            }
        }

        // Delete from source Wings (removes container + volume on source node).
        $sourceNode = Node::find($transfer->source_node_id);
        if ($sourceNode) {
            try {
                $this->daemonServerRepository
                    ->setServer($server)
                    ->setNode($sourceNode)
                    ->delete();
                Log::info('[AgentTransfer] Source server deleted from Wings by admin', [
                    'transfer_id' => $transfer->transfer_id,
                    'server_id'   => $server->id,
                    'source_node' => $transfer->source_node_id,
                    'admin'       => $request->user()?->email,
                ]);
            } catch (DaemonConnectionException $ex) {
                Log::warning('[AgentTransfer] Could not delete source from Wings (non-fatal, marking cleaned anyway): ' . $ex->getMessage());
            }
        }

        // Mark as fully completed with source cleaned.
        $transfer->update([
            'status'         => 'completed',
            'source_cleaned' => true,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Source data deleted and transfer marked as fully completed.',
            'transfer_id' => $transfer->transfer_id,
        ]);
    }
}

