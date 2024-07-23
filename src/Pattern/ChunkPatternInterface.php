<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\Chunk\ChunkInterface;

interface ChunkPatternInterface
{
    /**
     * @param ChunkInterface $chunk
     * @return bool
     */
    public function matches(ChunkInterface $chunk): bool;
}
