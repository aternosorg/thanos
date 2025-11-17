<?php

namespace Aternos\Thanos\Tests\Pattern;

use Aternos\Nbt\IO\Writer\StringWriter;
use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\LongTag;
use Aternos\Thanos\Mca\Entry\McaEntry;
use Aternos\Thanos\Pattern\InhabitedTimePattern;
use Aternos\Thanos\World\Chunk;

class InhabitedTimePatternTest extends PatternTestCase
{
    protected function makeChunkWithInhabitedTime(?int $time): Chunk
    {
        if ($time !== null) {
            $compound = new CompoundTag();
            $compound["InhabitedTime"] = new LongTag()->setValue($time);
            $writer = new StringWriter();
            $compound->write($writer);
            $nbtData = $writer->getStringData();
        } else {
            $nbtData = "testdata";
        }

        $entryData = $this->getDataFile($this->makeEntry($nbtData));
        $entry = new McaEntry($entryData, 0, 40964, 0, 0);
        return new Chunk($entry, null, null, 0, 0);
    }

    public function testInhabitedTimePattern(): void
    {
        $pattern = new InhabitedTimePattern(10, false);
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(0, 0), null, null, 0, 0)));
        $this->assertTrue($pattern->matches(new Chunk($this->chunkReader->getChunkAt(1, 1), null, null, 0, 0)));
        $this->assertFalse($pattern->matches(new Chunk($this->chunkReader->getChunkAt(10, 10), null, null, 0, 0)));

        $this->assertFalse($pattern->matches($this->makeChunkWithInhabitedTime(5)));
        $this->assertTrue($pattern->matches($this->makeChunkWithInhabitedTime(50)));
        $this->assertTrue($pattern->matches($this->makeChunkWithInhabitedTime(null))); // No InhabitedTime tag
    }

    public function testDeleteUnknown(): void
    {
        $pattern = new InhabitedTimePattern(10, true);
        $this->assertFalse($pattern->matches($this->makeChunkWithInhabitedTime(null))); // No InhabitedTime tag
    }
}
