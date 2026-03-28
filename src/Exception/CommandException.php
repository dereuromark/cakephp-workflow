<?php
declare(strict_types=1);

namespace Workflow\Exception;

use Throwable;

class CommandException extends WorkflowException
{
    private string $commandName;

    public function __construct(string $commandName, Throwable $previous)
    {
        $this->commandName = $commandName;
        parent::__construct(
            "Command '{$commandName}' failed: {$previous->getMessage()}",
            0,
            $previous,
        );
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }
}
