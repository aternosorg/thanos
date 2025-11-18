<?php

namespace Aternos\Thanos\Pattern\Factory;

use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\World\World;

/**
 * Dynamically creates a chunk pattern based on the world it is applied to
 */
interface WorldPatternFactoryInterface
{
    /**
     * @param World $world
     * @return ChunkPatternInterface
     */
    public function makePattern(World $world): ChunkPatternInterface;
}
