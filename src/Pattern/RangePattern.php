<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Thanos\Chunk\ChunkInterface;
use Aternos\Thanos\Pattern\ChunkPatternInterface;

class RangePattern implements ChunkPatternInterface
{
    protected int $startX;
    protected int $startY;
    protected int $endX;
    protected int $endY;

    /**
     * @param int $startX
     * @param int $startY
     * @param int $endX
     * @param int $endY
     */
    public function __construct(
        int $startX,
        int $startY,
        int $endX,
        int $endY
    )
    {
        $this->startX = min($startX, $endX);
        $this->startY = min($startY, $endY);
        $this->endX = max($startX, $endX);
        $this->endY = max($startY, $endY);
    }

    /**
     * @inheritDoc
     */
    public function matches(ChunkInterface $chunk): bool
    {
        return $chunk->getGlobalXPos() >= $this->startX
            && $chunk->getGlobalXPos() <= $this->endX
            && $chunk->getGlobalYPos() >= $this->startY
            && $chunk->getGlobalYPos() <= $this->endY;
    }
}
