<?php
/**
 * @var \Cake\View\View $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->fetch('title') ?> - Workflow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= $this->Html->css('Workflow.workflow') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= $this->Url->build(['plugin' => 'Workflow', 'controller' => 'Workflows', 'action' => 'index', 'prefix' => 'Admin']) ?>">
                Workflow Admin
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">Workflows</a>
                <a class="nav-link" href="<?= $this->Url->build(['controller' => 'Transitions', 'action' => 'index']) ?>">Transitions</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->Workflow->includeMermaid() ?>
    <?= $this->fetch('script') ?>
</body>
</html>
