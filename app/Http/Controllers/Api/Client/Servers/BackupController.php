<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Throwable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Models\GlobalStorageBackend;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Backups\DeleteBackupService;
use Pterodactyl\Services\Backups\DownloadLinkService;
use Pterodactyl\Repositories\Eloquent\BackupRepository;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Transformers\Api\Client\BackupTransformer;
use Pterodactyl\Models\NodeBackup;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\StoreBackupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\RestoreBackupRequest;

use Pterodactyl\Jobs\RestoreNativeBackupJob;

class BackupController extends ClientApiController
{
    
    /**
     * BackupController constructor.
     */
    public function __construct(
        private DaemonBackupRepository $daemonRepository,
        private DeleteBackupService $deleteBackupService,
        private InitiateBackupService $initiateBackupService,
        private DownloadLinkService $downloadLinkService,
        private BackupRepository $repository,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct();
    }

    /**
     * Returns auto (node) backups for a server, for display in the server backup page.
     *
     * @throws AuthorizationException
     */
    public function autoBackups(Request $request, Server $server): JsonResponse
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_READ, $server)) {
            throw new AuthorizationException();
        }

        $items = NodeBackup::where('server_id', $server->id)
            ->where('is_node_archive', false)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($nb) {
                $startedAt = $nb->started_at ?? $nb->created_at;
                return [
                    'object' => 'server_backup',
                    'attributes' => [
                        'uuid'          => $nb->run_id . ':' . $nb->id,
                        'is_successful' => $nb->status === 'completed',
                        'is_locked'     => false,
                        'name'          => 'Auto Backup ' . $startedAt->format('M d, Y H:i'),
                        'ignored_files' => '',
                        'checksum'      => '',
                        'bytes'         => $nb->size_bytes ?? 0,
                        'created_at'    => $startedAt->toAtomString(),
                        'completed_at'  => $nb->completed_at ? $nb->completed_at->toAtomString() : null,
                        'is_automated'  => true,
                        'status'        => $nb->status,
                    ],
                ];
            })
            ->values();

        return new JsonResponse(['data' => $items]);
    }

    /**
     * Returns all the backups for a given server instance in a paginated
     * result set.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request, Server $server): array
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_READ, $server)) {
            throw new AuthorizationException();
        }

        $limit = min($request->query('per_page') ?? 20, 50);

        return $this->fractal->collection($server->backups()->paginate($limit))
            ->transformWith($this->getTransformer(BackupTransformer::class))
            ->addMeta([
                'backup_count' => $this->repository->getNonFailedBackups($server)->count(),
            ])
            ->toArray();
    }

    /**
     * Starts the backup process for a server.
     *
     * @throws \Spatie\Fractalistic\Exceptions\InvalidTransformation
     * @throws \Spatie\Fractalistic\Exceptions\NoTransformerSpecified
     * @throws \Throwable
     */
    public function store(StoreBackupRequest $request, Server $server): array
    {
        $action = $this->initiateBackupService
            ->setIgnoredFiles(explode(PHP_EOL, $request->input('ignored') ?? ''));

        // Only set the lock status if the user even has permission to delete backups,
        // otherwise ignore this status. This gets a little funky since it isn't clear
        // how best to allow a user to create a backup that is locked without also preventing
        // them from just filling up a server with backups that can never be deleted?
        if ($request->user()->can(Permission::ACTION_BACKUP_DELETE, $server)) {
            $action->setIsLocked($request->boolean('is_locked'));
        }

        $backup = Activity::event('server:backup.start')->transaction(function ($log) use ($action, $server, $request) {
            $server->backups()->lockForUpdate();

            $backup = $action->handle($server, $request->input('name'));

            $log->subject($backup)->property([
                'name' => $backup->name,
                'locked' => $request->boolean('is_locked'),
            ]);

            return $backup;
        });

        return $this->fractal->item($backup)
            ->transformWith($this->getTransformer(BackupTransformer::class))
            ->toArray();
    }

    /**
     * Toggles the lock status of a given backup for a server.
     *
     * @throws \Throwable
     * @throws AuthorizationException
     */
    public function toggleLock(Request $request, Server $server, Backup $backup): array
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_DELETE, $server)) {
            throw new AuthorizationException();
        }

        $action = $backup->is_locked ? 'server:backup.unlock' : 'server:backup.lock';

        $backup->update(['is_locked' => !$backup->is_locked]);

        Activity::event($action)->subject($backup)->property('name', $backup->name)->log();

        return $this->fractal->item($backup)
            ->transformWith($this->getTransformer(BackupTransformer::class))
            ->toArray();
    }

    /**
     * Returns information about a single backup.
     *
     * @throws AuthorizationException
     */
    public function view(Request $request, Server $server, Backup $backup): array
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_READ, $server)) {
            throw new AuthorizationException();
        }

        return $this->fractal->item($backup)
            ->transformWith($this->getTransformer(BackupTransformer::class))
            ->toArray();
    }

    /**
     * Deletes a backup from the panel as well as the remote source where it is currently
     * being stored.
     *
     * Pass `?force=1` to skip Wings / restic calls and remove only the database record.
     * This is useful when the normal delete returns a 504 due to a dedupe-mode mismatch.
     *
     * @throws \Throwable
     */
    public function delete(Request $request, Server $server, Backup $backup): JsonResponse
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_DELETE, $server)) {
            throw new AuthorizationException();
        }

        $force = $request->boolean('force', false);

        $this->deleteBackupService->handle($backup, $force);

        Activity::event('server:backup.delete')
            ->subject($backup)
            ->property(['name' => $backup->name, 'failed' => !$backup->is_successful, 'force' => $force])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Admin-only: force-mark a stuck (never-completed) backup as failed so
     * the normal user delete flow can then remove it from the panel.
     *
     * @throws AuthorizationException
     */
    public function forceFail(Request $request, Server $server, Backup $backup): JsonResponse
    {
        if (!$request->user()->root_admin) {
            throw new AuthorizationException();
        }

        if (!is_null($backup->completed_at)) {
            return new JsonResponse(['error' => 'Backup is already completed and cannot be force-failed.'], 422);
        }

        $backup->update([
            'is_successful' => false,
            'completed_at'  => CarbonImmutable::now(),
        ]);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Download the backup for a given server instance. For daemon local files, the file
     * will be streamed back through the Panel. For AWS S3 files, a signed URL will be generated
     * which the user is redirected to.
     *
     * @throws \Throwable
     * @throws AuthorizationException
     */
    public function download(Request $request, Server $server, Backup $backup): JsonResponse
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_DOWNLOAD, $server)) {
            throw new AuthorizationException();
        }

        if ($backup->disk !== Backup::ADAPTER_AWS_S3 && $backup->disk !== Backup::ADAPTER_WINGS) {
            throw new BadRequestHttpException('The backup requested references an unknown disk driver type and cannot be downloaded.');
        }

        $url = $this->downloadLinkService->handle($backup, $request->user());

        Activity::event('server:backup.download')->subject($backup)->property('name', $backup->name)->log();

        return new JsonResponse([
            'object' => 'signed_url',
            'attributes' => ['url' => $url],
        ]);
    }

    /**
     * Handles restoring a backup by making a request to the Wings instance telling it
     * to begin the process of finding (or downloading) the backup and unpacking it
     * over the server files.
     *
     * If the "truncate" flag is passed through in this request then all the
     * files that currently exist on the server will be deleted before restoring.
     * Otherwise, the archive will simply be unpacked over the existing files.
     *
     * @throws \Throwable
     */
    public function restore(RestoreBackupRequest $request, Server $server, Backup $backup): JsonResponse
    {
        // Cannot restore a backup unless a server is fully installed and not currently
        // processing a different backup restoration request.
        if (!is_null($server->status)) {
            throw new BadRequestHttpException('This server is not currently in a state that allows for a backup to be restored.');
        }

        if (!$backup->is_successful && is_null($backup->completed_at)) {
            throw new BadRequestHttpException('This backup cannot be restored at this time: not completed or failed.');
        }

        Activity::event('server:backup.restore')
            ->subject($backup)
            ->property(['name' => $backup->name, 'truncate' => $request->input('truncate')])
            ->log();

        // For restic-dedup backups, dispatch the restore to a background job so the
        // HTTP response returns immediately — no 20s timeout waiting for restic.
        // The job stops the server, restores files directly (no .tar.gz created),
        // and clears the restoring status when complete.
        if (!empty($backup->agent_external_path) && str_starts_with((string) $backup->agent_external_path, 'restic:')) {
            $server->update(['status' => Server::STATUS_RESTORING_BACKUP]);
            RestoreNativeBackupJob::dispatch($backup->id, $server->id, (bool) $request->input('truncate'))
                ->onQueue('high');
            return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
        }

        // Legacy path: S3 or standard Wings local backups — synchronous restore via Wings.
        $log = Activity::event('server:backup.restore')
            ->subject($backup)
            ->property(['name' => $backup->name, 'truncate' => $request->input('truncate')]);

        $log->transaction(function () use ($backup, $server, $request) {
            if ($backup->disk === Backup::ADAPTER_AWS_S3) {
                $url = $this->downloadLinkService->handle($backup, $request->user());
            }

            if (!empty($backup->agent_external_path)) {
                $this->downloadFromExternalBackend($backup, $server);
            }

            $server->update(['status' => Server::STATUS_RESTORING_BACKUP]);

            $this->daemonRepository->setServer($server)->restore($backup, $url ?? null, $request->input('truncate'));

            if (!empty($backup->agent_external_path)) {
                $cached = sprintf('/var/lib/pterodactyl/backups/%s.tar.gz', $backup->uuid);
                if (file_exists($cached)) {
                    @unlink($cached);
                }
            }
        });

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * If the backup was uploaded to an external backend (or local restic dedup)
     * via the Wings Agent, tell the agent to restore it back to the Wings node's
     * local backup path so Wings can restore it normally.
     *
     * Handles two cases:
     *  1. External storage enabled with a backend mapping → external backend download.
     *  2. Local restic dedup (no external) — backup.agent_external_path starts with
     *     "restic::" — agent restores snapshot from its local restic repo.
     */
    private function downloadFromExternalBackend(Backup $backup, Server $server): void
    {
        try {
            $raw  = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
            $data = json_decode($raw, true) ?: [];
        } catch (Throwable) {
            return;
        }

        $nodeId    = (int) $server->node_id;
        $nativeExt = $data['addons']['wings-addon']['native_backup_external'] ?? [];

        // ── Resolve the backend to send in the download request ──────────────
        // Priority 1: external storage is on and this node has a backend mapped.
        // Priority 2: backup is a local restic dedup snapshot (external off) —
        //             use empty local_path so the agent uses its default repo.
        $backendPayload = null;

        if (!empty($nativeExt['enabled'])) {
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
                    $backendPayload = array_merge(['type' => $global->type], $creds);
                }
            }
        }

        // Fallback: local restic dedup — external_path starts with "restic::"
        if ($backendPayload === null && str_starts_with((string) $backup->agent_external_path, 'restic::')) {
            $backendPayload = ['type' => 'local', 'local_path' => ''];
        }

        if ($backendPayload === null) {
            // Nothing to do — no backend and not a local restic backup.
            return;
        }

        // ── Find Wings Agent endpoint + token ────────────────────────────────
        $endpoint = null;
        foreach ($data['addons']['wings-addon']['node_endpoints'] ?? [] as $ep) {
            if (((int) ($ep['node_id'] ?? 0)) !== $nodeId) continue;
            $ip = $ep['ip'] ?? '';
            try { $ip = Crypt::decryptString($ip); } catch (Throwable) {}
            $rawPort = (string) ($ep['port'] ?? '8443');
            try { $port = (int) Crypt::decryptString($rawPort); } catch (Throwable) { $port = (int) $rawPort; }
            if ($port < 1 || $port > 65535) $port = 8443;
            $endpoint = "https://{$ip}:{$port}";
            break;
        }
        if (!$endpoint) return;

        $encToken = $data['addons']['wings-addon']['access_token'] ?? '';
        if (empty($encToken)) return;
        try {
            $token = Crypt::decryptString($encToken);
        } catch (Throwable) {
            return;
        }

        // Wings expects the tar.gz at the flat path: backups/{uuid}.tar.gz
        // (same location Wings originally wrote it before dedup removed it).
        $localDest = sprintf('/var/lib/pterodactyl/backups/%s.tar.gz', $backup->uuid);

        $payload = json_encode([
            'backup_uuid'   => $backup->uuid,
            'server_uuid'   => $server->uuid,
            'external_path' => $backup->agent_external_path,
            'local_dest'    => $localDest,
            'backend'       => $backendPayload,
        ]);

        $ts  = (string) time();
        $sig = 'sha256=' . hash_hmac('sha256', $ts . $payload, $token);

        Log::info("[NativeBackupExternal] Sending restore-download request to agent", [
            'backup_uuid'   => $backup->uuid,
            'external_path' => $backup->agent_external_path,
            'local_dest'    => $localDest,
            'backend_type'  => $backendPayload['type'],
        ]);

        try {
            $response = Http::withoutVerifying()
                ->timeout(300)
                ->withHeaders([
                    'Authorization'     => "Bearer {$token}",
                    'X-Hyper-Timestamp' => $ts,
                    'X-Hyper-Signature' => $sig,
                ])
                ->withBody($payload, 'application/json')
                ->post("{$endpoint}/native-backup/download");

            if ($response->successful()) {
                Log::info("[NativeBackupExternal] Restore-download succeeded for backup {$backup->uuid}");
            } else {
                Log::warning("[NativeBackupExternal] Restore download failed for backup {$backup->uuid}: " . $response->body());
            }
        } catch (Throwable $e) {
            Log::warning("[NativeBackupExternal] Restore download exception for backup {$backup->uuid}: {$e->getMessage()}");
        }
    }
}
