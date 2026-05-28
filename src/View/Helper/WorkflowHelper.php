<?php

declare(strict_types=1);

namespace Workflow\View\Helper;

use Cake\Datasource\EntityInterface;
use Cake\View\Helper;
use Workflow\Engine\Definition\Definition;
use Workflow\Renderer\MermaidRenderer;

/**
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\FormHelper $Form
 */
class WorkflowHelper extends Helper
{
    protected array $helpers = ['Html', 'Form'];

    private ?MermaidRenderer $mermaidRenderer = null;

    private int $widgetSequence = 0;

    /**
     * Render a complete, drop-in workflow panel for one entity: the current-state
     * badge plus a POST button (CSRF-protected) for each available transition.
     *
     * ```php
     * echo $this->Workflow->panel($definition, $order, $this->Orders->availableTransitions($order), [
     *     'url' => ['action' => 'transition'],
     * ]);
     * ```
     *
     * The entity id and transition name are appended to `url` automatically, and the
     * `transition` is also POSTed, ready for `WorkflowComponent::handleTransition()`.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string> $transitions Available transition names
     * @param array<string, mixed> $options 'url', 'class', 'buttonClass', 'badge'
     */
    public function panel(
        Definition $definition,
        EntityInterface $entity,
        array $transitions,
        array $options = [],
    ): string {
        $state = (string)$entity->get($definition->getField());
        $badge = $this->stateBadge($definition, $state, $options['badge'] ?? []);
        $buttons = $this->postTransitionButtons($entity, $transitions, $options);
        $class = $options['class'] ?? 'workflow-panel';

        return sprintf(
            '<div class="%s">%s%s</div>',
            h($class),
            $badge,
            $buttons !== '' ? ' ' . $buttons : '',
        );
    }

    /**
     * Render available transitions as CSRF-protected POST buttons.
     *
     * Each button submits the `transition` name to the configured `url`, so it is
     * safe for state changes (unlike the GET links produced by transitionButtons()).
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string> $transitions
     * @param array<string, mixed> $options 'url', 'buttonClass'
     */
    public function postTransitionButtons(
        EntityInterface $entity,
        array $transitions,
        array $options = [],
    ): string {
        if (!$transitions) {
            return '';
        }

        $urlBase = $options['url'] ?? [];
        $buttonClass = $options['buttonClass'] ?? 'btn btn-sm btn-outline-primary';

        $buttons = [];
        foreach ($transitions as $transition) {
            $url = $urlBase + ['action' => 'transition', $entity->get('id'), $transition];
            $buttons[] = $this->Form->postButton(
                ucfirst(str_replace('_', ' ', $transition)),
                $url,
                [
                    'class' => $buttonClass,
                    'data-transition' => $transition,
                    'data' => ['transition' => $transition],
                ],
            );
        }

        return implode(' ', $buttons);
    }

    /**
     * Render a Mermaid diagram for a workflow.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string, mixed> $options Supported keys: id, class, currentState, showDetails
     */
    public function diagram(Definition $definition, array $options = []): string
    {
        $data = $this->diagramData($definition, $options);
        $mode = (string)($options['mode'] ?? 'diagram');

        if ($mode === 'code') {
            $class = $options['codeClass'] ?? 'workflow-code';

            return sprintf(
                '<pre class="%s"><code>%s</code></pre>',
                h($class),
                h($data['mermaid']),
            );
        }

        return sprintf(
            '<div id="%s" class="%s">%s</div>',
            h((string)$data['id']),
            h((string)($options['class'] ?? 'mermaid')),
            $data['mermaid'],
        );
    }

    /**
     * Build normalized diagram payload for app embedding.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string, mixed> $options Supported keys: id, currentState, showDetails, detailMarkers
     *
     * @return array{id: string, mermaid: string, currentState: string|null, currentStateLabel: string|null}
     */
    public function diagramData(Definition $definition, array $options = []): array
    {
        $currentState = isset($options['currentState']) ? (string)$options['currentState'] : null;
        if ($currentState === '') {
            $currentState = null;
        }

        return [
            'id' => (string)($options['id'] ?? 'workflow-diagram-' . $definition->getName()),
            'mermaid' => $this->getMermaidRenderer()->render(
                $definition,
                $currentState,
                (bool)($options['showDetails'] ?? false),
                (string)($options['detailMarkers'] ?? 'emoji'),
            ),
            'currentState' => $currentState,
            'currentStateLabel' => $currentState !== null ? $definition->resolveState($currentState)->getDisplayName() : null,
        ];
    }

