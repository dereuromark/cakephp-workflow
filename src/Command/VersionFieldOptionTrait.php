<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Console\Arguments;
use Cake\ORM\Table;

/**
 * Resolves the entity version column for versioning commands.
 *
 * Precedence: an explicit --version-field option, then the table's Workflow behavior
 * configuration, then the default `workflow_version`.
 */
trait VersionFieldOptionTrait
{
    private function resolveVersionField(Arguments $args, Table $table): string
    {
        $explicit = $args->getOption('version-field');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if ($table->hasBehavior('Workflow')) {
            $field = $table->behaviors()->get('Workflow')->getConfig('versionField');
            if (is_string($field) && $field !== '') {
                return $field;
            }
        }

        return 'workflow_version';
    }
}
