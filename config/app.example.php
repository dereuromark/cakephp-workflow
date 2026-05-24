<?php

/**
 * Example Workflow plugin configuration.
 *
 * Copy this file to your application's config/app_local.php and customize.
 */
use Cake\Http\ServerRequest;

return [
    'Workflow' => [
        /**
         * Admin access gate. REQUIRED — the host application MUST set this
         * to a Closure that returns literal `true` to grant access to
         * /admin/workflow/...; anything else (unset, non-Closure, returns
         * false, returns a truthy non-bool, or throws) yields a 403.
         *
         * The admin UI can rewrite workflow definitions and trigger
         * transitions; the controllers extend the bare Cake\Controller\Controller
         * (not the host's AppController), so per-controller auth wired through
         * AppController would never run anyway. The default policy is deny.
         *
         * Example — admin role check on the cakephp/authentication identity:
         */
        'adminAccess' => function (ServerRequest $request): bool {
            $identity = $request->getAttribute('identity');

            return $identity !== null && in_array('admin', (array)$identity->roles, true);
        },

        /**
         * Back-to-App link in the admin sidebar (opt-in).
         *
         * When set, a "Back to App" entry appears at the bottom of the
         * sidebar so admins can escape the plugin-isolated layout. Accepts
         * anything Router::url() takes — Cake URL array, path string, or
         * full URL. Use 'plugin' => false to anchor the URL builder to the
         * host app rather than the Workflow plugin.
         *
         * 'adminBackLabel' is optional and defaults to "Back to App"
         * (translated through the `workflow` domain).
         */
        // 'adminBackUrl' => ['plugin' => false, 'prefix' => 'Admin', 'controller' => 'Overview', 'action' => 'index'],
        // 'adminBackLabel' => 'Back to admin',

        /**
         * Optional actor presentation hook for persisted transition views.
         *
         * Receives the stored `user_id` plus the WorkflowTransition entity and
         * may return either a string label or an array with `label` and `url`
         * keys so the admin UI can show a friendly name/link instead of only
         * the raw identifier.
         */
        // 'adminActorResolver' => function (string $userId, ?\Workflow\Model\Entity\WorkflowTransition $transition = null): array {
        //     return [
        //         'label' => 'Admin #' . $userId,
        //         'url' => ['plugin' => false, 'controller' => 'Users', 'action' => 'view', $userId],
        //     ];
        // },

        /**
         * Workflow definition loaders (read by WorkflowPlugin::buildRegistry()).
         *
         * The registry chains an attribute loader and YAML/NEON file loaders.
         * At least one of `namespaces` (for PHP attribute definitions) or a
         * valid `configPath` directory (for YAML/NEON files) must be set, or
         * building the registry throws a RuntimeException.
         */
        'loader' => [
            /**
             * Namespaces scanned by the AttributeLoader for state classes
             * carrying workflow attributes. Empty disables the attribute loader.
             *
             * Default: []
             */
            'namespaces' => [],

            /**
             * Namespace-prefix to base-path map for the AttributeLoader, e.g.
             * ['App\\' => APP]. Helps resolve class files for the scanned
             * namespaces.
             *
             * Default: []
             */
            'pathMap' => [],

            /**
             * Directory scanned for YAML/NEON workflow definition files
             * (requires symfony/yaml and/or nette/neon). Only used when the
             * directory exists.
             *
             * Default: CONFIG . 'workflows/' (lower-case)
             */
            'configPath' => CONFIG . 'workflows' . DS,
        ],

        /**
         * Enable transition logging to database.
         * Records all successful transitions with timestamps and context.
         *
         * Default: true
         */
        'logging' => true,

        /**
         * Enable entity locking during transitions.
         * Prevents concurrent transitions on the same entity.
         *
         * Default: true
         */
        'locking' => true,

        /**
         * Default lock duration in seconds.
         * How long an entity remains locked during a transition.
         *
         * Default: 30
         */
        'lockDuration' => 30,

        /**
         * Enable timeout handling for automatic transitions.
         * Allows states to auto-transition after a specified delay.
         *
         * Default: true
         */
        'timeouts' => true,

        /**
         * Default timeout delay in seconds (for Queue integration).
         *
         * Default: 3600 (1 hour)
         */
        'timeoutDelay' => 3600,

        /**
         * Strict mode for workflow correctness.
         * When enabled, throws exceptions for missing guards/commands/conditions, and when an
         * automatic branch state (more than one automatic transition) has no matching condition
         * and no unconditional fallback, and the automatic branch is the sole exit (otherwise the
         * item would silently stay put). A single conditional automatic transition, or a branch
         * that also has a non-automatic exit (a manual transition or one a timeout fires), is not
         * stuck and is exempt.
         * When disabled, missing handlers are silently skipped and such a branch stays put.
         * Either way, `bin/cake workflow validate` reports automatic branch states without a
         * fallback (as a warning when off, as a hard error when on).
         *
         * Default: false
         */
        'strictMode' => false,

        /**
         * Maximum number of times a single event may re-fire within one
         * transition before the registry stops to guard against infinite loops.
         *
         * Default: 10
         */
        'maxEventRepeats' => 10,

        /**
         * Workflow registry instance.
         *
         * Normally auto-resolved: the plugin builds a WorkflowRegistry from the
         * `loader` config above, and the WorkflowRegistryLocator (DI container)
         * is consulted first. This key is only read as a fallback and must be an
         * actual Workflow\Service\WorkflowRegistry OBJECT, not a scalar. Set it
         * only for advanced/custom registry wiring.
         */
        // 'registry' => new \Workflow\Service\WorkflowRegistry(/* ... */),
    ],
];
