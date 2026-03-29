<?php

declare(strict_types=1);

namespace Workflow\Loader;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Workflow\Attribute\Color;
use Workflow\Attribute\Command as CommandAttr;
use Workflow\Attribute\FailedState;
use Workflow\Attribute\FinalState;
use Workflow\Attribute\Flag;
use Workflow\Attribute\Guard as GuardAttr;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Label;
use Workflow\Attribute\OnEnter;
use Workflow\Attribute\OnExit;
use Workflow\Attribute\RequireReason;
use Workflow\Attribute\StateMachine;
use Workflow\Attribute\Timeout as TimeoutAttr;
use Workflow\Attribute\Transition as TransitionAttr;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;
use Workflow\State\AbstractState;

class AttributeLoader implements LoaderInterface
{
    /**
     * @var array<string, \Workflow\Engine\Definition\Definition>
     */
    private array $definitions = [];

    /**
     * @var array<string, array{baseClass: class-string, stateClasses: array<class-string>, smAttr: \Workflow\Attribute\StateMachine}>
     */
    private array $discovered = [];

    private bool $scanned = false;

    /**
     * @param array<string> $namespaces Namespaces to scan for state classes
     * @param array<string, string> $pathMap Namespace prefix to path mapping (e.g., ['App\\' => APP])
     */
    public function __construct(
        private array $namespaces,
        private array $pathMap = [],
    ) {
    }

    public function supports(string $workflowName): bool
    {
        $this->ensureScanned();

        return isset($this->discovered[$workflowName]);
    }

    /**
     * Load a workflow definition by name.
     *
     * @param string $workflowName
     *
     * @throws \Workflow\Exception\WorkflowException When workflow is not found
     *
     * @return \Workflow\Engine\Definition\Definition
     */
    public function load(string $workflowName): Definition
    {
        if (isset($this->definitions[$workflowName])) {
            return $this->definitions[$workflowName];
        }

        $this->ensureScanned();

        if (!isset($this->discovered[$workflowName])) {
            throw new WorkflowException("Workflow '{$workflowName}' not found");
        }

        $info = $this->discovered[$workflowName];
        $this->definitions[$workflowName] = $this->buildDefinition($workflowName, $info);

        return $this->definitions[$workflowName];
    }

    /**
     * @return array<string>
     */
    public function getWorkflowNames(): array
    {
        $this->ensureScanned();

        return array_keys($this->discovered);
    }

    private function ensureScanned(): void
    {
        if ($this->scanned) {
            return;
        }

        foreach ($this->namespaces as $namespace) {
            $this->scanNamespace($namespace);
        }

        $this->scanned = true;
    }

    private function scanNamespace(string $namespace): void
    {
        $baseDir = $this->namespaceToPath($namespace);
        if (!is_dir($baseDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir),
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->fileToClassName($file->getPathname(), $namespace, $baseDir);
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if (!$reflection->isSubclassOf(AbstractState::class)) {
                continue;
            }

            $smAttr = $this->findStateMachineAttribute($reflection);
            if ($smAttr === null) {
                continue;
            }

            $workflowName = $smAttr->name;

            if (!isset($this->discovered[$workflowName])) {
                $this->discovered[$workflowName] = [
                    'baseClass' => $this->findBaseStateClass($reflection),
                    'stateClasses' => [],
                    'smAttr' => $smAttr,
                ];
            }

            if (!$reflection->isAbstract()) {
                $this->discovered[$workflowName]['stateClasses'][] = $className;
            }
        }
    }

    private function namespaceToPath(string $namespace): string
    {
        $namespace = trim($namespace, '\\');

        // Check custom path mappings first
        foreach ($this->pathMap as $prefix => $basePath) {
            $prefix = trim($prefix, '\\');
            if (str_starts_with($namespace, $prefix . '\\') || $namespace === $prefix) {
                $relative = substr($namespace, strlen($prefix));
                $relative = ltrim($relative, '\\');

                return rtrim($basePath, DS) . DS . str_replace('\\', DS, $relative);
            }
        }

        // Default mappings for test environment
        if (defined('TESTS') && str_starts_with($namespace, 'Workflow\\Test\\TestApp\\')) {
            $relative = substr($namespace, strlen('Workflow\\Test\\TestApp\\'));

            return TESTS . 'test_app' . DS . str_replace('\\', DS, $relative);
        }

        // Default App namespace mapping
        if (defined('APP') && str_starts_with($namespace, 'App\\')) {
            $relative = substr($namespace, strlen('App\\'));

            return APP . str_replace('\\', DS, $relative);
        }

        return '';
    }

    private function fileToClassName(string $filepath, string $baseNamespace, string $baseDir): string
    {
        $relative = str_replace($baseDir, '', $filepath);
        $relative = str_replace([DS, '.php'], ['\\', ''], $relative);
        $relative = ltrim($relative, '\\');

        return rtrim($baseNamespace, '\\') . '\\' . $relative;
    }

    private function findStateMachineAttribute(ReflectionClass $reflection): ?StateMachine
    {
        $attrs = $reflection->getAttributes(StateMachine::class);
        if ($attrs) {
            return $attrs[0]->newInstance();
        }

        $parent = $reflection->getParentClass();
        if ($parent && $parent->getName() !== AbstractState::class) {
            return $this->findStateMachineAttribute($parent);
        }

        return null;
    }

    /**
     * @return class-string
     */
    private function findBaseStateClass(ReflectionClass $reflection): string
    {
        $parent = $reflection->getParentClass();
        if (!$parent || $parent->getName() === AbstractState::class) {
            return $reflection->getName();
        }

        return $this->findBaseStateClass($parent);
    }

