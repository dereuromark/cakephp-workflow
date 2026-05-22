<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowStampCommandTest extends DatabaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private Definition $definition;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->definition = $this->createDefinition();
        Configure::write(
            'Workflow.registry',
            new WorkflowRegistry($this->loaderFor($this->definition), new EventManager()),
        );
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
        parent::tearDown();
    }

    public function testStampBackfillsNullVersionsOnly(): void
    {
        $this->insertOrder('pending', null);
        $this->insertOrder('paid', null);
        $this->insertOrder('shipped', 'keepme1');
        $hash = $this->definition->getVersionHash();

        $this->exec('workflow stamp order');

        $this->assertExitSuccess();
        $versions = $this->orderVersionsByState();
        $this->assertSame($hash, $versions['pending']);
        $this->assertSame($hash, $versions['paid']);
        $this->assertSame('keepme1', $versions['shipped']);
    }

    public function testStampAllReStampsEveryRecord(): void
    {
        $this->insertOrder('pending', null);
        $this->insertOrder('shipped', 'outdated');
        $hash = $this->definition->getVersionHash();

        $this->exec('workflow stamp order --all');

        $this->assertExitSuccess();
        $versions = $this->orderVersionsByState();
        $this->assertSame($hash, $versions['pending']);
        $this->assertSame($hash, $versions['shipped']);
    }

    public function testStampDryRunMakesNoChanges(): void
    {
        $this->insertOrder('pending', null);

        $this->exec('workflow stamp order --dry-run');

        $this->assertExitSuccess();
        $versions = $this->orderVersionsByState();
        $this->assertNull($versions['pending']);
    }

    public function testStampUsesBehaviorConfiguredVersionField(): void
    {
        ConnectionManager::get('test')->execute(
            'INSERT INTO orders (state, workflow_version, alt_version) VALUES (?, ?, ?)',
            ['pending', null, null],
        );
        $hash = $this->definition->getVersionHash();

        $this->getTableLocator()->get('Orders')->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => Configure::read('Workflow.registry'),
            'versioning' => true,
            'versionField' => 'alt_version',
        ]);

        // No --version-field passed: the command should pick up the behavior's field.
        $this->exec('workflow stamp order');

        $this->assertExitSuccess();
        $row = ConnectionManager::get('test')
            ->execute('SELECT workflow_version, alt_version FROM orders')
            ->fetch('assoc');
        $this->assertSame($hash, $row['alt_version']);
        $this->assertNull($row['workflow_version']);
    }

    private function createDefinition(): Definition
    {
        return new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('shipped'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('ship', ['paid'], 'shipped'),
                new Transition('complete', ['shipped'], 'completed'),
            ],
        );
    }

    private function loaderFor(Definition $definition): LoaderInterface
    {
        return new class ($definition) implements LoaderInterface {
            public function __construct(private Definition $definition)
            {
            }

            public function supports(string $workflowName): bool
            {
                return $workflowName === $this->definition->getName();
            }

            public function load(string $workflowName): Definition
            {
                return $this->definition;
            }

            public function getWorkflowNames(): array
            {
                return [$this->definition->getName()];
            }
        };
    }

    private function insertOrder(string $state, ?string $version): void
    {
        ConnectionManager::get('test')->execute(
            'INSERT INTO orders (state, workflow_version) VALUES (?, ?)',
            [$state, $version],
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function orderVersionsByState(): array
    {
        $rows = ConnectionManager::get('test')
            ->execute('SELECT state, workflow_version FROM orders')
            ->fetchAll('assoc');

        $byState = [];
        foreach ($rows as $row) {
            $byState[$row['state']] = $row['workflow_version'];
        }

        return $byState;
    }
}
