<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\World\Chunk;

interface ChunkPatternInterface
{
    /**
     * @param Chunk $chunk
     * @return bool
     */
    public function matches(Chunk $chunk): bool;
}
