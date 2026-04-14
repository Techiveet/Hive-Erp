<?php

namespace Modules\Core\Support\Localization;

class AuthDictionary
{
    public static function getTranslations(string $locale): array
    {
        $translations = [
            'en' => [
                'auth' => [
                    'login' => [
                        'establishing_uplink' => 'ESTABLISHING UPLINK...',
                        'command' => 'Command',
                        'access' => 'Access',
                        'system_identifier' => 'System Identifier',
                        'encryption_key' => 'Encryption Key',
                        'forgot_key' => 'Forgot Key?',
                        'initiate_handshake' => 'Initiate Handshake',
                        'verifying' => 'VERIFYING...',
                        'tenant_gateway' => 'Tenant Node Gateway',
                        'master_gateway' => 'Master Cluster Gateway',
                        'identifier_placeholder' => 'user@hive.corp',
                        'welcome_desc' => 'Authenticate your identity to decrypt your management workspace.',
                        'central_portal' => 'HIVE.OS CENTRAL',
                        'node_label' => 'NODE',
                        'success' => 'Sign In',
                        'failed' => 'These credentials do not match our records.',
                    ],
                    'logout' => 'Sign Out',
                ],
            ],
            'am' => [
                'auth' => [
                    'login' => [
                        'establishing_uplink' => 'ግንኙነት በመፍጠር ላይ...',
                        'command' => 'ትዕዛዝ',
                        'access' => 'መዳረሻ',
                        'system_identifier' => 'የስርዓት መለያ',
                        'encryption_key' => 'የምስጠራ ቁልፍ',
                        'forgot_key' => 'ቁልፉን ረስተዋል?',
                        'initiate_handshake' => 'ግንኙነት ጀምር',
                        'verifying' => 'በማረጋገጥ ላይ...',
                        'tenant_gateway' => 'የተከራይ ኖድ መግቢያ',
                        'master_gateway' => 'የማዕከላዊ ክላስተር መግቢያ',
                        'identifier_placeholder' => 'user@hive.corp',
                        'welcome_desc' => 'የአመራር የስራ ቦታዎን ለመክፈት ማንነትዎን ያረጋግጡ።',
                        'central_portal' => 'HIVE.OS ማዕከላዊ',
                        'node_label' => 'ኖድ',
                        'success' => 'ይግቡ',
                        'failed' => 'እነዚህ ምስክርነቶች ከመዝገቦቻችን ጋር አይዛመዱም።',
                    ],
                    'logout' => 'ይውጡ',
                ],
            ],
        ];

        return $translations[$locale] ?? [];
    }
}
