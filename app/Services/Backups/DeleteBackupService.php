<?php

namespace Pterodactyl\Services\Backups;

use Throwable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\GlobalStorageBackend;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Extensions\Backups\BackupManager;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Exceptions\Service\Backup\BackupLockedException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class DeleteBackupService
{
    /**
     * DeleteBackupService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private BackupManager $manager,
        private DaemonBackupRepository $daemonBackupRepository,
        private SettingsRepository $settingsRepository
    ) {
    }

    /**
     * Deletes a backup from the system. If the backup is stored in S3 a request
     * will be made to delete that backup from the disk as well.
     *
     * When $force is true the Wings / restic API calls are skipped and the
     * database record is removed immediately.  Use this to recover from backups
     * that are stuck because of a dedupe-mode mismatch (504 errors).
     *
     * @throws \Throwable
     */
    public function handle(Backup $backup, bool $force = false): void
    {
        if ($backup->is_locked && ($backup->is_successful && !is_null($backup->completed_at))) {
            throw new BackupLockedException();
        }

        // Force-delete: skip all remote calls and just purge the DB record.
        if ($force) {
            $backup->delete();
            return;
        }

        if ($backup->disk === Backup::ADAPTER_AWS_S3) {
            $this->deleteFromS3($backup);

            return;
        }

        // If this backup was stored as a restic dedup snapshot, delete it from the repo.
        if (!empty($backup->agent_external_path) && str_starts_with((string) $backup->agent_external_path, 'restic:')) {
            $this->deleteFromResticAgent($backup);
        }

        $this->connection->transaction(function () use ($backup) {
            try {
                $this->daemonBackupRepository->setServer($backup->server)->delete($backup);
            } catch (DaemonConnectionException $exception) {
                // For incomplete/stuck backups (completed_at = null), silently swallow
                // all Wings errors. The backup never finished so there is nothing meaningful
                // for Wings to clean up, and blocking deletion would leave users stuck.
                $isIncomplete = is_null($backup->completed_at);
                if (!$isIncomplete) {
                    $previous = $exception->getPrevious();
                    if (!$previous instanceof ClientException || $previous->getResponse()->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                        throw $exception;
                    }
                }
            }

            $backup->delete();
        });
    }

    /**
     * Tell the Wings Agent to delete the restic snapshot for this backup.
     * Failures are logged but do not block the backup record from being deleted.
     */
    protected function deleteFromResticAgent(Backup $backup): void
    {
        try {
            $raw  = $this->settingsRepository->get('settings::app:addons:hyperv1', '{}');
            $data = json_decode($raw, true) ?: [];
        } catch (Throwable) {
            return;
        }

        $wingsAddon = $data['addons']['wings-addon'] ?? [];
        $nodeId     = (int) $backup->server->node_id;
        $nativeExt  = $wingsAddon['native_backup_external'] ?? [];

        // Resolve backend (same logic as BackupController::downloadFromExternalBackend).
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
        if ($backendPayload === null && str_starts_with((string) $backup->agent_external_path, 'restic::')) {
            $backendPayload = ['type' => 'local', 'local_path' => ''];
        }
        if ($backendPayload === null) {
            return;
        }

        // Resolve Wings Agent endpoint + token.
        $endpoint = null;
        foreach ($wingsAddon['node_endpoints'] ?? [] as $ep) {
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

        $encToken = $wingsAddon['access_token'] ?? '';
        if (empty($encToken)) return;
        try {
            $token = Crypt::decryptString($encToken);
        } catch (Throwable) {
            return;
        }

        $payload = json_encode([
            'backup_uuid'   => $backup->uuid,
            'server_uuid'   => $backup->server->uuid,
            'external_path' => $backup->agent_external_path,
            'backend'       => $backendPayload,
        ]);

        $ts  = (string) time();
        $sig = 'sha256=' . hash_hmac('sha256', $ts . $payload, $token);

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'Authorization'     => "Bearer {$token}",
                    'X-Hyper-Timestamp' => $ts,
                    'X-Hyper-Signature' => $sig,
                ])
                ->withBody($payload, 'application/json')
                ->post("{$endpoint}/native-backup/delete-snapshot");

            if ($response->successful()) {
                Log::info("[NativeBackupExternal] Deleted restic snapshot for backup {$backup->uuid}");
            } else {
                Log::warning("[NativeBackupExternal] Failed to delete restic snapshot for backup {$backup->uuid}: " . $response->body());
            }
        } catch (Throwable $e) {
            Log::warning("[NativeBackupExternal] Exception deleting restic snapshot for backup {$backup->uuid}: {$e->getMessage()}");
        }
    }

    protected function deleteFromS3(Backup $backup): void
    {
        $this->connection->transaction(function () use ($backup) {
            $backup->delete();

            
            $adapter = $this->manager->adapter(Backup::ADAPTER_AWS_S3);

            $adapter->getClient()->deleteObject([
                'Bucket' => $adapter->getBucket(),
                'Key' => sprintf('%s/%s.tar.gz', $backup->server->uuid, $backup->uuid),
            ]);
        });
    }
}
