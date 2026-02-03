<?php

namespace Aternos\Thanos\Pattern\Factory;

use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\Thanos\Pattern\ChunkPatternInterface;

/**
 * Dynamically creates a chunk pattern based on the dimension it is applied to
 */
interface DimensionPatternFactoryInterface
{
    /**
     * @param GetPathInterface&DirectoryInterface $location
     * @return ChunkPatternInterface
     */
    public function makePattern(DirectoryInterface&GetPathInterface $location): ChunkPatternInterface;
}
