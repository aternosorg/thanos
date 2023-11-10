<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\TaskFactory;
use Aternos\Taskmaster\Task\TaskInterface;
use Aternos\Thanos\World\AnvilWorld;
use Generator;

class RegionTaskFactory extends TaskFactory
{
    protected Generator $taskGenerator;

    public function __construct(
        protected AnvilWorld $world,
        protected int $inhabitedTimeThreshold,
        protected bool $removeUnknownChunks
    )
    {
        $this->taskGenerator = $this->generateTasks();
    }

    /**
     * @return Generator<RegionTask>
     */
    protected function generateTasks(): Generator
    {
        foreach ($this->world->getRegionDirectories() as $regionDirectory) {
            $forcedChunks = $regionDirectory->getForceLoadedChunks();

            foreach ($regionDirectory->getRegionFiles() as $file) {
                yield new RegionTask(
                    $regionDirectory->getPath() . DIRECTORY_SEPARATOR . $file,
                    $regionDirectory->getDestination() . DIRECTORY_SEPARATOR . $file,
                    $this->inhabitedTimeThreshold,
                    $this->removeUnknownChunks,
                    $forcedChunks
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function createNextTask(?string $group): ?TaskInterface
    {
        if (!$this->taskGenerator->valid()) {
            return null;
        }
        $task = $this->taskGenerator->current();
        $this->taskGenerator->next();
        return $task;
    }
}
