<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Nbt\Tag\LongTag;
use Aternos\Thanos\Exception\McaExceptionInterface;
use Aternos\Thanos\World\Chunk;

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
     * @throws McaExceptionInterface
     */
    public function matches(Chunk $chunk): bool
    {
        $tag = $chunk->findChunkTag(LongTag::class, "InhabitedTime", 8);
        if ($tag === null) {
            return !$this->removeUnknownChunks;
        }
        return $tag->getValue() > $this->inhabitedTimeThreshold;
    }
}
