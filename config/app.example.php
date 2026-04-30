<?php

/**
 * Example Workflow plugin configuration.
 *
 * Copy this file to your application's config/app_local.php and customize.
 */

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
        'adminAccess' => function (\Cake\Http\ServerRequest $request): bool {
            $identity = $request->getAttribute('identity');

            return $identity !== null && in_array('admin', (array)$identity->roles, true);
        },

        /**
         * Path to workflow definition files (YAML, NEON, or PHP).
         * Relative to APP or absolute path.
         *
         * Default: CONFIG . 'Workflows/'
         */
        'configPath' => CONFIG . 'Workflows' . DS,

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
         * Strict mode for guards and commands.
         * When enabled, throws exceptions for missing guards/commands.
         * When disabled, missing handlers are silently skipped.
         *
         * Default: false
         */
        'strictMode' => false,
    ],
];
