<?php

declare(strict_types=1);

namespace Workflow;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Routing\RouteBuilder;
use Nette\Neon\Neon;
use Symfony\Component\Yaml\Yaml;
use Workflow\Command\WorkflowListCommand;
use Workflow\Command\WorkflowShowCommand;
use Workflow\Command\WorkflowTimeoutsCommand;
use Workflow\Command\WorkflowValidateCommand;
use Workflow\Loader\AttributeLoader;
use Workflow\Loader\ChainLoader;
use Workflow\Loader\NeonLoader;
use Workflow\Loader\YamlLoader;
use Workflow\Service\WorkflowRegistry;

class WorkflowPlugin extends BasePlugin
{
    protected bool $bootstrapEnabled = true;

    protected bool $routesEnabled = true;

    protected bool $consoleEnabled = true;

    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        $this->loadDefaultConfig();
        $this->registerServices();
    }

    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('workflow list', WorkflowListCommand::class);
        $commands->add('workflow show', WorkflowShowCommand::class);
        $commands->add('workflow timeouts', WorkflowTimeoutsCommand::class);
        $commands->add('workflow validate', WorkflowValidateCommand::class);

        return $commands;
    }

    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('Workflow', ['path' => '/workflow'], function (RouteBuilder $builder): void {
            $builder->prefix('Admin', function (RouteBuilder $builder): void {
                $builder->fallbacks();
            });
            $builder->fallbacks();
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

    private function registerServices(): void
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
            if (class_exists(Yaml::class)) {
                $loaders[] = new YamlLoader($configPath);
            }
            if (class_exists(Neon::class)) {
                $loaders[] = new NeonLoader($configPath);
            }
        }

        if (!$loaders) {
            return;
        }

        $chainLoader = new ChainLoader($loaders);
        $registry = new WorkflowRegistry($chainLoader, EventManager::instance());

        Configure::write('Workflow.registry', $registry);
    }
}
