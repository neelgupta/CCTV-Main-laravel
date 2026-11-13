<?php

namespace App\Services;

use App\Models\DriveConnection;
use App\Models\DriveConnectionLog;
use Illuminate\Support\Facades\DB;

/**
 * Persists Drive connections and writes the audit trail. Reconnecting with a
 * different Google account disconnects the previous one (logged) and activates
 * the new one. Existing uploaded files keep their own stored metadata, so they
 * remain traceable regardless of the current active connection.
 */
class DriveConnectionManager
{
    /**
     * Activate (or reconnect) a Drive account for the given owner.
     *
     * @param  array{access_token:?string,refresh_token:?string,expires_at:?\DateTimeInterface,account_email:?string,account_id:?string,root_folder_id:?string}  $data
     */
    public function connect(string $ownerType, ?int $companyId, array $data, ?int $userId): DriveConnection
    {
        return DB::transaction(function () use ($ownerType, $companyId, $data, $userId) {
            $previous = $this->activeConnection($ownerType, $companyId);

            // Deactivate any currently-active connection for this owner.
            if ($previous) {
                $previous->update([
                    'status'          => DriveConnection::STATUS_DISCONNECTED,
                    'disconnected_at' => now(),
                ]);
            }

            $connection = DriveConnection::create([
                'company_id'       => $companyId,
                'owner_type'       => $ownerType,
                'account_email'    => $data['account_email'] ?? null,
                'account_id'       => $data['account_id'] ?? null,
                'access_token'     => $data['access_token'] ?? null,
                'refresh_token'    => $data['refresh_token'] ?? null,
                'token_expires_at' => $data['expires_at'] ?? null,
                'root_folder_id'   => $data['root_folder_id'] ?? null,
                'status'           => DriveConnection::STATUS_ACTIVE,
                'connected_by'     => $userId,
                'connected_at'     => now(),
            ]);

            $this->log(
                ownerType: $ownerType,
                companyId: $companyId,
                action: $previous ? DriveConnectionLog::ACTION_RECONNECTED : DriveConnectionLog::ACTION_CONNECTED,
                status: DriveConnection::STATUS_ACTIVE,
                currentEmail: $connection->account_email,
                previousEmail: $previous?->account_email,
                userId: $userId,
                connectedAt: $connection->connected_at,
            );

            return $connection;
        });
    }

    /**
     * Disconnect the active Drive account for the given owner.
     */
    public function disconnect(string $ownerType, ?int $companyId, ?int $userId): void
    {
        DB::transaction(function () use ($ownerType, $companyId, $userId) {
            $active = $this->activeConnection($ownerType, $companyId);
            if (! $active) {
                return;
            }

            $active->update([
                'status'          => DriveConnection::STATUS_DISCONNECTED,
                'disconnected_at' => now(),
            ]);

            $this->log(
                ownerType: $ownerType,
                companyId: $companyId,
                action: DriveConnectionLog::ACTION_DISCONNECTED,
                status: DriveConnection::STATUS_DISCONNECTED,
                currentEmail: null,
                previousEmail: $active->account_email,
                userId: $userId,
                disconnectedAt: now(),
            );
        });
    }

    public function updateRootFolderId(DriveConnection $connection, string $folderId): void
    {
        $connection->update(['root_folder_id' => $folderId]);
    }

    private function activeConnection(string $ownerType, ?int $companyId): ?DriveConnection
    {
        return $ownerType === DriveConnection::OWNER_SUPER_ADMIN
            ? DriveConnection::activeForSuperAdmin()
            : DriveConnection::activeForCompany((int) $companyId);
    }

    private function log(
        string $ownerType,
        ?int $companyId,
        string $action,
        string $status,
        ?string $currentEmail,
        ?string $previousEmail,
        ?int $userId,
        ?\DateTimeInterface $connectedAt = null,
        ?\DateTimeInterface $disconnectedAt = null,
    ): void {
        DriveConnectionLog::create([
            'company_id'             => $companyId,
            'drive_type'             => $ownerType,
            'action'                 => $action,
            'status'                 => $status,
            'account_email'          => $currentEmail ?? $previousEmail,
            'previous_account_email' => $previousEmail,
            'current_account_email'  => $currentEmail,
            'performed_by'           => $userId,
            'connected_at'           => $connectedAt,
            'disconnected_at'        => $disconnectedAt,
        ]);
    }
}
