<?php

namespace Aternos\Thanos\Tests\Pattern;

use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\World\Chunk;

class ListPatternTest extends PatternTestCase
{
    public function testListPattern(): void
    {
        $pattern = new ListPattern([[1, 1], [2, 2], [3, 3]]);
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 1), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(2, 2), null, null, 0, 0)));
        $this->assertFalse($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 2), null, null, 0, 0)));
    }
}
