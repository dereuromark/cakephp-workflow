<?php

declare(strict_types=1);

namespace Workflow\Engine\Definition;

use Workflow\Exception\WorkflowException;

final class Definition
{
    /**
     * @var array<string, \Workflow\Engine\Definition\State>
     */
    private array $stateMap = [];

    /**
     * @param string $name
     * @param string $table
     * @param string $field
     * @param array<\Workflow\Engine\Definition\State> $states
     * @param array<\Workflow\Engine\Definition\Transition> $transitions
     * @param string|null $description
     * @param string|null $label
     * @param int $version Workflow version number (increment when making breaking changes)
     */
    public function __construct(
        private string $name,
        private string $table,
        private string $field,
        private array $states,
        private array $transitions,
        private ?string $label = null,
        private ?string $description = null,
        private int $version = 1,
    ) {
        foreach ($states as $state) {
            $this->stateMap[$state->getName()] = $state;
        }
    }

    /**
     * Get the workflow version number.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<\Workflow\Engine\Definition\State>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @return array<\Workflow\Engine\Definition\Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function hasState(string $name): bool
    {
        return isset($this->stateMap[$name]);
    }

    /**
     * Get a state by name.
     *
     * @param string $name
     *
     * @throws \Workflow\Exception\WorkflowException When state is not found
     *
     * @return \Workflow\Engine\Definition\State
     */
    public function getState(string $name): State
    {
        if (!isset($this->stateMap[$name])) {
            throw new WorkflowException("State '{$name}' not found in workflow '{$this->name}'");
        }

        return $this->stateMap[$name];
    }

    /**
     * Find a state by name without throwing.
     *
     * @param string $name
     *
     * @return \Workflow\Engine\Definition\State|null Null when the state is not defined
     */
    public function findState(string $name): ?State
    {
        return $this->stateMap[$name] ?? null;
    }

    /**
     * Resolve a state by name, returning a synthetic "unknown" state when it is not
     * defined instead of throwing.
     *
     * Use this for reading/displaying an entity's current state, which may have been
     * orphaned by a definition change. Use {@see self::getState()} where the state is
     * required to exist (e.g. a transition's target).
     *
     * @param string $name
     *
     * @return \Workflow\Engine\Definition\State
     */
    public function resolveState(string $name): State
    {
        return $this->stateMap[$name] ?? State::unknown($name);
    }

    /**
     * Get the initial state of the workflow.
     *
     * @throws \Workflow\Exception\WorkflowException When no initial state is defined
     *
     * @return \Workflow\Engine\Definition\State
     */
    public function getInitialState(): State
    {
        foreach ($this->states as $state) {
            if ($state->isInitial()) {
                return $state;
            }
        }

        throw new WorkflowException("No initial state defined for workflow '{$this->name}'");
    }

    /**
     * @return array<\Workflow\Engine\Definition\State>
     */
    public function getFinalStates(): array
    {
        return array_filter($this->states, fn (State $state) => $state->isFinal());
    }

    /**
     * @return array<\Workflow\Engine\Definition\Transition>
     */
    public function getTransitionsFromState(string $stateName): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn (Transition $t) => $t->isAllowedFrom($stateName),
        ));
    }

    /**
     * Get automatic (event-less) transitions from a state.
     *
     * These are transitions that happen automatically when an entity
     * enters a state, used for conditional branching.
     *
     * @return array<\Workflow\Engine\Definition\Transition>
     */
    public function getAutomaticTransitionsFromState(string $stateName): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn (Transition $t) => $t->isAllowedFrom($stateName) && $t->isAutomatic(),
        ));
    }

    /**
     * Check if a state has automatic transitions.
     */
    public function hasAutomaticTransitions(string $stateName): bool
    {
        return count($this->getAutomaticTransitionsFromState($stateName)) > 0;
    }

    /**
     * Whether a state is a "stuck" automatic branch: it has more than one automatic transition,
     * all of them conditional (no unconditional fallback), and the automatic branch is its sole
     * exit - no manual transition and no transition a timeout could fire. Such a state can leave
     * an item stuck when no condition matches.
     *
     * A single conditional automatic transition (an "advance when ready, otherwise wait" step) and
     * a branch that also has a non-automatic exit are not considered stuck.
     */
    public function isStuckAutomaticBranchState(string $stateName): bool
    {
        $state = $this->findState($stateName);
        if ($state === null || $state->isFinal() || $state->isFailed()) {
            return false;
        }

        $autoTransitions = $this->getAutomaticTransitionsFromState($stateName);
        if (count($autoTransitions) <= 1) {
            return false;
        }

        // A non-automatic transition (manual, or one a timeout fires) is a real exit.
        if (count($this->getTransitionsFromState($stateName)) > count($autoTransitions)) {
            return false;
        }

        foreach ($autoTransitions as $transition) {
            if ($transition->getCondition() === null) {
                return false; // has an unconditional fallback
            }
        }

        return true;
    }

    /**
     * Names of all states that are stuck automatic branches.
     *
     * @return array<string>
     */
    public function getStuckAutomaticBranchStates(): array
    {
        $states = [];
        foreach ($this->states as $state) {
            if ($this->isStuckAutomaticBranchState($state->getName())) {
                $states[] = $state->getName();
            }
        }

        return $states;
    }

    /**
     * Get a transition by name.
     *
     * @param string $name
     *
     * @throws \Workflow\Exception\WorkflowException When transition is not found
     *
     * @return \Workflow\Engine\Definition\Transition
     */
    public function getTransition(string $name): Transition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->getName() === $name) {
                return $transition;
            }
        }

        throw new WorkflowException("Transition '{$name}' not found in workflow '{$this->name}'");
    }

    /**
     * @return array<\Workflow\Engine\Definition\State>
     */
    public function getStatesWithFlag(string $flag): array
    {
        return array_values(array_filter(
            $this->states,
            fn (State $state) => $state->hasFlag($flag),
        ));
    }

    public function getVersionHash(): string
    {
        $data = [
            'states' => array_map(fn (State $s) => $s->getName(), $this->states),
            'transitions' => array_map(
                fn (Transition $t) => [$t->getName(), $t->getFrom(), $t->getTo()],
                $this->transitions,
            ),
        ];

        $json = json_encode($data);
        if ($json === false) {
            // Fallback to serialization if JSON encoding fails
            $json = serialize($data);
        }

        return substr(md5($json), 0, 8);
    }
}
