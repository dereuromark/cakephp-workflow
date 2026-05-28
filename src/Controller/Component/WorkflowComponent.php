<?php

declare(strict_types=1);

namespace Workflow\Controller\Component;

use Cake\Controller\Component;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\ORM\Table;
use InvalidArgumentException;
use RuntimeException;
use Workflow\Engine\TransitionResult;
use Workflow\Model\Behavior\WorkflowBehavior;

/**
 * WorkflowComponent
 *
 * Provides controller helpers for handling workflow transitions with
 * standardized flash messages and redirects.
 *
 * The table passed to methods must have WorkflowBehavior attached.
 *
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class WorkflowComponent extends Component
{
    /**
     * @var array
     */
    protected array $components = ['Flash'];

    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'messages' => [
            'success' => "Transition '{transition}' applied successfully.",
            'blocked' => 'Transition blocked: {reasons}',
            'error' => 'Transition failed: {error}',
        ],
        'flashKey' => null,
    ];

    /**
     * Handle a transition request with standard flash messages.
     *
     * Applies the transition and sets appropriate flash messages.
     * Returns the TransitionResult for further inspection if needed.
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table The table with WorkflowBehavior attached
     * @param \Cake\Datasource\EntityInterface $entity The entity to transition
     * @param string $transition The transition name
     * @param array<string, mixed> $context Additional context for the transition
     * @param array<string, mixed> $options Options: 'messages' to override flash messages
     *
     * @return \Workflow\Engine\TransitionResult
     */
    public function applyTransition(
        Table $table,
        EntityInterface $entity,
        string $transition,
        array $context = [],
        array $options = [],
    ): TransitionResult {
        $result = $this->behavior($table)->applyTransition($entity, $transition, $context);

        $this->flashResult($result, $transition, $options);

        return $result;
    }

    /**
     * Handle a transition request from form data.
     *
     * Convenience method that extracts transition name and reason from request data,
     * applies the transition, sets flash messages, and returns a redirect response.
     *
     * Request data keys:
     * - 'transition': The transition name (required)
     * - 'reason': Optional reason for the transition
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table The table with WorkflowBehavior attached
     * @param \Cake\Datasource\EntityInterface $entity The entity to transition
     * @param array|string $redirect Redirect URL after transition
     * @param array<string, mixed> $options Options for transition and flash messages
     *
     * @throws \RuntimeException When redirect generation fails
     *
     * @return \Cake\Http\Response
     */
    public function handleTransition(
        Table $table,
        EntityInterface $entity,
        array|string $redirect,
        array $options = [],
    ): Response {
        $request = $this->getController()->getRequest();
        $transition = trim((string)$request->getData('transition'));
        $reason = $request->getData('reason');

        if ($transition === '') {
            $message = (string)($options['missingTransitionMessage'] ?? 'Transition request is missing the required "transition" field.');
            $flashKey = $options['flashKey'] ?? $this->getConfig('flashKey');
            $this->Flash->error($message, $flashKey ? ['key' => $flashKey] : []);

            $response = $this->getController()->redirect($redirect);
            if ($response === null) {
                throw new RuntimeException('Redirect failed to return a response');
            }

            return $response;
        }

        $context = $options['context'] ?? [];
        if ($reason) {
            $context['reason'] = $reason;
        }

        $this->applyTransition($table, $entity, $transition, $context, $options);

        $response = $this->getController()->redirect($redirect);
        if ($response === null) {
            throw new RuntimeException('Redirect failed to return a response');
        }

        return $response;
    }

    /**
     * Set flash messages based on transition result.
     *
     * @param \Workflow\Engine\TransitionResult $result The transition result
     * @param string $transition The transition name (for message interpolation)
     * @param array<string, mixed> $options Options: 'messages' to override defaults
     *
     * @return void
     */
    public function flashResult(
        TransitionResult $result,
        string $transition,
        array $options = [],
    ): void {
        $messages = array_merge(
            $this->getConfig('messages'),
            $options['messages'] ?? [],
        );
        $flashKey = $options['flashKey'] ?? $this->getConfig('flashKey');

        if ($result->isSuccess()) {
            $message = str_replace('{transition}', $transition, $messages['success']);
            $this->Flash->success($message, $flashKey ? ['key' => $flashKey] : []);

            return;
        }

        if ($result->isBlocked()) {
            $reasons = implode(', ', $result->getBlockedBy());
            $message = str_replace('{reasons}', $reasons, $messages['blocked']);
            $this->Flash->warning($message, $flashKey ? ['key' => $flashKey] : []);

            return;
        }

        $error = $result->getError()?->getMessage() ?? 'Unknown error';
        $message = str_replace('{error}', $error, $messages['error']);
        $this->Flash->error($message, $flashKey ? ['key' => $flashKey] : []);
    }

    /**
     * Assert that the table has the Workflow behavior attached and return it.
     *
     * @param \Cake\ORM\Table $table Table with WorkflowBehavior attached
     *
     * @throws \InvalidArgumentException
     */
    private function behavior(Table $table): WorkflowBehavior
    {
        if (!$table->behaviors()->has('Workflow')) {
            throw new InvalidArgumentException(
                sprintf('Table `%s` must have WorkflowBehavior attached', $table->getAlias()),
            );
        }

        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $table->getBehavior('Workflow');

        return $behavior;
    }
}
