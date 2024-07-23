<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\TaskFactory;
use Aternos\Taskmaster\Task\TaskInterface;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\World\AnvilWorld;
use Generator;

class RegionTaskFactory extends TaskFactory
{
    protected Generator $taskGenerator;

    public function __construct(
        protected AnvilWorld $world,

        /**
         * @var ChunkPatternInterface[]
         */
        protected array $pattern = []
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
            $pattern = $this->pattern;
            array_unshift($pattern, new ListPattern($regionDirectory->getForceLoadedChunks()));

            foreach ($regionDirectory->getRegionFiles() as $file) {
                yield new RegionTask(
                    $regionDirectory->getPath() . DIRECTORY_SEPARATOR . $file,
                    $regionDirectory->getDestination() . DIRECTORY_SEPARATOR . $file,
                    $pattern
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
