<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\Chunk\ChunkInterface;

class InhabitedTimePattern implements ChunkPatternInterface
{
    /**
     * @param int $inhabitedTimeThreshold
     * @param bool $removeUnknownChunks
     */
    public function __construct(
        protected int $inhabitedTimeThreshold,
        protected bool $removeUnknownChunks,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function matches(ChunkInterface $chunk): bool
    {
        $time = $chunk->getInhabitedTime();
        return $time > $this->inhabitedTimeThreshold || ($time === -1 && !$this->removeUnknownChunks);
    }

    /**
     * @return int
     */
    public function getInhabitedTimeThreshold(): int
    {
        return $this->inhabitedTimeThreshold;
    }

    /**
     * @param int $inhabitedTimeThreshold
     * @return $this
     */
    public function setInhabitedTimeThreshold(int $inhabitedTimeThreshold): InhabitedTimePattern
    {
        $this->inhabitedTimeThreshold = $inhabitedTimeThreshold;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRemoveUnknownChunks(): bool
    {
        return $this->removeUnknownChunks;
    }

    /**
     * @param bool $removeUnknownChunks
     * @return $this
     */
    public function setRemoveUnknownChunks(bool $removeUnknownChunks): InhabitedTimePattern
    {
        $this->removeUnknownChunks = $removeUnknownChunks;
        return $this;
    }
}
