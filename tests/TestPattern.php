<?php

namespace Aternos\Thanos\Tests;

use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\World\Chunk;

class TestPattern implements ChunkPatternInterface
{
    public array $chunks = [];
    protected int $i = 0;

    public function __construct(
        protected int $keepChunks = PHP_INT_MAX,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function matches(Chunk $chunk): bool
    {
        $this->chunks[] = $chunk;
        $this->i++;
        return $this->i <= $this->keepChunks;
    }
}