    /**
     * Render available transitions as buttons.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string> $transitions
     * @param array<string, mixed> $options
     */
    public function transitionButtons(
        EntityInterface $entity,
        array $transitions,
        array $options = [],
    ): string {
        if (!$transitions) {
            return '';
        }

        $urlBase = $options['url'] ?? [];
        $buttonClass = $options['buttonClass'] ?? 'btn btn-sm btn-outline-primary';

        $buttons = [];
        foreach ($transitions as $transition) {
            $url = $urlBase + ['action' => 'transition', $entity->get('id'), $transition];
            $buttons[] = $this->Html->link(
                ucfirst($transition),
                $url,
                [
                    'class' => $buttonClass,
                    'data-transition' => $transition,
                ],
            );
        }

        return implode(' ', $buttons);
    }

    /**
     * Render the current state as a badge.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string $state
     * @param array<string, mixed> $options
     */
    public function stateBadge(Definition $definition, string $state, array $options = []): string
    {
        $stateObj = $definition->resolveState($state);
        $color = $stateObj->getColor() ?? '#6c757d';
        $label = $stateObj->getDisplayName();

        $style = sprintf('background-color: %s; color: %s;', $color, $this->getContrastColor($color));
        $class = $options['class'] ?? 'badge';

        return sprintf(
            '<span class="%s" style="%s">%s</span>',
            h($class),
            h($style),
            h($label),
        );
    }

    /**
     * Get the color for a state.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string $state
     */
    public function getStateColor(Definition $definition, string $state): string
    {
        $stateObj = $definition->resolveState($state);

        return $stateObj->getColor() ?? '#6c757d';
    }

    /**
     * Get the raw Mermaid code for a workflow.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string, mixed> $options Supported keys: currentState, showDetails
     */
    public function getMermaidCode(Definition $definition, array $options = []): string
    {
        return $this->diagramData($definition, $options)['mermaid'];
    }

    /**
     * Include Mermaid.js library.
     *
     * @param array<string, mixed> $options Supported keys: src, startOnLoad, config, guardKey, toolkit
     */
    public function includeMermaid(array $options = []): string
    {
        $src = (string)($options['src'] ?? 'https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js');
        $startOnLoad = (bool)($options['startOnLoad'] ?? true);
        $config = (array)($options['config'] ?? []);
        $guardKey = (string)($options['guardKey'] ?? '__workflowMermaidInitialized');
        $config = ['startOnLoad' => $startOnLoad] + $config;
        $configJson = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $output = sprintf(
            '<script src="%s"></script><script>(function(){window.%s=window.%s||false;if(window.%s||typeof mermaid==="undefined"){return;}mermaid.initialize(%s);window.%s=true;})();</script>',
            h($src),
            h($guardKey),
            h($guardKey),
            h($guardKey),
            $configJson,
            h($guardKey),
        );

        if (!empty($options['toolkit'])) {
            $output .= $this->mermaidToolkitScript();
        }

        return $output;
    }

