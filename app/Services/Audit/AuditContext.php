<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditOrigin;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Immutable execution context attached to one or more audit records.
 *
 * The context never guesses an actor: a system operation has no human causer,
 * and an automated operation must be declared explicitly by the caller.
 */
final readonly class AuditContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public AuditOrigin $origin,
        public ?Model $actor = null,
        public ?string $correlationId = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {
        if ($origin === AuditOrigin::User && $actor === null) {
            throw new InvalidArgumentException('A user audit context requires an actor.');
        }

        if ($origin !== AuditOrigin::User && $actor !== null) {
            throw new InvalidArgumentException('A system or automated audit context cannot have a human actor.');
        }

        if ($correlationId !== null && trim($correlationId) === '') {
            throw new InvalidArgumentException('Audit correlation id cannot be empty.');
        }

        if ($reason !== null && trim($reason) === '') {
            throw new InvalidArgumentException('Audit reason cannot be empty.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function user(
        Model $actor,
        ?string $correlationId = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        return new self(
            origin: AuditOrigin::User,
            actor: $actor,
            correlationId: $correlationId,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function system(
        ?string $correlationId = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        return new self(
            origin: AuditOrigin::System,
            correlationId: $correlationId,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function automated(
        ?string $correlationId = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        return new self(
            origin: AuditOrigin::Automated,
            correlationId: $correlationId,
            reason: $reason,
            metadata: $metadata,
        );
    }

    public function withCorrelationId(string $correlationId): self
    {
        return new self(
            origin: $this->origin,
            actor: $this->actor,
            correlationId: $correlationId,
            reason: $this->reason,
            metadata: $this->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            origin: $this->origin,
            actor: $this->actor,
            correlationId: $this->correlationId,
            reason: $this->reason,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
