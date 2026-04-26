<?php

namespace Modules\Core\Support;

use JsonException;

trait EncryptsCommunicationAttributes
{
    protected function getEncryptedCommunicationStringValue(
        mixed $plaintextValue,
        mixed $encryptedValue,
        string $purpose,
    ): ?string {
        if (is_string($encryptedValue) && $encryptedValue !== '') {
            try {
                return $this->communicationEncryptionService()->decryptString($encryptedValue, $purpose);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $plaintextValue === null ? null : (string) $plaintextValue;
    }

    protected function getEncryptedCommunicationArrayValue(
        mixed $plaintextValue,
        mixed $encryptedValue,
        string $purpose,
    ): ?array {
        if (is_string($encryptedValue) && $encryptedValue !== '') {
            try {
                return $this->communicationEncryptionService()->decryptArray($encryptedValue, $purpose);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if (is_array($plaintextValue)) {
            return $plaintextValue;
        }

        if (! is_string($plaintextValue) || trim($plaintextValue) === '') {
            return null;
        }

        try {
            $decoded = json_decode($plaintextValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            report($exception);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function setEncryptedCommunicationStringValue(
        string $plaintextField,
        string $encryptedField,
        mixed $value,
        string $purpose,
    ): void {
        if ($value === null) {
            $this->attributes[$plaintextField] = null;
            $this->attributes[$encryptedField] = null;

            return;
        }

        $normalizedValue = (string) $value;

        if ($this->communicationEncryptionService()->isEnabled()) {
            $this->attributes[$plaintextField] = null;
            $this->attributes[$encryptedField] = $this->communicationEncryptionService()->encryptString($normalizedValue, $purpose);

            return;
        }

        $this->attributes[$plaintextField] = $normalizedValue;
        $this->attributes[$encryptedField] = null;
    }

    protected function setEncryptedCommunicationArrayValue(
        string $plaintextField,
        string $encryptedField,
        mixed $value,
        string $purpose,
    ): void {
        if ($value === null) {
            $this->attributes[$plaintextField] = null;
            $this->attributes[$encryptedField] = null;

            return;
        }

        $normalizedValue = is_array($value)
            ? $value
            : (is_string($value) ? $this->decodeCommunicationJsonValue($value) : null);

        if ($normalizedValue === null) {
            $this->attributes[$plaintextField] = null;
            $this->attributes[$encryptedField] = null;

            return;
        }

        if ($this->communicationEncryptionService()->isEnabled()) {
            $this->attributes[$plaintextField] = null;
            $this->attributes[$encryptedField] = $this->communicationEncryptionService()->encryptArray($normalizedValue, $purpose);

            return;
        }

        $this->attributes[$plaintextField] = json_encode($normalizedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->attributes[$encryptedField] = null;
    }

    private function decodeCommunicationJsonValue(string $value): ?array
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            report($exception);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function communicationEncryptionService(): CommunicationEncryptionService
    {
        return app(CommunicationEncryptionService::class);
    }
}
