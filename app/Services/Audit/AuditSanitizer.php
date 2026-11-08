<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditEventType;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonSerializable;
use UnitEnum;

/**
 * Filters every value before it reaches the activity log.
 *
 * Security model:
 * - a model/event allow-list decides which attributes may be logged;
 * - a sensitive-key blacklist is always applied, even to allow-listed fields;
 * - metadata is allow-listed separately;
 * - recursive arrays and JSON-serialisable objects are filtered at every level;
 * - unsupported objects are rejected instead of being serialised blindly.
 */
final class AuditSanitizer
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, attributes: array<string, mixed>}
     */
    public function sanitizeChanges(
        string $subjectType,
        AuditEventType $event,
        array $oldValues,
        array $newValues,
    ): array {
        $allowedFields = $this->allowedFields($subjectType, $event);

        return [
            'old' => $this->sanitizeValues($oldValues, $allowedFields),
            'attributes' => $this->sanitizeValues($newValues, $allowedFields),
        ];
    }

    /**
     * Metadata values are validated by category: controlled technical values,
     * opaque identifiers, or the strict reason allow-list. Invalid values are
     * dropped without being written to a diagnostic log.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function sanitizeMetadata(AuditEventType $event, array $metadata): array
    {
        $sanitized = [];

        foreach ($this->allowedMetadata($event) as $key) {
            if ($key === '' || $this->isSensitiveKey($key)) {
                continue;
            }

            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $this->sanitizeMetadataValue($key, $metadata[$key]);

            if ($value === null) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowedKeys
     * @return array<string, mixed>
     */
    public function sanitizeValues(array $values, array $allowedKeys): array
    {
        $sanitized = [];

        foreach ($allowedKeys as $key) {
            if ($key === '' || $this->isSensitiveKey($key)) {
                continue;
            }

            if (! array_key_exists($key, $values)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($values[$key], 0);
        }

        return $sanitized;
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return true;
        }

        $sensitiveKeys = (array) $this->config->get('audit.sensitive_keys', []);

        if (in_array($normalized, $sensitiveKeys, true)) {
            return true;
        }

        $patterns = (array) $this->config->get('audit.sensitive_patterns', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function allowedFields(string $subjectType, AuditEventType $event): array
    {
        $fields = $this->config->get('audit.fields', []);

        if (! is_array($fields) || ! isset($fields[$subjectType]) || ! is_array($fields[$subjectType])) {
            return [];
        }

        $eventFields = $fields[$subjectType][$event->value] ?? null;

        return is_array($eventFields) ? $this->stringList($eventFields) : [];
    }

    /**
     * @return list<string>
     */
    private function allowedMetadata(AuditEventType $event): array
    {
        $metadata = $this->config->get('audit.metadata', []);

        if (! is_array($metadata)) {
            return [];
        }

        $global = is_array($metadata['*'] ?? null) ? $this->stringList($metadata['*']) : [];
        $eventSpecific = is_array($metadata[$event->value] ?? null) ? $this->stringList($metadata[$event->value]) : [];

        return array_values(array_unique([...$global, ...$eventSpecific]));
    }

    private function sanitizeMetadataValue(string $key, mixed $value): mixed
    {
        $controlledKeys = [
            'source',
            'channel',
            'origin',
            'guard',
            'actor_type',
            'initiated_by',
            'request_id',
            'correlation_id',
            'command',
            'job',
            'role',
            'permission',
            'reason',
        ];

        if (! is_string($value)) {
            // Only the free-form extension keys (for example a nested context)
            // may carry a structure; technical and descriptive keys do not.
            return in_array($key, $controlledKeys, true)
                ? null
                : $this->sanitizeValue($value, 0);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return match ($key) {
            'reason' => $this->allowedReason($value),
            'request_id', 'correlation_id' => $this->matchesConfiguredPattern(
                $value,
                'audit.metadata_identifier_pattern',
            ) ? $value : null,
            'command', 'job', 'role', 'permission' => $this->matchesConfiguredPattern(
                $value,
                'audit.metadata_technical_identifier_pattern',
            ) ? $value : null,
            'source', 'channel', 'origin', 'guard', 'actor_type', 'initiated_by' => $this->allowedControlledValue(
                $key,
                $value,
            ),
            default => $this->sanitizeValue($value, 0),
        };
    }

    private function allowedReason(string $value): ?string
    {
        $maxLength = max(1, (int) $this->config->get('audit.reason_max_length', 200));

        if (mb_strlen($value) > $maxLength) {
            return null;
        }

        $reasons = (array) $this->config->get('audit.reasons', []);

        return in_array($value, $reasons, true) ? $value : null;
    }

    private function allowedControlledValue(string $key, string $value): ?string
    {
        $allowedValues = (array) $this->config->get("audit.metadata_values.{$key}", []);

        return in_array($value, $allowedValues, true) ? $value : null;
    }

    private function matchesConfiguredPattern(string $value, string $configKey): bool
    {
        $pattern = $this->config->get($configKey);

        return is_string($pattern)
            && $pattern !== ''
            && preg_match($pattern, $value) === 1;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($depth > max(1, (int) $this->config->get('audit.max_depth', 8))) {
            throw new InvalidArgumentException('Audit value nesting is too deep.');
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof Model) {
            throw new InvalidArgumentException('Eloquent models cannot be stored as audit values.');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize(), $depth + 1);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && ($key === '' || $this->isSensitiveKey($key))) {
                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($item, $depth + 1);
            }

            return $sanitized;
        }

        throw new InvalidArgumentException('Unsupported audit value type.');
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
