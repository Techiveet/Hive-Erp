<?php

namespace Modules\MailBox\Services;

use Illuminate\Support\Facades\DB;
use Modules\MailBox\Models\MailParticipant;
use Modules\Subscription\Support\TenantModuleCatalog;

class MailboxStorageTracker
{
    /**
     * Calculate the storage used by a specific user in bytes.
     */
    public static function getUserStorageUsedBytes(int $userId): int
    {
        // Calculate the absolute byte length of the body and subject for all messages owned by the user.
        // Assuming UTF-8, LENGTH() in MySQL returns bytes string length.
        $result = MailParticipant::where('user_id', $userId)
            ->join('mail_messages', 'mail_participants.mail_message_id', '=', 'mail_messages.id')
            ->select(DB::raw('SUM(COALESCE(LENGTH(mail_messages.body), LENGTH(mail_messages.body_encrypted), 0) + COALESCE(LENGTH(mail_messages.subject), LENGTH(mail_messages.subject_encrypted), 0)) as total_bytes'))
            ->first();

        // Plus roughly 500 bytes per message to account for metadata overhead (headers, sender details)
        $messageCount = MailParticipant::where('user_id', $userId)->count();

        $bytes = (int) ($result->total_bytes ?? 0);
        
        return $bytes + ($messageCount * 500);
    }

    /**
     * Retrieve the active user quota limit in bytes.
     */
    public static function getUserQuotaLimitBytes(): int
    {
        if (function_exists('tenant') && tenant('id')) {
            // Tenant Users pull from the tenant-admin configured per-user quota
            // If the Tenant Admin never set one, default to 1024 MB (1 GB)
            $mbLimit = get_system_setting('mail_storage_quota_tenant_users', 1024);
        } else {
            // Central Users pull from the Central Admin configured central user quota
            $mbLimit = get_system_setting('mail_storage_quota_central_users', 1024);
        }

        // Convert MB to Bytes
        return ((int) $mbLimit) * 1024 * 1024;
    }

    /**
     * Calculate total storage footprint of the active tenant.
     */
    public static function getTenantTotalStorageUsedBytes(): int
    {
        if (!function_exists('tenant') || !tenant('id')) {
            return 0;
        }

        $result = MailParticipant::join('mail_messages', 'mail_participants.mail_message_id', '=', 'mail_messages.id')
            ->select(DB::raw('SUM(COALESCE(LENGTH(mail_messages.body), LENGTH(mail_messages.body_encrypted), 0) + COALESCE(LENGTH(mail_messages.subject), LENGTH(mail_messages.subject_encrypted), 0)) as total_bytes'))
            ->first();

        $messageCount = MailParticipant::count();
        $bytes = (int) ($result->total_bytes ?? 0);
        
        return $bytes + ($messageCount * 500);
    }

    /**
     * Retrieve the global tenant absolute capacity constraint.
     *
     * Priority:
     *  1. Manual override: Central Admin sets 'mail_storage_quota_tenant_PLANNAME' (e.g. mail_storage_quota_tenant_business)
     *  2. Plan-based default:  Derived from TenantModuleCatalog::mailStorageQuotaForPlan()
     *  3. Hard fallback: 5120 MB (5 GB)
     */
    public static function getTenantGlobalQuotaLimitBytes(): int
    {
        if (!function_exists('tenant') || !tenant('id') || !function_exists('tenancy')) {
            return 0;
        }

        $tenantId = tenant('id');

        // Cross-DB: read plan + manual override from the central node
        [$plan, $manualOverrideMb] = tenancy()->central(function () use ($tenantId) {
            // Load the tenant's plan
            $tenantRow = DB::table('tenants')->where('id', $tenantId)->first();
            $plan = $tenantRow->plan ?? 'business';

            // Check for a per-plan manual override key first, then the legacy generic key
            $overrideKey = 'mail_storage_quota_tenant_' . strtolower($plan);
            $override = get_system_setting($overrideKey)
                     ?? get_system_setting('mail_storage_quota_tenant_default');

            return [$plan, $override];
        });

        if ($manualOverrideMb !== null && (int) $manualOverrideMb > 0) {
            return ((int) $manualOverrideMb) * 1024 * 1024;
        }

        // Derive from plan definition
        $mbLimit = TenantModuleCatalog::mailStorageQuotaForPlan($plan);

        return $mbLimit * 1024 * 1024;
    }

    /**
     * Validate if the user can accept an incoming mail of a specific byte size.
     */
    public static function canAcceptMail(int $userId, int $incomingBytes = 0): bool
    {
        // 1. Check Personal Quota
        $userLimitBytes = self::getUserQuotaLimitBytes();
        if ($userLimitBytes > 0) {
            $usedBytes = self::getUserStorageUsedBytes($userId);
            if (($usedBytes + $incomingBytes) > $userLimitBytes) {
                return false;
            }
        }

        // 2. Check Global Tenant Quota Hierarchical Overlap
        if (function_exists('tenant') && tenant('id')) {
            $tenantLimitBytes = self::getTenantGlobalQuotaLimitBytes();
            if ($tenantLimitBytes > 0) {
                $tenantUsedBytes = self::getTenantTotalStorageUsedBytes();
                if (($tenantUsedBytes + $incomingBytes) > $tenantLimitBytes) {
                    return false;
                }
            }
        }

        return true;
    }
}
