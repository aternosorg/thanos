<?php

namespace Aternos\Thanos\World;

use Aternos\IO\Exception\IOException;
use Aternos\Taskmaster\Task\TaskFactory;
use Aternos\Taskmaster\Task\TaskInterface;
use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Exception\TaskCreatingException;
use Generator;
use Throwable;

class ThanosTaskFactory extends TaskFactory
{
    /**
     * @param Generator $generator
     */
    public function __construct(
        protected Generator $generator,
    )
    {
    }

    /**
     * @inheritDoc
     * @throws FileSystemException
     * @throws TaskCreatingException
     */
    public function createNextTask(?string $group): ?TaskInterface
    {
        try {
            if (!$this->generator->valid()) {
                return null;
            }
            $task = $this->generator->current();
            $this->generator->next();
        } catch (IOException $e) {
            throw new FileSystemException("A file system error occurred while creating the next task", previous: $e);
        } catch (Throwable $e) {
            throw new TaskCreatingException("An error occurred while creating the next task", previous: $e);
        }
        return $task;
    }
}
