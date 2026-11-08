<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Business facade for audit operations.
 *
 * Responsibilities:
 * - resolve and isolate the current AuditContext;
 * - provide temporary contexts that are restored even on exception;
 * - delegate the actual write pipeline to the AuditLogger contract.
 *
 * It never sanitizes or persists directly: those responsibilities stay in the
 * logger and sanitizer.
 */
final class AuditManager
{
    private ?AuditContext $context = null;

    public function __construct(
        private readonly AuditLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        AuditEventType $event,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?AuditContext $context = null,
    ): AuditResult {
        return $this->logger->record(
            event: $event,
            subject: $subject,
            oldValues: $oldValues,
            newValues: $newValues,
            context: $this->resolveContext($context),
        );
    }

    public function currentContext(): AuditContext
    {
        return $this->context ??= $this->defaultContext();
    }

    /**
     * Run a callback with a temporary context and restore the previous one in
     * every case, including exceptions.
     */
    public function withContext(AuditContext $context, Closure $callback): mixed
    {
        $previous = $this->context;
        $this->context = $context->correlationId === null
            ? $context->withCorrelationId((string) Str::uuid())
            : $context;

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }

    /**
     * Explicitly drop the current context. Scoped container bindings already
     * reset between requests/jobs; this method covers long-running processes.
     */
    public function resetContext(): void
    {
        $this->context = null;
    }

    private function resolveContext(?AuditContext $context): AuditContext
    {
        $context ??= $this->currentContext();

        return $context->correlationId === null
            ? $context->withCorrelationId((string) Str::uuid())
            : $context;
    }

    private function defaultContext(): AuditContext
    {
        $actor = Auth::user();
        $metadata = [
            'source' => app()->runningInConsole() ? 'console' : 'http',
        ];

        if ($actor instanceof Model) {
            return AuditContext::user(
                actor: $actor,
                correlationId: (string) Str::uuid(),
                metadata: $metadata,
            );
        }

        return AuditContext::system(
            correlationId: (string) Str::uuid(),
            metadata: $metadata,
        );
    }
}
