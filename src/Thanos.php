<?php

namespace Aternos\Thanos;

use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\Taskmaster\Taskmaster;
use Aternos\Thanos\Exception\SnapException;
use Aternos\Thanos\Exception\ThanosExceptionInterface;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\Factory\DimensionPatternFactoryInterface;
use Aternos\Thanos\Pattern\Factory\WorldPatternFactoryInterface;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\World\World;

class Thanos
{
    /**
     * @param (ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface)[] $patterns
     * @param int $defaultWorkerCount
     * @param float $defaultTaskTimeout
     */
    public function __construct(
        protected array $patterns = [],
        protected int   $defaultWorkerCount = 4,
        protected float $defaultTaskTimeout = 0,
    )
    {
    }

    /**
     * @return Taskmaster
     */
    protected function getTaskmaster(): Taskmaster
    {
        return new Taskmaster()
            ->autoDetectWorkers($this->defaultWorkerCount)
            ->setDefaultTaskTimeout($this->defaultTaskTimeout);
    }

    /**
     * @param World $world
     * @param GetPathInterface&DirectoryInterface $destination
     * @return int
     * @throws ThanosExceptionInterface
     */
    public function snap(World $world, DirectoryInterface&GetPathInterface $destination): int
    {
        $taskmaster = $this->getTaskmaster();
        $taskFactory = $world->getTaskFactory($this->patterns, $destination);
        $taskmaster->addTaskFactory($taskFactory);

        $deletedChunks = 0;
        foreach ($taskmaster->waitAndHandleTasks() as $task) {
            if ($e = $task->getError()) {
                $taskmaster->stop();
                throw new SnapException("An error occurred while processing the world.", previous: $e);
            }
            if (!$task instanceof ProcessRegionTask) {
                continue;
            }

            $deletedChunks += $task->getResult();
        }

        $taskmaster->stop();

        return $deletedChunks;
    }

    /**
     * @return (ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface)[]
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * @param (ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface)[] $patterns
     * @return $this
     */
    public function setPatterns(array $patterns): static
    {
        $this->patterns = $patterns;
        return $this;
    }

    /**
     * @param ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface $pattern
     * @return $this
     */
    public function addPattern(ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface $pattern): static
    {
        $this->patterns[] = $pattern;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefaultWorkerCount(): int
    {
        return $this->defaultWorkerCount;
    }

    /**
     * @param int $defaultWorkerCount
     * @return $this
     */
    public function setDefaultWorkerCount(int $defaultWorkerCount): static
    {
        $this->defaultWorkerCount = $defaultWorkerCount;
        return $this;
    }

    /**
     * @return float
     */
    public function getDefaultTaskTimeout(): float
    {
        return $this->defaultTaskTimeout;
    }

    /**
     * @param float $defaultTaskTimeout
     * @return $this
     */
    public function setDefaultTaskTimeout(float $defaultTaskTimeout): static
    {
        $this->defaultTaskTimeout = $defaultTaskTimeout;
        return $this;
    }
}