    /**
     * @param string $name
     * @param array{baseClass: class-string, stateClasses: array<class-string>, smAttr: \Workflow\Attribute\StateMachine} $info
     */
    private function buildDefinition(string $name, array $info): Definition
    {
        $smAttr = $info['smAttr'];
        $states = [];
        $transitions = [];

        foreach ($info['stateClasses'] as $stateClass) {
            $reflection = new ReflectionClass($stateClass);
            $stateName = $stateClass::getStateName();

            $states[] = $this->buildState($reflection, $stateName);

            $stateTransitions = $this->buildTransitions($reflection, $stateName, $info['stateClasses']);
            $transitions = array_merge($transitions, $stateTransitions);
        }

        $transitions = $this->mergeTransitions($transitions);

        return new Definition(
            name: $name,
            table: $smAttr->table,
            field: $smAttr->field,
            states: $states,
            transitions: $transitions,
            version: $smAttr->version,
        );
    }

    private function buildState(ReflectionClass $reflection, string $stateName): State
    {
        $isInitial = (bool)$reflection->getAttributes(InitialState::class);
        $isFinal = (bool)$reflection->getAttributes(FinalState::class);
        $isFailed = (bool)$reflection->getAttributes(FailedState::class);

        $colorAttr = $reflection->getAttributes(Color::class);
        $color = $colorAttr ? $colorAttr[0]->newInstance()->color : null;

        $labelAttr = $reflection->getAttributes(Label::class);
        $label = $labelAttr ? $labelAttr[0]->newInstance()->label : null;

        $flagAttrs = $reflection->getAttributes(Flag::class);
        $flags = array_map(fn ($attr) => $attr->newInstance()->name, $flagAttrs);

        // Collect OnEnter/OnExit method names
        $onEnter = [];
        $onExit = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(OnEnter::class)) {
                $onEnter[] = $reflection->getName() . '::' . $method->getName();
            }
            if ($method->getAttributes(OnExit::class)) {
                $onExit[] = $reflection->getName() . '::' . $method->getName();
            }
        }

        // Get RequireReason attribute
        $requireReasonAttr = $reflection->getAttributes(RequireReason::class);
        $requireReasonFor = $requireReasonAttr ? $requireReasonAttr[0]->newInstance()->for : [];

        $timeouts = array_map(
            fn ($attr) => new StateTimeout(
                $attr->newInstance()->after,
                $attr->newInstance()->transition,
            ),
            $reflection->getAttributes(TimeoutAttr::class),
        );

        return new State(
            name: $stateName,
            label: $label,
            color: $color,
            initial: $isInitial,
            final: $isFinal,
            failed: $isFailed,
            flags: $flags,
            onEnter: $onEnter,
            onExit: $onExit,
            requireReasonFor: $requireReasonFor,
            timeouts: $timeouts,
        );
    }

    /**
     * @param \ReflectionClass $reflection
     * @param string $fromState
     * @param array<class-string> $allStateClasses
     *
     * @return array<\Workflow\Engine\Definition\Transition>
     */
    private function buildTransitions(ReflectionClass $reflection, string $fromState, array $allStateClasses): array
    {
        $transitions = [];
        $transitionAttrs = $reflection->getAttributes(TransitionAttr::class);

        $guards = [];
        $commands = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(GuardAttr::class) as $guardAttr) {
                $guard = $guardAttr->newInstance();
                $guards[$guard->transition][] = $reflection->getName() . '::' . $method->getName();
            }
            foreach ($method->getAttributes(CommandAttr::class) as $cmdAttr) {
                $cmd = $cmdAttr->newInstance();
                $commands[$cmd->transition][] = $reflection->getName() . '::' . $method->getName();
            }
        }

        foreach ($transitionAttrs as $attr) {
            $transitionDef = $attr->newInstance();
            $toStateName = $this->classToStateName($transitionDef->to, $allStateClasses);

            $transitions[] = new Transition(
                name: $transitionDef->name,
                from: [$fromState],
                to: $toStateName,
                happy: $transitionDef->happy,
                guards: $guards[$transitionDef->name] ?? [],
                commands: $commands[$transitionDef->name] ?? [],
            );
        }

        return $transitions;
    }

    /**
     * @param string $targetClass
     * @param array<class-string> $allStateClasses
     *
     * @throws \Workflow\Exception\WorkflowException
     * @throws \Workflow\Exception\WorkflowException
     */
    private function classToStateName(string $targetClass, array $allStateClasses): string
    {
        if (!in_array($targetClass, $allStateClasses, true)) {
            throw new WorkflowException("Target state class '{$targetClass}' not found");
        }

        return $targetClass::getStateName();
    }

    /**
     * @param array<\Workflow\Engine\Definition\Transition> $transitions
     *
     * @return array<\Workflow\Engine\Definition\Transition>
     */
    private function mergeTransitions(array $transitions): array
    {
        $merged = [];

        foreach ($transitions as $transition) {
            $name = $transition->getName();
            if (!isset($merged[$name])) {
                $merged[$name] = $transition;
            } else {
                $existing = $merged[$name];
                $merged[$name] = new Transition(
                    name: $name,
                    from: array_unique(array_merge($existing->getFrom(), $transition->getFrom())),
                    to: $transition->getTo(),
                    happy: $existing->isHappy() || $transition->isHappy(),
                    guards: array_unique(array_merge($existing->getGuards(), $transition->getGuards())),
                    commands: array_unique(array_merge($existing->getCommands(), $transition->getCommands())),
                );
            }
        }

        return array_values($merged);
    }
}
