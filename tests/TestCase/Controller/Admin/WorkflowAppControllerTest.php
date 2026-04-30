<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\WorkflowAppController
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowAppControllerTest extends IntegrationTestCase
{
    /**
     * Without a configured Workflow.adminAccess gate, the backend must fail
     * closed (403). The test bootstrap installs a permissive default; we
     * delete it for this test only.
     */
    public function testAdminAccessGateUnconfiguredYields403(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::delete('Workflow.adminAccess');

        $this->expectException(ForbiddenException::class);
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * A non-Closure value (e.g. someone setting a string by mistake) is
     * treated as unconfigured.
     */
    public function testAdminAccessGateNonClosureYields403(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::write('Workflow.adminAccess', 'not a closure');

        $this->expectException(ForbiddenException::class);
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * A gate that returns false rejects the request.
     */
    public function testAdminAccessGateFalseYields403(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::write('Workflow.adminAccess', fn () => false);

        $this->expectException(ForbiddenException::class);
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * Truthy non-bool returns are rejected (no coercion).
     */
    public function testAdminAccessGateRequiresStrictTrue(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::write('Workflow.adminAccess', fn () => 1);

        $this->expectException(ForbiddenException::class);
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * A throwing gate is converted to 403 (the original exception is logged,
     * the client sees a generic forbidden).
     */
    public function testAdminAccessGateThrowingYields403(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::write('Workflow.adminAccess', function (): bool {
            throw new RuntimeException('oops');
        });

        $this->expectException(ForbiddenException::class);
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * A gate that itself throws ForbiddenException is respected as-is.
     */
    public function testAdminAccessGateForbiddenPassthrough(): void
    {
        $this->disableErrorHandlerMiddleware();
        Configure::write('Workflow.adminAccess', function (): bool {
            throw new ForbiddenException('nope');
        });

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('nope');
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);
    }

    /**
     * The gate receives the request, so closures can inspect path/identity/etc.
     */
    public function testAdminAccessGateReceivesRequest(): void
    {
        $received = null;
        Configure::write('Workflow.adminAccess', function ($request) use (&$received): bool {
            $received = $request;

            return true;
        });

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
        $this->assertNotNull($received);
        $this->assertStringContainsString('workflow', $received->getPath());
    }
}
