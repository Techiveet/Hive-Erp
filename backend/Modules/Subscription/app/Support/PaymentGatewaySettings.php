<?php

namespace Modules\Subscription\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Models\Setting;

class PaymentGatewaySettings
{
    public const SETTING_KEY = 'subscription_payment_gateways';

    public static function definitions(): array
    {
        return [
            'chapa' => [
                'label' => 'Chapa',
                'description' => 'Hosted checkout through Chapa for ETB payments and card/mobile wallet collection.',
                'implemented' => true,
                'supports_payment_methods' => false,
                'requires_billing_phone' => false,
                'required_fields' => ['secret_key'],
                'defaults' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'base_url' => rtrim((string) env('CHAPA_BASE_URL', 'https://api.chapa.co'), '/'),
                    'public_key' => (string) env('CHAPA_PUBLIC_KEY', ''),
                    'secret_key' => (string) env('CHAPA_SECRET_KEY', ''),
                    'encryption_key' => (string) env('CHAPA_ENCRYPTION_KEY', ''),
                    'webhook_secret' => (string) env('CHAPA_WEBHOOK_SECRET', ''),
                    'logo_url' => (string) env('CHAPA_LOGO_URL', ''),
                ],
                'fields' => [
                    ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'base_url', 'label' => 'API Base URL', 'type' => 'text', 'placeholder' => 'https://api.chapa.co', 'rules' => ['required', 'url']],
                    ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'password', 'placeholder' => 'CHAPUBK_TEST-...', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'placeholder' => 'CHASECK_TEST-...', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'encryption_key', 'label' => 'Encryption Key', 'type' => 'password', 'placeholder' => 'Direct charge encryption key', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'placeholder' => 'random webhook secret', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'logo_url', 'label' => 'Checkout Logo URL', 'type' => 'text', 'placeholder' => 'https://...', 'rules' => ['nullable', 'url', 'max:500']],
                ],
            ],
            'arifpay' => [
                'label' => 'ArifPay',
                'description' => 'Hosted checkout through ArifPay with Ethiopian bank and wallet payment methods.',
                'implemented' => true,
                'supports_payment_methods' => true,
                'requires_billing_phone' => true,
                'required_fields' => ['api_key'],
                'defaults' => [
                    'enabled' => true,
                    'sandbox' => filter_var(env('ARIFPAY_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
                    'base_url' => rtrim((string) env('ARIFPAY_BASE_URL', 'https://gateway.arifpay.net/api'), '/'),
                    'api_key' => (string) env('ARIFPAY_API_KEY', ''),
                    'checkout_expire_minutes' => (int) env('ARIFPAY_CHECKOUT_EXPIRE_MINUTES', 30),
                    'payment_methods' => collect(explode(',', (string) env('ARIFPAY_PAYMENT_METHODS', 'TELEBIRR_USSD,CBE,AWASH_BIRR,AMOLE,ZAMZAM')))
                        ->map(fn (string $value) => strtoupper(trim($value)))
                        ->filter()
                        ->values()
                        ->all(),
                ],
                'fields' => [
                    ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'base_url', 'label' => 'Gateway Base URL', 'type' => 'text', 'placeholder' => 'https://gateway.arifpay.net/api', 'rules' => ['required', 'url']],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'placeholder' => 'arifpay key', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'checkout_expire_minutes', 'label' => 'Checkout Expiry (Minutes)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:5', 'max:180']],
                    ['key' => 'payment_methods', 'label' => 'Allowed Payment Methods', 'type' => 'csv', 'placeholder' => 'TELEBIRR_USSD,CBE,AWASH_BIRR,AMOLE,ZAMZAM', 'rules' => ['nullable', 'array']],
                ],
            ],
            'telebirr' => [
                'label' => 'Telebirr',
                'description' => 'Telebirr direct API scaffold. Credentials can be stored now and the adapter can be completed later.',
                'implemented' => false,
                'supports_payment_methods' => false,
                'requires_billing_phone' => false,
                'required_fields' => ['merchant_app_id', 'fabric_app_id', 'short_code', 'app_secret', 'private_key'],
                'defaults' => [
                    'enabled' => false,
                    'sandbox' => true,
                    'merchant_app_id' => (string) env('TELEBIRR_MERCHANT_APP_ID', env('TELEBIRR_APP_ID', '')),
                    'fabric_app_id' => (string) env('TELEBIRR_FABRIC_APP_ID', ''),
                    'short_code' => (string) env('TELEBIRR_SHORT_CODE', ''),
                    'app_secret' => (string) env('TELEBIRR_APP_SECRET', env('TELEBIRR_APP_KEY', '')),
                    'public_key' => (string) env('TELEBIRR_PUBLIC_KEY', ''),
                    'private_key' => (string) env('TELEBIRR_PRIVATE_KEY', ''),
                ],
                'fields' => [
                    ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'toggle', 'rules' => ['required', 'boolean']],
                    ['key' => 'merchant_app_id', 'label' => 'Merchant AppId', 'type' => 'text', 'placeholder' => 'telebirr merchant app id', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'fabric_app_id', 'label' => 'Fabric App ID', 'type' => 'text', 'placeholder' => 'telebirr fabric app id', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'short_code', 'label' => 'Short Code', 'type' => 'text', 'placeholder' => 'merchant short code', 'rules' => ['nullable', 'string', 'max:100']],
                    ['key' => 'app_secret', 'label' => 'App Secret', 'type' => 'password', 'placeholder' => 'telebirr app secret', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'password', 'placeholder' => 'telebirr public key', 'rules' => ['nullable', 'string', 'max:4000']],
                    ['key' => 'private_key', 'label' => 'Private Key', 'type' => 'password', 'placeholder' => 'telebirr private key', 'rules' => ['nullable', 'string', 'max:8000']],
                ],
            ],
        ];
    }

    public function current(): array
    {
        $stored = json_decode((string) Setting::on($this->centralConnection())
            ->where('key', self::SETTING_KEY)
            ->value('value'), true);
        $providers = [];

        foreach (self::definitions() as $key => $definition) {
            $providers[$key] = $this->decryptSensitiveValues($key, array_merge(
                $definition['defaults'],
                $this->normalizeLegacyConfig($key, is_array($stored['providers'][$key] ?? null) ? $stored['providers'][$key] : [])
            ));
        }

        return [
            'active_provider' => strtolower((string) ($stored['active_provider'] ?? 'chapa')),
            'providers' => $providers,
            'direct_transfer' => $this->normalizeDirectTransferConfig(
                is_array($stored['direct_transfer'] ?? null) ? $stored['direct_transfer'] : []
            ),
        ];
    }

    public function activeProviderKey(): string
    {
        $current = $this->current();
        $requested = strtolower((string) ($current['active_provider'] ?? 'chapa'));

        $config = $current['providers'][$requested] ?? [];

        if (
            isset(self::definitions()[$requested])
            && (self::definitions()[$requested]['implemented'] ?? false)
            && (bool) ($config['enabled'] ?? true)
        ) {
            return $requested;
        }

        $implementedProviders = array_filter(
            self::definitions(),
            fn (array $definition, string $key) => (bool) ($definition['implemented'] ?? false)
                && (bool) (($current['providers'][$key]['enabled'] ?? true)),
            ARRAY_FILTER_USE_BOTH
        );

        return array_key_first($implementedProviders) ?? 'chapa';
    }

    public function providerConfig(string $key): array
    {
        $current = $this->current();

        return $current['providers'][$key] ?? (self::definitions()[$key]['defaults'] ?? []);
    }

    public function definition(string $key): array
    {
        return self::definitions()[$key] ?? [];
    }

    public function configuredFor(string $key, ?array $config = null): bool
    {
        $definition = $this->definition($key);
        $current = $config ?? $this->providerConfig($key);

        foreach ($definition['required_fields'] ?? [] as $field) {
            $value = $current[$field] ?? null;

            if (is_array($value) && $value === []) {
                return false;
            }

            if (!filled($value)) {
                return false;
            }
        }

        return true;
    }

    public function settingsPayload(): array
    {
        $current = $this->current();
        $directTransfer = $current['direct_transfer'] ?? $this->directTransferDefaults();

        return [
            'active_provider' => $this->activeProviderKey(),
            'providers' => collect(self::definitions())
                ->mapWithKeys(function (array $definition, string $key) use ($current) {
                    $config = $current['providers'][$key] ?? $definition['defaults'];

                    return [$key => [
                        'key' => $key,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'implemented' => (bool) $definition['implemented'],
                        'supports_payment_methods' => (bool) $definition['supports_payment_methods'],
                        'requires_billing_phone' => (bool) $definition['requires_billing_phone'],
                        'configured' => $this->configuredFor($key, $config),
                        'fields' => collect($definition['fields'])
                            ->map(fn (array $field) => [
                                ...Arr::except($field, ['rules']),
                                'sensitive' => $this->isSensitiveField($definition, $field['key']),
                            ])
                            ->values()
                            ->all(),
                        'settings' => $this->maskSensitiveValues($key, $config),
                    ]];
                })
                ->all(),
            'direct_transfer' => [
                'enabled' => (bool) ($directTransfer['enabled'] ?? false),
                'configured' => $this->directTransferConfigured($directTransfer),
                'instructions' => (string) ($directTransfer['instructions'] ?? ''),
                'bank_accounts' => $directTransfer['bank_accounts'] ?? [],
            ],
        ];
    }

    public function store(array $payload): array
    {
        $normalized = $this->normalizeForStorage($payload);

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($normalized)]
        );

        clear_system_settings_cache();

        return $normalized;
    }

    public function validationRules(): array
    {
        $rules = [
            'active_provider' => ['required', 'string'],
            'providers' => ['required', 'array'],
            'direct_transfer' => ['required', 'array'],
            'direct_transfer.enabled' => ['required', 'boolean'],
            'direct_transfer.instructions' => ['nullable', 'string', 'max:3000'],
            'direct_transfer.bank_accounts' => ['nullable', 'array'],
            'direct_transfer.bank_accounts.*.id' => ['nullable', 'string', 'max:64'],
            'direct_transfer.bank_accounts.*.label' => ['nullable', 'string', 'max:255'],
            'direct_transfer.bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'direct_transfer.bank_accounts.*.account_name' => ['nullable', 'string', 'max:255'],
            'direct_transfer.bank_accounts.*.account_number' => ['nullable', 'string', 'max:255'],
            'direct_transfer.bank_accounts.*.branch' => ['nullable', 'string', 'max:255'],
            'direct_transfer.bank_accounts.*.notes' => ['nullable', 'string', 'max:1000'],
            'direct_transfer.bank_accounts.*.is_active' => ['required', 'boolean'],
        ];

        foreach (self::definitions() as $providerKey => $definition) {
            $rules["providers.{$providerKey}"] = ['required', 'array'];

            foreach ($definition['fields'] as $field) {
                $rules["providers.{$providerKey}.{$field['key']}"] = $field['rules'];
            }
        }

        return $rules;
    }

    public function directTransferConfig(): array
    {
        return $this->current()['direct_transfer'] ?? $this->directTransferDefaults();
    }

    public function directTransferConfigured(?array $config = null): bool
    {
        $config ??= $this->directTransferConfig();

        if (!(bool) ($config['enabled'] ?? false)) {
            return false;
        }

        return collect($config['bank_accounts'] ?? [])
            ->contains(fn (array $account) => (bool) ($account['is_active'] ?? false));
    }

    public function directTransferPublicPayload(): array
    {
        $config = $this->directTransferConfig();

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'configured' => $this->directTransferConfigured($config),
            'instructions' => (string) ($config['instructions'] ?? ''),
            'bank_accounts' => collect($config['bank_accounts'] ?? [])
                ->filter(fn (array $account) => (bool) ($account['is_active'] ?? false))
                ->values()
                ->all(),
        ];
    }

    public function directTransferAccount(string $id): ?array
    {
        return collect($this->directTransferConfig()['bank_accounts'] ?? [])
            ->first(fn (array $account) => (string) ($account['id'] ?? '') === $id);
    }

    protected function normalizeForStorage(array $payload): array
    {
        $current = $this->current();
        $providers = [];

        foreach (self::definitions() as $providerKey => $definition) {
            $base = $current['providers'][$providerKey] ?? $definition['defaults'];
            $incoming = is_array($payload['providers'][$providerKey] ?? null) ? $payload['providers'][$providerKey] : [];
            $normalized = [];

            foreach ($definition['defaults'] as $fieldKey => $default) {
                $value = array_key_exists($fieldKey, $incoming) ? $incoming[$fieldKey] : ($base[$fieldKey] ?? $default);

                if (is_array($default)) {
                    $normalized[$fieldKey] = collect(Arr::wrap($value))
                        ->map(fn ($item) => trim((string) $item))
                        ->filter()
                        ->values()
                        ->all();
                    continue;
                }

                if (is_bool($default)) {
                    $normalized[$fieldKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }

                if (is_int($default)) {
                    $normalized[$fieldKey] = (int) $value;
                    continue;
                }

                if ($this->isSensitiveField($definition, $fieldKey)) {
                    $incomingValue = trim((string) $value);
                    $existingValue = trim((string) ($base[$fieldKey] ?? $default));

                    $normalized[$fieldKey] = $incomingValue === ''
                        ? $this->encryptSensitiveValue($existingValue)
                        : $this->encryptSensitiveValue($incomingValue);
                    continue;
                }

                $normalized[$fieldKey] = trim((string) $value);
            }

            $providers[$providerKey] = $normalized;
        }

        $activeProvider = strtolower((string) ($payload['active_provider'] ?? $current['active_provider'] ?? 'chapa'));

        if (!isset(self::definitions()[$activeProvider])) {
            $activeProvider = array_key_first(self::definitions()) ?? 'chapa';
        }

        if (!(self::definitions()[$activeProvider]['implemented'] ?? false)) {
            $implementedProviders = array_filter(
                self::definitions(),
                fn (array $definition) => (bool) ($definition['implemented'] ?? false)
            );
            $activeProvider = array_key_first($implementedProviders) ?? 'chapa';
        }

        return [
            'active_provider' => $activeProvider,
            'providers' => $providers,
            'direct_transfer' => $this->normalizeDirectTransferConfig(
                is_array($payload['direct_transfer'] ?? null)
                    ? $payload['direct_transfer']
                    : ($current['direct_transfer'] ?? [])
            ),
        ];
    }

    protected function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }

    protected function normalizeLegacyConfig(string $providerKey, array $config): array
    {
        if ($providerKey === 'arifpay') {
            unset(
                $config['settlement_account_number'],
                $config['settlement_bank_code'],
                $config['beneficiary_account'],
                $config['beneficiary_bank'],
            );

            $methods = collect($config['payment_methods'] ?? [])
                ->map(fn (string $value) => strtoupper(trim($value)))
                ->filter()
                ->values();

            if (
                $methods->count() === 3
                && $methods->contains('TELEBIRR_USSD')
                && $methods->contains('CBE')
                && $methods->contains('CARD')
            ) {
                $config['payment_methods'] = ['TELEBIRR_USSD', 'CBE', 'AWASH_BIRR', 'AMOLE', 'ZAMZAM'];
            }

            return $config;
        }

        if ($providerKey !== 'telebirr') {
            return $config;
        }

        if (!array_key_exists('merchant_app_id', $config) && array_key_exists('app_id', $config)) {
            $config['merchant_app_id'] = $config['app_id'];
        }

        if (!array_key_exists('app_secret', $config) && array_key_exists('app_key', $config)) {
            $config['app_secret'] = $config['app_key'];
        }

        return $config;
    }

    protected function isSensitiveField(array $definition, string $fieldKey): bool
    {
        $field = collect($definition['fields'] ?? [])->firstWhere('key', $fieldKey);

        return ($field['type'] ?? null) === 'password';
    }

    protected function decryptSensitiveValues(string $providerKey, array $config): array
    {
        $definition = $this->definition($providerKey);

        foreach (array_keys($config) as $fieldKey) {
            if (!$this->isSensitiveField($definition, $fieldKey)) {
                continue;
            }

            $config[$fieldKey] = $this->decryptSensitiveValue((string) ($config[$fieldKey] ?? ''));
        }

        return $config;
    }

    protected function maskSensitiveValues(string $providerKey, array $config): array
    {
        $definition = $this->definition($providerKey);

        foreach (array_keys($config) as $fieldKey) {
            if ($this->isSensitiveField($definition, $fieldKey)) {
                $config[$fieldKey] = '';
            }
        }

        return $config;
    }

    protected function encryptSensitiveValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($this->looksEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    protected function decryptSensitiveValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!$this->looksEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function looksEncrypted(string $value): bool
    {
        $decoded = json_decode(base64_decode($value, true) ?: '', true);

        return is_array($decoded) && isset($decoded['iv'], $decoded['value'], $decoded['mac']);
    }

    protected function directTransferDefaults(): array
    {
        return [
            'enabled' => false,
            'instructions' => 'Transfer the full amount to one of the accounts below, then submit the exact transaction reference for manual verification.',
            'bank_accounts' => [],
        ];
    }

    protected function normalizeDirectTransferConfig(array $config): array
    {
        $defaults = $this->directTransferDefaults();

        return [
            'enabled' => filter_var($config['enabled'] ?? $defaults['enabled'], FILTER_VALIDATE_BOOLEAN),
            'instructions' => trim((string) ($config['instructions'] ?? $defaults['instructions'])),
            'bank_accounts' => collect($config['bank_accounts'] ?? [])
                ->filter(fn ($account) => is_array($account))
                ->map(function (array $account) {
                    return [
                        'id' => trim((string) ($account['id'] ?? '')) ?: (string) Str::ulid(),
                        'label' => trim((string) ($account['label'] ?? '')),
                        'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                        'account_name' => trim((string) ($account['account_name'] ?? '')),
                        'account_number' => trim((string) ($account['account_number'] ?? '')),
                        'branch' => trim((string) ($account['branch'] ?? '')),
                        'notes' => trim((string) ($account['notes'] ?? '')),
                        'is_active' => filter_var($account['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ];
                })
                ->filter(function (array $account) {
                    return $account['label'] !== ''
                        && $account['bank_name'] !== ''
                        && $account['account_name'] !== ''
                        && $account['account_number'] !== '';
                })
                ->values()
                ->all(),
        ];
    }
}
