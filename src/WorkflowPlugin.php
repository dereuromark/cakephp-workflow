<?php

declare(strict_types=1);

namespace Workflow;

use Bake\Command\SimpleBakeCommand;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin as CakePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Cake\Routing\RouteBuilder;
use Nette\Neon\Neon;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Workflow\Command\BakeWorkflowStateCommand;
use Workflow\Command\WorkflowApplyCommand;
use Workflow\Command\WorkflowBatchCommand;
use Workflow\Command\WorkflowInitCommand;
use Workflow\Command\WorkflowListCommand;
use Workflow\Command\WorkflowMigrateCommand;
use Workflow\Command\WorkflowShowCommand;
use Workflow\Command\WorkflowTimeoutsCommand;
use Workflow\Command\WorkflowValidateCommand;
use Workflow\Loader\AttributeLoader;
use Workflow\Loader\ChainLoader;
use Workflow\Loader\NeonLoader;
use Workflow\Loader\PhpLoader;
use Workflow\Loader\YamlLoader;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

class WorkflowPlugin extends BasePlugin
{
    protected bool $bootstrapEnabled = true;

    protected bool $routesEnabled = true;

    protected bool $consoleEnabled = true;

    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        $this->loadDefaultConfig();
        $this->loadBakeIfAvailable($app);
    }

    /**
     * Load Bake plugin if installed but not yet loaded.
     * This enables `bin/cake bake workflow_state` without requiring
     * users to manually add Bake to their Application::bootstrap().
     */
    private function loadBakeIfAvailable(PluginApplicationInterface $app): void
    {
        if (class_exists(SimpleBakeCommand::class) && !CakePlugin::isLoaded('Bake')) {
            $app->addPlugin('Bake');
        }
    }

    public function services(ContainerInterface $container): void
    {
        WorkflowRegistryLocator::setContainer($container);

        if (!$container->has(WorkflowRegistry::class)) {
            $container->addShared(WorkflowRegistry::class, function () {
                return $this->buildRegistry();
            });
        }
    }

    public function console(CommandCollection $commands): CommandCollection
    {
        if (class_exists(SimpleBakeCommand::class) && CakePlugin::isLoaded('Bake')) {
            $commands->add('bake workflow_state', BakeWorkflowStateCommand::class);
        }

        $commands->add('workflow init', WorkflowInitCommand::class);
        $commands->add('workflow list', WorkflowListCommand::class);
        $commands->add('workflow show', WorkflowShowCommand::class);
        $commands->add('workflow apply', WorkflowApplyCommand::class);
        $commands->add('workflow batch', WorkflowBatchCommand::class);
        $commands->add('workflow timeouts', WorkflowTimeoutsCommand::class);
        $commands->add('workflow validate', WorkflowValidateCommand::class);
        $commands->add('workflow migrate', WorkflowMigrateCommand::class);

        return $commands;
    }

    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('Workflow', function (RouteBuilder $routes): void {
            $routes->fallbacks();
        });

        // Admin routes: /admin/workflow/*
        $routes->prefix('Admin', function (RouteBuilder $routes): void {
            $routes->plugin('Workflow', function (RouteBuilder $routes): void {
                $routes->connect('/', ['controller' => 'Workflows', 'action' => 'index']);
                $routes->fallbacks();
            });
        });
    }

    private function loadDefaultConfig(): void
    {
        $defaults = [
            'designer' => true,
            'logging' => true,
            'locking' => true,
            'timeouts' => true,
            'lockDuration' => 30,
            'maxEventRepeats' => 10,
            'strictMode' => false,
            'loader' => [
                'namespaces' => [],
                'configPath' => CONFIG . 'workflows' . DS,
                'pathMap' => [],
            ],
        ];

        $config = Configure::read('Workflow', []);
        Configure::write('Workflow', array_replace_recursive($defaults, $config));
    }

    private function buildRegistry(): WorkflowRegistry
    {
        $config = Configure::read('Workflow');

        $loaders = [];

        $namespaces = $config['loader']['namespaces'] ?? [];
        $pathMap = $config['loader']['pathMap'] ?? [];
        if ($namespaces) {
            $loaders[] = new AttributeLoader($namespaces, $pathMap);
        }

        $configPath = $config['loader']['configPath'] ?? null;
        if ($configPath && is_dir($configPath)) {
            // Native PHP definitions need no extra dependency.
            $loaders[] = new PhpLoader($configPath);
            if (class_exists(Yaml::class)) {
                $loaders[] = new YamlLoader($configPath);
            }
            if (class_exists(Neon::class)) {
                $loaders[] = new NeonLoader($configPath);
            }
        }

        if (!$loaders) {
            Log::warning(
                'Workflow plugin: No loaders configured. Set Workflow.loader.namespaces for PHP attributes, '
                . 'or ensure Workflow.loader.configPath directory exists with YAML/NEON files and '
                . 'symfony/yaml or nette/neon is installed.',
            );

            throw new RuntimeException('Workflow plugin registry could not be built because no loaders are configured.');
        }

        $chainLoader = new ChainLoader($loaders);

        return new WorkflowRegistry(
            $chainLoader,
            EventManager::instance(),
            (bool)($config['strictMode'] ?? false),
            (int)($config['maxEventRepeats'] ?? 10),
        );
    }
}
