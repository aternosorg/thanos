<?php

namespace Aternos\Thanos;

use Aternos\Taskmaster\Taskmaster;
use Aternos\Thanos\Task\RegionTask;
use Aternos\Thanos\Task\RegionTaskFactory;
use Aternos\Thanos\World\AnvilWorld;
use Exception;

/**
 * Class Thanos
 * Automatically delete chunks from Minecraft worlds
 *
 * @package Aternos\Thanos
 */
class Thanos
{

    /**
     * @var int
     */
    protected int $minInhabitedTime = 0;
    protected int $defaultWorkerCount = 8;
    protected float $defaultTaskTimeout = 0;
    protected bool $removeUnknownChunks = false;

    /**
     * @param int $minInhabitedTime
     */
    public function setMinInhabitedTime(int $minInhabitedTime): void
    {
        $this->minInhabitedTime = $minInhabitedTime;
    }

    /**
     * @return int $maxInhabitedTime
     */
    public function getMinInhabitedTime(): int
    {
        return $this->minInhabitedTime;
    }

    /**
     * @param int $defaultWorkerCount
     */
    public function setDefaultWorkerCount(int $defaultWorkerCount): void
    {
        $this->defaultWorkerCount = $defaultWorkerCount;
    }

    /**
     * @return int $defaultWorkerCount
     */
    public function getDefaultWorkerCount(): int
    {
        return $this->defaultWorkerCount;
    }

    /**
     * Remove all unused chunks
     *
     * @param AnvilWorld $world
     * @return int
     * @throws Exception
     */
    public function snap(AnvilWorld $world): int
    {
        $world->copyOtherFiles();
        $removedChunks = 0;

        $taskmaster = new Taskmaster();
        $taskmaster->autoDetectWorkers($this->defaultWorkerCount);
        $taskmaster->setDefaultTaskTimeout($this->getDefaultTaskTimeout());

        $taskmaster->addTaskFactory(new RegionTaskFactory($world, $this->getMinInhabitedTime(), $this->getRemoveUnknownChunks()));

        foreach ($taskmaster->waitAndHandleTasks() as $task) {
            if (!$task instanceof RegionTask) {
                continue;
            }
            if ($task->getError()) {
                $taskmaster->stop();
                throw $task->getError();
            }

            $removedChunks += $task->getResult();
        }

        $taskmaster->stop();

        return $removedChunks;
    }

    /**
     * @param bool $removeUnknownChunks
     */
    public function setRemoveUnknownChunks(bool $removeUnknownChunks): void
    {
        $this->removeUnknownChunks = $removeUnknownChunks;
    }

    /**
     * @return bool
     */
    public function getRemoveUnknownChunks(): bool
    {
        return $this->removeUnknownChunks;
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
     * @return void
     */
    public function setDefaultTaskTimeout(float $defaultTaskTimeout): void
    {
        $this->defaultTaskTimeout = $defaultTaskTimeout;
    }
}
