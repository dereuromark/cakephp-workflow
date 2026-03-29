<?php
/**
 * @var \Cake\View\View $this
 * @var array<string, array{name: string, count: int}>|null $workflowStats
 * @var int|null $pendingTimeoutsCount
 * @var int|null $orphanCount
 */

use Cake\Core\Configure;

$workflowStats = $workflowStats ?? [];
$pendingTimeoutsCount = $pendingTimeoutsCount ?? 0;
$orphanCount = $orphanCount ?? 0;
$currentController = $this->request->getParam('controller');
$currentAction = $this->request->getParam('action');
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-diagram-3"></i> Workflow</h4>
        <small>CakePHP Plugin</small>
    </div>

    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li>
            <?= $this->Html->link(
                '<i class="bi bi-speedometer2"></i> Dashboard',
                ['plugin' => 'Workflow', 'controller' => 'Workflow', 'action' => 'index', 'prefix' => 'Admin'],
                [
                    'class' => 'nav-link' . ($currentController === 'Workflow' ? ' active' : ''),
                    'escapeTitle' => false,
                ],
            ) ?>
        </li>
    </ul>

    <div class="sidebar-section">Workflows</div>
    <ul class="sidebar-nav">
        <li>
            <?= $this->Html->link(
                '<i class="bi bi-collection"></i> All Workflows',
                ['plugin' => 'Workflow', 'controller' => 'Workflows', 'action' => 'index', 'prefix' => 'Admin'],
                [
                    'class' => 'nav-link' . ($currentController === 'Workflows' && $currentAction === 'index' ? ' active' : ''),
                    'escapeTitle' => false,
                ],
            ) ?>
        </li>
        <?php foreach ($workflowStats as $workflow) { ?>
            <li>
                <?= $this->Html->link(
                    '<i class="bi bi-diagram-2"></i> ' . h($workflow['name']) .
                    ' <span class="badge bg-secondary ms-auto">' . $workflow['count'] . '</span>',
                    ['plugin' => 'Workflow', 'controller' => 'Workflows', 'action' => 'view', $workflow['name'], 'prefix' => 'Admin'],
                    [
                        'class' => 'nav-link',
                        'escapeTitle' => false,
                    ],
                ) ?>
            </li>
        <?php } ?>
    </ul>

    <div class="sidebar-section">Monitoring</div>
    <ul class="sidebar-nav">
        <li>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left-right"></i> Transitions',
                ['plugin' => 'Workflow', 'controller' => 'Transitions', 'action' => 'index', 'prefix' => 'Admin'],
                [
                    'class' => 'nav-link' . ($currentController === 'Transitions' ? ' active' : ''),
                    'escapeTitle' => false,
                ],
            ) ?>
        </li>
        <li>
            <?= $this->Html->link(
                '<i class="bi bi-clock-history"></i> Timeouts' .
                ($pendingTimeoutsCount > 0 ? ' <span class="badge bg-warning text-dark ms-auto">' . $pendingTimeoutsCount . '</span>' : ''),
                ['plugin' => 'Workflow', 'controller' => 'Timeouts', 'action' => 'index', 'prefix' => 'Admin'],
                [
                    'class' => 'nav-link' . ($currentController === 'Timeouts' ? ' active' : ''),
                    'escapeTitle' => false,
                ],
            ) ?>
        </li>
        <li>
            <?= $this->Html->link(
                '<i class="bi bi-lock"></i> Locks',
                ['plugin' => 'Workflow', 'controller' => 'Locks', 'action' => 'index', 'prefix' => 'Admin'],
                [
                    'class' => 'nav-link' . ($currentController === 'Locks' ? ' active' : ''),
                    'escapeTitle' => false,
                ],
            ) ?>
        </li>
    </ul>
</nav>
