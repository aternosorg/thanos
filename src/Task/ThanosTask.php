<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnParent;
use Aternos\Taskmaster\Task\Task;
use Exception;

abstract class ThanosTask extends Task
{
    #[OnParent]
    public function handleError(Exception $error): void
    {
        $this->error = $error;
    }
}
