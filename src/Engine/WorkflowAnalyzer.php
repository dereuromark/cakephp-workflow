<?php

declare(strict_types=1);

namespace Workflow\Engine;

use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;

/**
 * Analyzes workflow definitions for potential issues.
 */
class WorkflowAnalyzer
{
    /**
     * @var array<array{type: string, severity: string, message: string, context: array<string, mixed>}>
     */
    private array $issues = [];

    /**
     * Analyze a workflow definition and return all issues found.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     *
     * @return array<array{type: string, severity: string, message: string, context: array<string, mixed>}>
     */
    public function analyze(Definition $definition): array
    {
        $this->issues = [];

        $this->checkInitialState($definition);
        $this->checkFinalStates($definition);
        $this->checkUnreachableStates($definition);
        $this->checkDeadEndStates($definition);
        $this->checkTransitionTargets($definition);
        $this->checkOutgoingTransitionsFromFinalStates($definition);
        $this->checkDuplicateTransitions($definition);
        $this->checkHappyPath($definition);

        return $this->issues;
    }

    /**
     * Get issues grouped by severity.
     *
     * @return array{errors: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, warnings: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, info: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>}
     */
    public function getIssuesBySeverity(): array
    {
        return [
            'errors' => array_filter($this->issues, fn ($i) => $i['severity'] === 'error'),
            'warnings' => array_filter($this->issues, fn ($i) => $i['severity'] === 'warning'),
            'info' => array_filter($this->issues, fn ($i) => $i['severity'] === 'info'),
        ];
    }

    /**
     * Check if workflow has exactly one initial state.
     */
    private function checkInitialState(Definition $definition): void
    {
        $initialStates = array_filter(
            $definition->getStates(),
            fn (State $s) => $s->isInitial(),
        );

        if (count($initialStates) === 0) {
            $this->addIssue('missing_initial', 'error', 'No initial state defined');
        } elseif (count($initialStates) > 1) {
            $names = array_map(fn (State $s) => $s->getName(), $initialStates);
            $this->addIssue(
                'multiple_initial',
                'error',
                'Multiple initial states defined: ' . implode(', ', $names),
                ['states' => $names],
            );
        }
    }

    /**
     * Check if workflow has at least one final state.
     */
    private function checkFinalStates(Definition $definition): void
    {
        $finalStates = $definition->getFinalStates();

        if (count($finalStates) === 0) {
            $this->addIssue(
                'no_final_states',
                'warning',
                'No final states defined - workflow items may never complete',
            );
        }
    }

    /**
     * Check for states that cannot be reached from the initial state.
     */
    private function checkUnreachableStates(Definition $definition): void
    {
        try {
            $initialState = $definition->getInitialState();
        } catch (Throwable) {
            return; // Already reported as error
        }

        $reachable = $this->findReachableStates($definition, $initialState->getName());
        $allStates = array_map(fn (State $s) => $s->getName(), $definition->getStates());
        $unreachable = array_diff($allStates, $reachable);

        foreach ($unreachable as $stateName) {
            $this->addIssue(
                'unreachable_state',
                'warning',
                "State '{$stateName}' is not reachable from the initial state",
                ['state' => $stateName],
            );
        }
    }

    /**
     * Check for non-final states with no outgoing transitions.
     */
    private function checkDeadEndStates(Definition $definition): void
    {
        foreach ($definition->getStates() as $state) {
            if ($state->isFinal()) {
                continue;
            }

            $transitions = $definition->getTransitionsFromState($state->getName());
            if (count($transitions) === 0) {
                $this->addIssue(
                    'dead_end_state',
                    'error',
                    "Non-final state '{$state->getName()}' has no outgoing transitions",
                    ['state' => $state->getName()],
                );
            }
        }
    }

    /**
     * Check that all transitions reference valid states.
     */
    private function checkTransitionTargets(Definition $definition): void
    {
        foreach ($definition->getTransitions() as $transition) {
            // Check 'to' state
            if (!$definition->hasState($transition->getTo())) {
                $this->addIssue(
                    'invalid_target',
                    'error',
                    "Transition '{$transition->getName()}' targets non-existent state '{$transition->getTo()}'",
                    ['transition' => $transition->getName(), 'target' => $transition->getTo()],
                );
            }

            // Check 'from' states
            foreach ($transition->getFrom() as $from) {
                if (!$definition->hasState($from)) {
                    $this->addIssue(
                        'invalid_source',
                        'error',
                        "Transition '{$transition->getName()}' references non-existent source state '{$from}'",
                        ['transition' => $transition->getName(), 'source' => $from],
                    );
                }
            }
        }
    }

