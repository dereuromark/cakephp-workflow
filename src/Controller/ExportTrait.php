<?php

declare(strict_types=1);

namespace Workflow\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use RuntimeException;
use Workflow\Engine\Definition\Definition;
use Workflow\Renderer\GraphvizRenderer;
use Workflow\Renderer\MermaidRenderer;
use Workflow\Service\WorkflowRegistry;

trait ExportTrait
{
    protected ?WorkflowRegistry $workflowRegistry = null;

    public function draw(string $name): Response
    {
        if (!$this->workflowRegistry instanceof WorkflowRegistry) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $format = strtolower(trim((string)$this->request->getQuery('format', 'svg')));
        if (!in_array($format, ['svg', 'png', 'mmd'], true)) {
            throw new BadRequestException('Unsupported workflow export format.');
        }

        $currentState = $this->resolveRequestedState($definition);
        $showDetails = filter_var($this->request->getQuery('showDetails', true), FILTER_VALIDATE_BOOLEAN);
        $detailMarkers = (string)$this->request->getQuery('detailMarkers', 'ascii');
        if (!in_array($detailMarkers, ['emoji', 'ascii', 'none'], true)) {
            $detailMarkers = 'ascii';
        }

        if ($format === 'mmd') {
            $renderer = new MermaidRenderer();

            return $this->response
                ->withType('text/plain')
                ->withStringBody($renderer->render($definition, $currentState, $showDetails, $detailMarkers));
        }

        $renderer = new GraphvizRenderer();
        $body = $renderer->render($definition, $currentState, $showDetails, $detailMarkers, $format);

        return $this->response
            ->withType($format === 'png' ? 'image/png' : 'image/svg+xml')
            ->withStringBody($body);
    }

    protected function resolveRequestedState(Definition $definition): ?string
    {
        $id = $this->request->getQuery('id');
        if ($id !== null && $id !== '') {
            $entity = $this->fetchTable($definition->getTable())->get($id);
            $state = $entity->get($definition->getField());

            return $state !== null && $state !== '' ? (string)$state : null;
        }

        $currentState = (string)$this->request->getQuery('currentState', (string)$this->request->getQuery('state', ''));

        return $currentState !== '' ? $currentState : null;
    }
}
