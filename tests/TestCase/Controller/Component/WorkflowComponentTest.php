<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TestApp\Model\Table\TraitOrdersTable;
use Workflow\Controller\Component\WorkflowComponent;

class WorkflowComponentTest extends TestCase
{
    private Controller $controller;

    private WorkflowComponent $component;

    private object $flashRecorder;

    protected function setUp(): void
    {
        parent::setUp();

        $request = new ServerRequest([
            'url' => '/orders/transition/1',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);
        $request = $request->withAttribute('session', new Session([
            'defaults' => 'php',
        ]));
        $this->controller = new Controller($request);
        $registry = new ComponentRegistry($this->controller);
        $registry->load('Flash');
        $this->component = new class ($registry) extends WorkflowComponent {
            public object $Flash;
        };
        $this->flashRecorder = new class {
            /**
             * @var array<int, array{method: string, message: string, options: array<string, mixed>}>
             */
            public array $calls = [];

            /**
             * @param string $name
             * @param array<int, mixed> $args
             */
            public function __call(string $name, array $args): void
            {
                $this->calls[] = [
                    'method' => $name,
                    'message' => (string)($args[0] ?? ''),
                    'options' => (array)($args[1] ?? []),
                ];
            }
        };
        $this->component->Flash = $this->flashRecorder;
    }

    public function testApplyTransitionRejectsTableWithoutWorkflowBehavior(): void
    {
        $table = new Table();
        $table->setAlias('Orders');
        $entity = $table->newEmptyEntity();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table `Orders` must have WorkflowBehavior attached');

        $this->component->applyTransition($table, $entity, 'pay');
    }

    public function testHandleTransitionRedirectsWithFlashErrorWhenTransitionIsMissing(): void
    {
        $table = $this->createWorkflowTable();
        $entity = new Entity(['state' => 'pending']);

        $response = $this->component->handleTransition($table, $entity, '/orders/view/1');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/orders/view/1', $response->getHeaderLine('Location'));
        $this->assertSame([
            [
                'method' => 'error',
                'message' => 'Transition request is missing the required "transition" field.',
                'options' => [],
            ],
        ], $this->flashRecorder->calls);
    }

    public function testHandleTransitionUsesCustomMissingTransitionMessage(): void
    {
        $table = $this->createWorkflowTable();
        $entity = new Entity(['state' => 'pending']);

        $response = $this->component->handleTransition($table, $entity, '/orders/view/1', [
            'missingTransitionMessage' => 'Please choose a transition first.',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/orders/view/1', $response->getHeaderLine('Location'));
        $this->assertSame([
            [
                'method' => 'error',
                'message' => 'Please choose a transition first.',
                'options' => [],
            ],
        ], $this->flashRecorder->calls);
    }

    private function createWorkflowTable(): TraitOrdersTable
    {
        return new TraitOrdersTable();
    }
}
