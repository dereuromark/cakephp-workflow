<?php

/**
 * Example Workflow plugin configuration.
 *
 * Copy this file to your application's config/app_local.php and customize.
 */

return [
    'Workflow' => [
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
