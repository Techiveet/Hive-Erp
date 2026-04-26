<?php

namespace Modules\Core\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;
use Modules\Core\Models\Setting;

class CommunicationEncryptionService
{
    private const PAYLOAD_PREFIX = 'hive-enc:v1:';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->centralSetting('enable_communication_encryption', false);
    }

    public function encryptString(?string $value, string $purpose, array $context = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $nonce = random_bytes($this->nonceBytes());
        $key = $this->deriveKey($purpose);
        $aad = $this->associatedData($purpose, $context);
        $ciphertext = $this->encryptPayload($value, $aad, $nonce, $key);

        return self::PAYLOAD_PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decryptString(?string $payload, string $purpose, array $context = []): ?string
    {
        if ($payload === null) {
            return null;
        }

        if ($payload === '') {
            return '';
        }

        if (! str_starts_with($payload, self::PAYLOAD_PREFIX)) {
            return $payload;
        }

        $encodedPayload = substr($payload, strlen(self::PAYLOAD_PREFIX));
        $decodedPayload = base64_decode($encodedPayload, true);

        if ($decodedPayload === false || strlen($decodedPayload) < $this->nonceBytes()) {
            throw new RuntimeException('The encrypted communication payload is malformed.');
        }

        $nonce = substr($decodedPayload, 0, $this->nonceBytes());
        $ciphertext = substr($decodedPayload, $this->nonceBytes());
        $key = $this->deriveKey($purpose);
        $aad = $this->associatedData($purpose, $context);
        $plaintext = $this->decryptPayload($ciphertext, $aad, $nonce, $key);

        if (! is_string($plaintext)) {
            throw new RuntimeException('The encrypted communication payload could not be decrypted.');
        }

        return $plaintext;
    }

    public function encryptArray(?array $value, string $purpose, array $context = []): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encryptString(
            json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $purpose,
            $context
        );
    }

    public function decryptArray(?string $payload, string $purpose, array $context = []): ?array
    {
        $plaintext = $this->decryptString($payload, $purpose, $context);

        if ($plaintext === null) {
            return null;
        }

        if ($plaintext === '') {
            return [];
        }

        $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    private function associatedData(string $purpose, array $context = []): string
    {
        try {
            return json_encode([
                'purpose' => $purpose,
                'context' => $this->normalizeContext($context),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to serialize communication encryption context.', previous: $exception);
        }
    }

    private function normalizeContext(array $context): array
    {
        ksort($context);

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->normalizeContext($value);
            }
        }

        return $context;
    }

    private function deriveKey(string $purpose): string
    {
        $appKey = (string) $this->config->get('app.key', '');

        if ($appKey === '') {
            throw new RuntimeException('Application key is missing. Communication encryption cannot be initialized.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('Application key is invalid. Communication encryption cannot be initialized.');
            }

            $appKey = $decoded;
        }

        return hash_hkdf('sha256', $appKey, $this->keyBytes(), 'hive.communication.' . $purpose, '');
    }

    private function encryptPayload(string $plaintext, string $aad, string $nonce, string $key): string
    {
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
        }

        if (class_exists(\ParagonIE_Sodium_Compat::class)) {
            return \ParagonIE_Sodium_Compat::crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
        }

        throw new RuntimeException('Sodium support is unavailable for communication encryption.');
    }

    private function decryptPayload(string $ciphertext, string $aad, string $nonce, string $key): string|false
    {
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $key);
        }

        if (class_exists(\ParagonIE_Sodium_Compat::class)) {
            return \ParagonIE_Sodium_Compat::crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $key);
        }

        throw new RuntimeException('Sodium support is unavailable for communication encryption.');
    }

    private function keyBytes(): int
    {
        return defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')
            ? (int) constant('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')
            : 32;
    }

    private function nonceBytes(): int
    {
        return defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES')
            ? (int) constant('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES')
            : 24;
    }

    private function centralSetting(string $key, mixed $default = null): mixed
    {
        try {
            $centralConnection = (string) $this->config->get('tenancy.database.central_connection', $this->config->get('database.default'));

            if ($centralConnection === '' || ! Schema::connection($centralConnection)->hasTable('settings')) {
                return $default;
            }

            $settings = Cache::rememberForever('global_system_settings:central', function () use ($centralConnection) {
                return DB::connection($centralConnection)
                    ->table((new Setting())->getTable())
                    ->pluck('value', 'key')
                    ->toArray();
            });
        } catch (\Throwable) {
            return $default;
        }

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }
}
