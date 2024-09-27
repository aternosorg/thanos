<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\Chunk\ChunkInterface;

class TimestampPattern implements ChunkPatternInterface
{
    /**
     * @param int $minTimestamp
     * @param bool $removeUnknownChunks
     */
    public function __construct(
        protected int  $minTimestamp,
        protected bool $removeUnknownChunks,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function matches(ChunkInterface $chunk): bool
    {
        $time = $chunk->getTimestamp();
        return $time > $this->minTimestamp || ($time === -1 && !$this->removeUnknownChunks);
    }

    /**
     * @return int
     */
    public function getMinTimestamp(): int
    {
        return $this->minTimestamp;
    }

    /**
     * @param int $minTimestamp
     * @return $this
     */
    public function setMinTimestamp(int $minTimestamp): TimestampPattern
    {
        $this->minTimestamp = $minTimestamp;
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
    public function setRemoveUnknownChunks(bool $removeUnknownChunks): TimestampPattern
    {
        $this->removeUnknownChunks = $removeUnknownChunks;
        return $this;
    }
}
