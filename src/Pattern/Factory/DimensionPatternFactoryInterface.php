<?php

namespace Aternos\Thanos\Pattern\Factory;

use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\World\DimensionTaskGenerator;

/**
 * Dynamically creates a chunk pattern based on the dimension it is applied to
 */
interface DimensionPatternFactoryInterface
{
    /**
     * @param DimensionTaskGenerator $dimension
     * @return ChunkPatternInterface
     */
    public function makePattern(DimensionTaskGenerator $dimension): ChunkPatternInterface;
}
