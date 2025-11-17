<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\World\Chunk;

class RangePattern implements ChunkPatternInterface
{
    protected int $startX;
    protected int $startZ;
    protected int $endX;
    protected int $endZ;

    /**
     * @param int $startX
     * @param int $startZ
     * @param int $endX
     * @param int $endZ
     */
    public function __construct(
        int $startX,
        int $startZ,
        int $endX,
        int $endZ
    )
    {
        $this->startX = min($startX, $endX);
        $this->startZ = min($startZ, $endZ);
        $this->endX = max($startX, $endX);
        $this->endZ = max($startZ, $endZ);
    }

    /**
     * @inheritDoc
     */
    public function matches(Chunk $chunk): bool
    {
        return $chunk->getGlobalXPos() >= $this->startX
            && $chunk->getGlobalXPos() <= $this->endX
            && $chunk->getGlobalZPos() >= $this->startZ
            && $chunk->getGlobalZPos() <= $this->endZ;
    }
}