    /**
     * Render a compact embeddable diagram widget with optional fullscreen/code/export controls.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string, mixed> $options Supported keys: id, title, class, currentState, showDetails,
     *  detailMarkers, focusCurrentState, fullscreen, code, export, exportScope,
     *  minWidth, maxHeight, modalMinWidth
     */
    public function widget(Definition $definition, array $options = []): string
    {
        $data = $this->diagramData($definition, $options);
        $widgetId = (string)($options['id'] ?? 'workflow-widget-' . (++$this->widgetSequence));
        $modalId = $widgetId . '-modal';
        $title = (string)($options['title'] ?? 'Workflow');
        $focusCurrentState = (bool)($options['focusCurrentState'] ?? true);
        $fullscreen = (bool)($options['fullscreen'] ?? true);
        $showCode = (bool)($options['code'] ?? true);
        $exportFormats = $this->normalizeExportFormats($options['export'] ?? 'svg');
        $minWidth = (string)($options['minWidth'] ?? '760px');
        $maxHeight = (string)($options['maxHeight'] ?? '320px');
        $modalMinWidth = (string)($options['modalMinWidth'] ?? '960px');
        $class = (string)($options['class'] ?? 'workflow-widget');

        $buttons = [];
        if ($showCode) {
            $buttons[] = sprintf(
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-toggle-code="%s">Code</button>',
                h($widgetId),
            );
        }
        foreach ($exportFormats as $exportFormat) {
            if ($exportFormat === 'svg' || $exportFormat === 'png' || $exportFormat === 'mmd') {
                $label = match ($exportFormat) {
                    'mmd' => 'Export Mermaid',
                    default => 'Export ' . strtoupper($exportFormat),
                };
                $buttons[] = sprintf(
                    '<button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-export-%s="%s" data-workflow-export-filename="%s">%s</button>',
                    h($exportFormat),
                    h($widgetId),
                    h($widgetId . '.' . ($exportFormat === 'mmd' ? 'mmd' : $exportFormat)),
                    h($label),
                );

                continue;
            }
        }
        if ($fullscreen) {
            $buttons[] = sprintf(
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#%s">Fullscreen</button>',
                h($modalId),
            );
        }

        $toolbar = $buttons !== [] ? '<div class="workflow-widget-toolbar">' . implode(' ', $buttons) . '</div>' : '';
        $currentState = $data['currentStateLabel'] !== null ? '<div class="workflow-widget-state">Current state: <strong>' . h((string)$data['currentStateLabel']) . '</strong></div>' : '';
        $codeBlock = $showCode ? sprintf(
            '<div class="workflow-widget-code" data-workflow-code="%s" hidden><pre><code>%s</code></pre></div>',
            h($widgetId),
            h($data['mermaid']),
        ) : '';

        $preview = sprintf(
            '<div class="workflow-widget-preview" data-workflow-render-root="%s" data-focus-current-state="%s" style="max-height:%s;overflow:auto;">'
            . '<script type="text/plain" data-workflow-mermaid-source>%s</script>'
            . '<div data-workflow-mermaid-target style="min-width:%s;"></div>'
            . '</div>',
            h($widgetId),
            $focusCurrentState ? '1' : '0',
            h($maxHeight),
            $data['mermaid'],
            h($minWidth),
        );

        $modal = '';
        if ($fullscreen) {
            $modal = sprintf(
                '<div class="modal fade" id="%s" tabindex="-1" aria-hidden="true" data-workflow-modal="%s">'
                . '<div class="modal-dialog modal-fullscreen"><div class="modal-content">'
                . '<div class="modal-header"><h2 class="modal-title fs-5">%s</h2>'
                . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                . '</div><div class="modal-body">'
                . '<div class="workflow-widget-modal-preview" data-workflow-render-root="%s-full" data-focus-current-state="%s" style="height:100%%;overflow:auto;">'
                . '<script type="text/plain" data-workflow-mermaid-source>%s</script>'
                . '<div data-workflow-mermaid-target style="min-width:%s;"></div>'
                . '</div></div></div></div></div>',
                h($modalId),
                h($widgetId),
                h($title),
                h($widgetId),
                $focusCurrentState ? '1' : '0',
                $data['mermaid'],
                h($modalMinWidth),
            );
        }

        return sprintf(
            '<div id="%s" class="%s">%s%s%s%s</div>',
            h($widgetId),
            h($class),
            $toolbar,
            $currentState,
            $preview,
            $codeBlock . $modal,
        );
    }

    private function getMermaidRenderer(): MermaidRenderer
    {
        if (!$this->mermaidRenderer instanceof MermaidRenderer) {
            $this->mermaidRenderer = new MermaidRenderer();
        }

        return $this->mermaidRenderer;
    }