    /**
     * Check for transitions leaving terminal states.
     *
     * Final and failed states are treated as terminal by the engine, so
     * declaring outgoing transitions from them creates unreachable paths.
     */
    private function checkOutgoingTransitionsFromFinalStates(Definition $definition): void
    {
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFrom() as $from) {
                if (!$definition->hasState($from)) {
                    continue;
                }

                $state = $definition->getState($from);
                if (!$state->isFinal()) {
                    continue;
                }

                $kind = $state->isFailed() ? 'failed' : 'final';
                $this->addIssue(
                    'terminal_state_outgoing_transition',
                    'error',
                    "Transition '{$transition->getName()}' starts from {$kind} state '{$from}', but terminal states cannot have outgoing transitions",
                    [
                        'transition' => $transition->getName(),
                        'state' => $from,
                        'stateType' => $kind,
                    ],
                );
            }
        }
    }

    /**
     * Check for duplicate transitions (same from/to pair).
     */
    private function checkDuplicateTransitions(Definition $definition): void
    {
        $seen = [];
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFrom() as $from) {
                $key = $from . '->' . $transition->getTo();
                if (isset($seen[$key])) {
                    $this->addIssue(
                        'duplicate_transition',
                        'info',
                        "Multiple transitions from '{$from}' to '{$transition->getTo()}': {$seen[$key]}, {$transition->getName()}",
                        ['from' => $from, 'to' => $transition->getTo()],
                    );
                } else {
                    $seen[$key] = $transition->getName();
                }
            }
        }
    }

    /**
     * Check if happy path leads to a final state.
     */
    private function checkHappyPath(Definition $definition): void
    {
        $happyTransitions = array_filter(
            $definition->getTransitions(),
            fn (Transition $t) => $t->isHappy(),
        );

        if (count($happyTransitions) === 0) {
            $this->addIssue(
                'no_happy_path',
                'info',
                'No happy path defined (no transitions marked as happy)',
            );

            return;
        }

        // Check if happy path is continuous from initial to final
        try {
            $current = $definition->getInitialState()->getName();
        } catch (Throwable) {
            return;
        }

        $visited = [$current];
        $maxIterations = count($definition->getStates()) + 1;
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $iterations++;
            $foundHappy = false;

            foreach ($happyTransitions as $transition) {
                if ($transition->isAllowedFrom($current)) {
                    $current = $transition->getTo();
                    if (in_array($current, $visited, true)) {
                        $this->addIssue(
                            'happy_path_cycle',
                            'warning',
                            "Happy path contains a cycle at state '{$current}'",
                            ['state' => $current],
                        );

                        return;
                    }
                    $visited[] = $current;
                    $foundHappy = true;

                    break;
                }
            }

            if (!$foundHappy) {
                break;
            }

            // Check if we reached a final state
            if ($definition->getState($current)->isFinal()) {
                return; // Happy path complete
            }
        }

        $state = $definition->getState($current);
        if (!$state->isFinal()) {
            $this->addIssue(
                'incomplete_happy_path',
                'warning',
                "Happy path ends at non-final state '{$current}'",
                ['state' => $current],
            );
        }
    }

    /**
     * Find all states reachable from a given state.
     *
     * @return array<string>
     */
    private function findReachableStates(Definition $definition, string $startState): array
    {
        $reachable = [$startState];
        $queue = [$startState];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($definition->getTransitions() as $transition) {
                if ($transition->isAllowedFrom($current)) {
                    $target = $transition->getTo();
                    if (!in_array($target, $reachable, true)) {
                        $reachable[] = $target;
                        $queue[] = $target;
                    }
                }
            }
        }

        return $reachable;
    }

    /**
     * @param string $type
     * @param string $severity
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function addIssue(string $type, string $severity, string $message, array $context = []): void
    {
        $this->issues[] = [
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
        ];
    }
}
