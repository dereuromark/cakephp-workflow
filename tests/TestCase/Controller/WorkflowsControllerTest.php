<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller;

/**
 * @uses \Workflow\Controller\WorkflowsController
 */
class WorkflowsControllerTest extends IntegrationTestCase
{
    public function testDrawSvg(): void
    {
        if (trim((string)shell_exec('command -v dot 2>/dev/null')) === '') {
            $this->markTestSkipped('GraphViz `dot` binary not available.');
        }

        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => 'svg', 'currentState' => 'paid'],
        ]);

        $this->assertResponseOk();
        $this->assertContentType('image/svg+xml');
        $this->assertResponseContains('<svg');
        $this->assertResponseContains('>paid</text>');
    }

    public function testDrawPng(): void
    {
        if (trim((string)shell_exec('command -v dot 2>/dev/null')) === '') {
            $this->markTestSkipped('GraphViz `dot` binary not available.');
        }

        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => 'png', 'currentState' => 'paid'],
        ]);

        $this->assertResponseOk();
        $this->assertContentType('image/png');
        $this->assertSame("\x89PNG", substr((string)$this->_response->getBody(), 0, 4));
    }

    public function testDrawMmd(): void
    {
        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => 'mmd', 'currentState' => 'paid'],
        ]);

        $this->assertResponseOk();
        $this->assertContentType('text/plain');
        $this->assertResponseContains('flowchart TD');
        $this->assertResponseContains('class paid current');
    }
    public function testDrawRejectsArrayFormat(): void
    {
        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => ['mmd']],
        ]);
        $this->assertResponseCode(400);
    }

    public function testDrawRejectsArrayEntityId(): void
    {
        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => 'mmd', 'id' => [1]],
        ]);
        $this->assertResponseCode(400);
    }

    public function testDrawRejectsArrayState(): void
    {
        $this->get([
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'draw',
            'order',
            '?' => ['format' => 'mmd', 'currentState' => ['paid']],
        ]);
        $this->assertResponseCode(400);
    }

}
