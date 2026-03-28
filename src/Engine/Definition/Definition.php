<?php
declare(strict_types=1);

namespace Workflow\Engine\Definition;

use Workflow\Exception\WorkflowException;

final class Definition
{
    /** @var array<string, State> */
    private array $stateMap = [];

    /**
     * @param array<State> $states
     * @param array<Transition> $transitions
     */
    public function __construct(
        private string $name,
        private string $table,
        private string $field,
        private array $states,
        private array $transitions,
        private ?string $label = null,
        private ?string $description = null,
    ) {
        foreach ($states as $state) {
            $this->stateMap[$state->getName()] = $state;
        }
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
     * @return array<State>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @return array<Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function hasState(string $name): bool
    {
        return isset($this->stateMap[$name]);
    }

    public function getState(string $name): State
    {
        if (!isset($this->stateMap[$name])) {
            throw new WorkflowException("State '{$name}' not found in workflow '{$this->name}'");
        }

        return $this->stateMap[$name];
    }

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
     * @return array<State>
     */
    public function getFinalStates(): array
    {
        return array_filter($this->states, fn (State $state) => $state->isFinal());
    }

    /**
     * @return array<Transition>
     */
    public function getTransitionsFromState(string $stateName): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn (Transition $t) => $t->isAllowedFrom($stateName),
        ));
    }

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
     * @return array<State>
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

        return substr(md5((string)json_encode($data)), 0, 8);
    }
}
