<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\Core\Configure;
use Cake\ORM\Entity;
use Closure;
use ReflectionFunction;
use Workflow\Service\TransitionLogger;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $entity_table
 * @property int|string $entity_id
 * @property string $transition_name
 * @property string $from_state
 * @property string $to_state
 * @property string $status
 * @property string|null $user_id
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property string|null $idempotency_key
 * @property string|null $workflow_version
 * @property \Cake\I18n\DateTime $created
 */
class WorkflowTransition extends Entity
{
    protected array $_accessible = [
        'workflow_name' => true,
        'entity_table' => true,
        'entity_id' => true,
        'transition_name' => true,
        'from_state' => true,
        'to_state' => true,
        'status' => true,
        'user_id' => true,
        'reason' => true,
        'context' => true,
        'idempotency_key' => true,
        'workflow_version' => true,
        'created' => true,
    ];

    /**
     * Check if this was a successful transition.
     */
    public function isSuccess(): bool
    {
        return $this->status === TransitionLogger::STATUS_SUCCESS;
    }

    /**
     * Check if this transition was blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status === TransitionLogger::STATUS_BLOCKED;
    }

    /**
     * Check if this transition was locked out.
     */
    public function isLocked(): bool
    {
        return $this->status === TransitionLogger::STATUS_LOCKED;
    }

    /**
     * Check if this transition had an error.
     */
    public function isError(): bool
    {
        return $this->status === TransitionLogger::STATUS_ERROR;
    }

    /**
     * Get blocked reasons if this was a blocked transition.
     *
     * @return array<string, string>
     */
    public function getBlockedBy(): array
    {
        return $this->context['_blocked_by'] ?? [];
    }

    /**
     * Get error details if this was an error transition.
     *
     * @return array{message?: string|null, class?: string|null, file?: string|null, line?: int|null}|null
     */
    public function getErrorDetails(): ?array
    {
        return $this->context['_error'] ?? null;
    }

    /**
     * Get runtime metadata from context.
     *
     * @return array{guards_evaluated?: array<string>, commands_executed?: array<string>, used_lock?: bool}|null
     */
    public function getRuntime(): ?array
    {
        return $this->context['_runtime'] ?? null;
    }

    /**
     * Get guards that were evaluated during this transition.
     *
     * @return array<string>
     */
    public function getGuardsEvaluated(): array
    {
        return $this->context['_runtime']['guards_evaluated'] ?? [];
    }

    /**
     * Get commands that were executed during this transition.
     *
     * @return array<string>
     */
    public function getCommandsExecuted(): array
    {
        return $this->context['_runtime']['commands_executed'] ?? [];
    }

    /**
     * Check if a lock was used during this transition.
     */
    public function usedLock(): bool
    {
        return $this->context['_runtime']['used_lock'] ?? false;
    }

    /**
     * Check if this transition was triggered by an admin operation.
     */
    public function isAdminAction(): bool
    {
        $context = $this->context ?? [];

        return (bool)($context['admin_action'] ?? false);
    }

    /**
     * Get the recorded client IP, if available.
     */
    public function getClientIp(): ?string
    {
        $context = $this->context ?? [];
        $clientIp = $context['client_ip'] ?? null;

        return is_string($clientIp) && $clientIp !== '' ? $clientIp : null;
    }

    /**
     * Get a display label for the transition actor.
     */
    public function getActorLabel(): ?string
    {
        $actorMeta = $this->resolveActorMeta();

        return $actorMeta['label'] ?? $this->user_id;
    }

    /**
     * Get an optional URL for the transition actor.
     *
     * @return array<string, mixed>|string|null
     */
    public function getActorUrl(): array|string|null
    {
        $actorMeta = $this->resolveActorMeta();

        return $actorMeta['url'] ?? null;
    }

    /**
     * Backfill stringified JSON contexts from older releases.
     *
     * @param mixed $value
     *
     * @return array<string, mixed>|null
     */
    protected function _getContext(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve actor metadata from optional application configuration.
     *
     * @return array{label?: string, url?: array<string, mixed>|string|null}|null
     */
    protected function resolveActorMeta(): ?array
    {
        if (!$this->user_id) {
            return null;
        }

        $resolver = Configure::read('Workflow.adminActorResolver');
        if (!($resolver instanceof Closure)) {
            return null;
        }

        $resolved = $this->invokeActorResolver($resolver);
        if (is_string($resolved) && $resolved !== '') {
            return ['label' => $resolved];
        }

        if (!is_array($resolved)) {
            return null;
        }

        $label = $resolved['label'] ?? null;
        $url = $resolved['url'] ?? null;
        if (!is_string($label) || $label === '') {
            return null;
        }

        if ($url !== null && !is_string($url) && !is_array($url)) {
            $url = null;
        }

        return [
            'label' => $label,
            'url' => $url,
        ];
    }

    /**
     * Invoke the actor resolver while remaining compatible with 1-arg callbacks.
     *
     * @return array<string, mixed>|string|null
     */
    protected function invokeActorResolver(Closure $resolver): array|string|null
    {
        $reflection = new ReflectionFunction($resolver);
        if ($reflection->getNumberOfParameters() < 2) {
            return $resolver($this->user_id);
        }

        return $resolver($this->user_id, $this);
    }
}
