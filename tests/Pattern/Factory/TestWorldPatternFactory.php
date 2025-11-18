<?php

namespace Aternos\Thanos\Tests\Pattern\Factory;

use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\Factory\WorldPatternFactoryInterface;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\World\World;

class TestWorldPatternFactory implements WorldPatternFactoryInterface
{

    /**
     * @inheritDoc
     */
    public function makePattern(World $world): ChunkPatternInterface
    {
        return new ListPattern([[42, 42]]);
    }
}
