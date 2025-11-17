<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\World\Chunk;

class ListPattern implements ChunkPatternInterface
{
    /**
     * @param int[][] $chunks
     */
    public function __construct(
        protected array $chunks
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function matches(Chunk $chunk): bool
    {
        return in_array([$chunk->getGlobalXPos(), $chunk->getGlobalZPos()], $this->chunks, true);
    }
}