    /**
     * @param mixed $export
     *
     * @return array<int, string>
     */
    private function normalizeExportFormats(mixed $export): array
    {
        if ($export === false || $export === null) {
            return [];
        }

        $formats = is_array($export) ? $export : [$export];
        $normalized = [];
        foreach ($formats as $format) {
            $value = strtolower(trim((string)$format));
            if ($value === 'mermaid') {
                $value = 'mmd';
            }
            if ($value === '' || !in_array($value, ['svg', 'png', 'mmd'], true) || in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * Get a contrasting text color for a background.
     */
    private function getContrastColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }

    private function mermaidToolkitScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    if (window.__workflowMermaidToolkitInitialized) {
        return;
    }
    window.__workflowMermaidToolkitInitialized = true;
    let renderCount = 0;

    function centerCurrentState(root) {
        if (!root || root.dataset.focusCurrentState !== '1') {
            return;
        }

        let attempts = 0;
        function tryCenter() {
            const currentNode = root.querySelector('.current');
            const svg = root.querySelector('svg');
            if (!svg || !currentNode) {
                attempts += 1;
                if (attempts < 20) {
                    window.setTimeout(tryCenter, 150);
                }
                return;
            }

            const rootRect = root.getBoundingClientRect();
            const nodeRect = currentNode.getBoundingClientRect();
            root.scrollTo({
                left: Math.max(0, root.scrollLeft + (nodeRect.left - rootRect.left) - (root.clientWidth / 2) + (nodeRect.width / 2)),
                top: Math.max(0, root.scrollTop + (nodeRect.top - rootRect.top) - (root.clientHeight / 2) + (nodeRect.height / 2)),
                behavior: 'smooth'
            });
        }

        tryCenter();
    }

    function renderRoot(root) {
        if (!root || root.dataset.workflowRendered === '1') {
            return;
        }

        const source = root.querySelector('[data-workflow-mermaid-source]');
        const target = root.querySelector('[data-workflow-mermaid-target]');
        if (!source || !target || typeof mermaid === 'undefined' || !mermaid.render) {
            return;
        }

        const graphId = 'workflow-graph-' + (++renderCount);
        mermaid.render(graphId, source.textContent || '').then(function (result) {
            target.innerHTML = result.svg;
            root.dataset.workflowRendered = '1';
            centerCurrentState(root);
        }).catch(function (error) {
            target.innerHTML = '<div class="text-danger small p-3">Workflow graph render error: ' + error.message + '</div>';
        });
    }

    function pngExportSource(diagram) {
        return '%%{init: {"securityLevel": "strict", "flowchart": {"htmlLabels": false}} }%%\n' + diagram;
    }

    function svgElementFromMarkup(markup) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(markup, 'image/svg+xml');

        return doc.documentElement instanceof SVGElement ? doc.documentElement : null;
    }

    function widgetSvgMarkup(widgetId) {
        const svg = document.querySelector('#' + widgetId + ' svg');
        if (!svg) {
            throw new Error('Workflow graph is not rendered yet.');
        }

        return ensureStandaloneSvgMarkup(new XMLSerializer().serializeToString(svg));
    }

    function rawWidgetSvgMarkup(widgetId) {
        const svg = document.querySelector('#' + widgetId + ' svg');
        if (!svg) {
            throw new Error('Workflow graph is not rendered yet.');
        }

        const clone = svg.cloneNode(true);
        if (!clone.getAttribute('xmlns')) {
            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        return new XMLSerializer().serializeToString(clone);
    }

    function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function parseSvgDimensions(svg) {
        const widthAttr = svg.getAttribute('width') || '';
        const heightAttr = svg.getAttribute('height') || '';
        let width = widthAttr.includes('%') ? NaN : parseFloat(widthAttr);
        let height = heightAttr.includes('%') ? NaN : parseFloat(heightAttr);
        let minX = 0;
        let minY = 0;
        const viewBox = svg.getAttribute('viewBox');

        if (viewBox) {
            const parts = viewBox.trim().split(/\s+/);
            if (parts.length === 4) {
                minX = parseFloat(parts[0]) || 0;
                minY = parseFloat(parts[1]) || 0;
                if (!Number.isFinite(width) || width <= 0) {
                    width = parseFloat(parts[2]);
                }
                if (!Number.isFinite(height) || height <= 0) {
                    height = parseFloat(parts[3]);
                }
            }
        }

        if (!Number.isFinite(width) || width <= 0) {
            width = 1200;
        }
        if (!Number.isFinite(height) || height <= 0) {
            height = 800;
        }

        return {minX, minY, width, height};
    }

    function ensureStandaloneSvgMarkup(markup) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(markup, 'image/svg+xml');
        const svg = doc.documentElement;
        if (!(svg instanceof SVGElement)) {
            throw new Error('Invalid SVG export payload.');
        }

        svg.querySelectorAll('foreignObject').forEach(function (foreignObject) {
            const group = foreignObject.parentNode;
            if (!(group instanceof SVGGElement)) {
                foreignObject.remove();
                return;
            }

            const width = parseFloat(foreignObject.getAttribute('width') || '0') || 0;
            const height = parseFloat(foreignObject.getAttribute('height') || '0') || 0;
            const textValue = (foreignObject.textContent || '').replace(/\s+/g, ' ').trim();
            const text = doc.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', String(width / 2));
            text.setAttribute('y', String(height / 2));
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dominant-baseline', 'middle');
            text.setAttribute('font-family', 'Trebuchet MS, Verdana, Arial, sans-serif');
            text.setAttribute('font-size', '16');
            text.setAttribute('fill', '#333333');
            text.setAttribute('stroke', 'none');
            text.setAttribute('stroke-width', '0');
            text.setAttribute('paint-order', 'fill');
            text.setAttribute('text-rendering', 'geometricPrecision');
            text.textContent = textValue;
            const wrapper = doc.createElementNS('http://www.w3.org/2000/svg', 'g');
            wrapper.setAttribute('data-workflow-export-label', '1');
            wrapper.appendChild(text);
            group.replaceChild(wrapper, foreignObject);
        });

        if (!svg.getAttribute('xmlns')) {
            svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        const dims = parseSvgDimensions(svg);
        svg.setAttribute('width', String(dims.width));
        svg.setAttribute('height', String(dims.height));
        svg.style.backgroundColor = '#ffffff';

        const hasBackground = svg.querySelector('[data-workflow-export-background="1"]');
        if (!hasBackground) {
            const rect = doc.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('x', String(dims.minX));
            rect.setAttribute('y', String(dims.minY));
            rect.setAttribute('width', String(dims.width));
            rect.setAttribute('height', String(dims.height));
            rect.setAttribute('fill', '#ffffff');
            rect.setAttribute('data-workflow-export-background', '1');
            svg.insertBefore(rect, svg.firstChild);
        }

        return {
            markup: new XMLSerializer().serializeToString(svg),
            width: dims.width,
            height: dims.height
        };
    }

    async function rasterizeSvgMarkupToPng(markup, filename) {
        const payload = ensureStandaloneSvgMarkup(markup);
        const blob = new Blob([payload.markup], {type: 'image/svg+xml;charset=utf-8'});
        const url = URL.createObjectURL(blob);

        try {
            const img = await new Promise(function (resolve, reject) {
                const image = new Image();
                image.onload = function () {
                    resolve(image);
                };
                image.onerror = function () {
                    reject(new Error('Failed to load workflow SVG for PNG export.'));
                };
                image.src = url;
            });

            const scale = 2;
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.ceil(payload.width * scale));
            canvas.height = Math.max(1, Math.ceil(payload.height * scale));
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('Canvas context unavailable for PNG export.');
            }

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const pngBlob = await new Promise(function (resolve, reject) {
                canvas.toBlob(function (result) {
                    if (!result) {
                        reject(new Error('PNG export produced no image data.'));
                        return;
                    }
                    resolve(result);
                }, 'image/png');
            });
            downloadBlob(pngBlob, filename);
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-workflow-render-root]').forEach(renderRoot);

        document.querySelectorAll('[data-workflow-toggle-code]').forEach(function (button) {
            button.addEventListener('click', function () {
                const code = document.querySelector('[data-workflow-code="' + button.getAttribute('data-workflow-toggle-code') + '"]');
                if (code) {
                    code.hidden = !code.hidden;
                }
            });
        });

        document.querySelectorAll('[data-workflow-export-svg]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    const widgetId = button.getAttribute('data-workflow-export-svg');
                    const filename = button.getAttribute('data-workflow-export-filename') || (widgetId + '.svg');
                    const blob = new Blob([rawWidgetSvgMarkup(widgetId)], {type: 'image/svg+xml;charset=utf-8'});
                    downloadBlob(blob, filename);
                } catch (error) {
                    console.error(error);
                    window.alert('Workflow SVG export failed: ' + (error && error.message ? error.message : error));
                }
            });
        });

        document.querySelectorAll('[data-workflow-export-png]').forEach(function (button) {
            button.addEventListener('click', function () {
                const widgetId = button.getAttribute('data-workflow-export-png') || 'workflow';
                const filename = button.getAttribute('data-workflow-export-filename') || (widgetId + '.png');
                let payload;
                try {
                    payload = widgetSvgMarkup(widgetId);
                } catch (error) {
                    console.error(error);
                    window.alert('Workflow PNG export failed: ' + (error && error.message ? error.message : error));
                    return;
                }

                rasterizeSvgMarkupToPng(payload.markup, filename).catch(function (error) {
                    console.error(error);
                    window.alert('Workflow PNG export failed: ' + (error && error.message ? error.message : error));
                });
            });
        });

        document.querySelectorAll('[data-workflow-export-mmd]').forEach(function (button) {
            button.addEventListener('click', function () {
                const widgetId = button.getAttribute('data-workflow-export-mmd');
                const root = document.getElementById(widgetId);
                const source = root ? root.querySelector('[data-workflow-mermaid-source]') : null;
                if (!source) {
                    return;
                }

                const filename = button.getAttribute('data-workflow-export-filename') || (widgetId + '.mmd');
                const blob = new Blob([source.textContent || ''], {type: 'text/plain;charset=utf-8'});
                downloadBlob(blob, filename);
            });
        });

        document.querySelectorAll('[data-workflow-modal]').forEach(function (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                modal.querySelectorAll('[data-workflow-render-root]').forEach(function (root) {
                    renderRoot(root);
                    centerCurrentState(root);
                });
            });
        });
    });
})();
</script>
HTML;
    }
}
