<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Modules\Tenancy\Models\Tenant;
use Stancl\Tenancy\Tenancy;

class CommunicationEncryptionSyncService
{
    public function __construct(
        private readonly CommunicationEncryptionService $encryption,
        private readonly Tenancy $tenancy,
    ) {
    }

    public function sync(bool $shouldEncrypt): void
    {
        $originalTenantId = function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : null;

        try {
            $this->endTenantContext();
            $this->syncCurrentContext($shouldEncrypt);

            Tenant::query()
                ->select('id')
                ->orderBy('id')
                ->cursor()
                ->each(function (Tenant $tenant) use ($shouldEncrypt): void {
                    $this->runInTenantContext((string) $tenant->id, function () use ($shouldEncrypt): void {
                        $this->syncCurrentContext($shouldEncrypt);
                    });
                });
        } finally {
            $this->restoreTenantContext($originalTenantId);
        }
    }

    private function syncCurrentContext(bool $shouldEncrypt): void
    {
        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }

        $this->syncChatMessages($shouldEncrypt);
        $this->syncMailboxMessages($shouldEncrypt);

        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }
    }

    private function syncChatMessages(bool $shouldEncrypt): void
    {
        if (! Schema::hasTable('messages')
            || ! Schema::hasColumn('messages', 'body_encrypted')
            || ! Schema::hasColumn('messages', 'metadata_encrypted')) {
            return;
        }

        DB::table('messages')
            ->select(['id', 'body', 'body_encrypted', 'metadata', 'metadata_encrypted'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($shouldEncrypt) {
                foreach ($rows as $row) {
                    $updates = $shouldEncrypt
                        ? $this->encryptedChatMessageUpdates($row)
                        : $this->decryptedChatMessageUpdates($row);

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('messages')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            }, 'id');
    }

    private function syncMailboxMessages(bool $shouldEncrypt): void
    {
        if (! Schema::hasTable('mail_messages')
            || ! Schema::hasColumn('mail_messages', 'subject_encrypted')
            || ! Schema::hasColumn('mail_messages', 'body_encrypted')) {
            return;
        }

        DB::table('mail_messages')
            ->select(['id', 'subject', 'subject_encrypted', 'body', 'body_encrypted'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($shouldEncrypt) {
                foreach ($rows as $row) {
                    $updates = $shouldEncrypt
                        ? $this->encryptedMailboxMessageUpdates($row)
                        : $this->decryptedMailboxMessageUpdates($row);

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('mail_messages')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            }, 'id');
    }

    private function encryptedChatMessageUpdates(object $row): array
    {
        $updates = [];

        if ($row->body_encrypted === null && $row->body !== null) {
            $updates['body_encrypted'] = $this->encryption->encryptString((string) $row->body, 'chat.message.body');
            $updates['body'] = null;
        } elseif ($row->body_encrypted !== null && $row->body !== null) {
            $updates['body'] = null;
        }

        if ($row->metadata_encrypted === null && $row->metadata !== null) {
            $decodedMetadata = $this->decodeJsonValue($row->metadata);

            if ($decodedMetadata !== null) {
                $updates['metadata_encrypted'] = $this->encryption->encryptArray($decodedMetadata, 'chat.message.metadata');
                $updates['metadata'] = null;
            }
        } elseif ($row->metadata_encrypted !== null && $row->metadata !== null) {
            $updates['metadata'] = null;
        }

        return $updates;
    }

    private function decryptedChatMessageUpdates(object $row): array
    {
        $updates = [];

        if ($row->body !== null && $row->body_encrypted !== null) {
            $updates['body_encrypted'] = null;
        } elseif ($row->body === null && is_string($row->body_encrypted) && $row->body_encrypted !== '') {
            $decryptedBody = $this->encryption->decryptString($row->body_encrypted, 'chat.message.body');

            if ($decryptedBody !== null) {
                $updates['body'] = $decryptedBody;
                $updates['body_encrypted'] = null;
            }
        }

        if ($row->metadata !== null && $row->metadata_encrypted !== null) {
            $updates['metadata_encrypted'] = null;
        } elseif ($row->metadata === null && is_string($row->metadata_encrypted) && $row->metadata_encrypted !== '') {
            $decryptedMetadata = $this->encryption->decryptArray($row->metadata_encrypted, 'chat.message.metadata');

            if ($decryptedMetadata !== null) {
                $updates['metadata'] = json_encode($decryptedMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $updates['metadata_encrypted'] = null;
            }
        }

        return $updates;
    }

    private function encryptedMailboxMessageUpdates(object $row): array
    {
        $updates = [];

        if ($row->subject_encrypted === null && $row->subject !== null) {
            $updates['subject_encrypted'] = $this->encryption->encryptString((string) $row->subject, 'mail.message.subject');
            $updates['subject'] = null;
        } elseif ($row->subject_encrypted !== null && $row->subject !== null) {
            $updates['subject'] = null;
        }

        if ($row->body_encrypted === null && $row->body !== null) {
            $updates['body_encrypted'] = $this->encryption->encryptString((string) $row->body, 'mail.message.body');
            $updates['body'] = null;
        } elseif ($row->body_encrypted !== null && $row->body !== null) {
            $updates['body'] = null;
        }

        return $updates;
    }

    private function decryptedMailboxMessageUpdates(object $row): array
    {
        $updates = [];

        if ($row->subject !== null && $row->subject_encrypted !== null) {
            $updates['subject_encrypted'] = null;
        } elseif ($row->subject === null && is_string($row->subject_encrypted) && $row->subject_encrypted !== '') {
            $decryptedSubject = $this->encryption->decryptString($row->subject_encrypted, 'mail.message.subject');

            if ($decryptedSubject !== null) {
                $updates['subject'] = $decryptedSubject;
                $updates['subject_encrypted'] = null;
            }
        }

        if ($row->body !== null && $row->body_encrypted !== null) {
            $updates['body_encrypted'] = null;
        } elseif ($row->body === null && is_string($row->body_encrypted) && $row->body_encrypted !== '') {
            $decryptedBody = $this->encryption->decryptString($row->body_encrypted, 'mail.message.body');

            if ($decryptedBody !== null) {
                $updates['body'] = $decryptedBody;
                $updates['body_encrypted'] = null;
            }
        }

        return $updates;
    }

    private function decodeJsonValue(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function runInTenantContext(string $tenantId, callable $callback): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return;
        }

        $this->tenancy->initialize($tenant);

        try {
            $callback();
        } finally {
            $this->endTenantContext();
        }
    }

    private function restoreTenantContext(?string $tenantId): void
    {
        if ($tenantId === null) {
            $this->endTenantContext();

            return;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->endTenantContext();

            return;
        }

        $this->tenancy->initialize($tenant);
    }

    private function endTenantContext(): void
    {
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }
    }
}
