<?php
/**
 * @var \Cake\View\View $this
 * @var array<string, array{name: string, count: int}>|null $workflowStats
 * @var int|null $pendingTimeoutsCount
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->fetch('title') ?> - Workflow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #1a1a2e;
            color: #fff;
            padding-top: 1rem;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 0 1rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        .sidebar-brand h4 {
            margin: 0;
            font-size: 1.1rem;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
        }
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-nav li .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-nav li .nav-link:hover,
        .sidebar-nav li .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .sidebar-nav li .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        .sidebar-section {
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.4);
            margin-top: 1rem;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #1a1a2e;
        }
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .workflow-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .workflow-card h5 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .state-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .state-badge .count {
            background: rgba(0,0,0,0.15);
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            margin-left: 0.5rem;
            font-weight: 600;
        }
        .item-state-highlight {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .recent-transitions {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .recent-transitions .list-group-item {
            border-left: none;
            border-right: none;
            padding: 1rem 1.25rem;
        }
        .recent-transitions .list-group-item:first-child {
            border-top: none;
        }
        .transition-arrow {
            color: #6c757d;
            margin: 0 0.5rem;
        }
        .timeout-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 4px 4px 0;
            font-size: 0.9rem;
        }
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .mermaid {
            background: #fff;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .diagram-container {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .flag-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            background: #e9d5ff;
            color: #6b21a8;
            margin-right: 0.25rem;
        }
        .happy-path {
            color: #28a745;
        }
        .info-table th {
            width: 150px;
            background: #f8f9fa;
        }
        /* Matrix table styles */
        .matrix-table {
            font-size: 0.9rem;
        }
        .matrix-table .state-column {
            min-width: 180px;
        }
        .matrix-table .bucket-column {
            min-width: 80px;
        }
        .matrix-table .total-column {
            min-width: 70px;
            background: #f8f9fa;
        }
        .matrix-table .state-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        .matrix-cell-link {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #e9ecef;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            color: #495057;
        }
        .matrix-cell-link:hover {
            background: #dee2e6;
            color: #212529;
        }

        /* Lighter placeholder so it doesn't get mistaken for real content */
        .form-control::placeholder,
        .form-select::placeholder {
            color: #adb5bd;
            opacity: 1;
        }

        /* Mobile topbar — visible only below the lg breakpoint */
        .workflow-topbar {
            background: #1a1a2e;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* Below lg: collapse sidebar into Bootstrap offcanvas, drop the desktop margin */
        @media (max-width: 991.98px) {
            .sidebar {
                position: static;
                width: 280px;
                height: 100%;
                top: auto;
                left: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
    <?= $this->fetch('css') ?>
</head>
<body>
    <nav class="workflow-topbar navbar navbar-dark d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#workflowSidebar" aria-controls="workflowSidebar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0"><i class="bi bi-diagram-3"></i> Workflow</span>
        </div>
    </nav>

    <?= $this->element('Workflow.sidebar', [
        'workflowStats' => $workflowStats ?? [],
        'pendingTimeoutsCount' => $pendingTimeoutsCount ?? 0,
    ]) ?>

    <main class="main-content">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->Workflow->includeMermaid() ?>
    <?= $this->fetch('script') ?>
</body>
</html>
