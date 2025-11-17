<?php

namespace Aternos\Thanos\Tests\Pattern;

use Aternos\Thanos\Pattern\RangePattern;
use Aternos\Thanos\World\Chunk;

class RangePatternTest extends PatternTestCase
{
    public function testRangePattern(): void
    {
        $pattern = new RangePattern(1, 1, 3, 3);
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 1), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 2), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 3), null, null, 0, 0)));
        $this->assertFalse($pattern->matches(new Chunk($this->chunkReader->getChunkAt(0, 2), null, null, 0, 0)));
    }

    public function testInvertedPattern(): void
    {
        $pattern = new RangePattern(3, 3, 1, 1);
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 1), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 2), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 3), null, null, 0, 0)));
        $this->assertFalse($pattern->matches(new Chunk($this->chunkReader->getChunkAt(0, 2), null, null, 0, 0)));
    }
}
