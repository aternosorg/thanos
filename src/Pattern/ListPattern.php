<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\Chunk\ChunkInterface;

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
    public function matches(ChunkInterface $chunk): bool
    {
        return in_array([$chunk->getGlobalXPos(), $chunk->getGlobalYPos()], $this->chunks, true);
    }
}
