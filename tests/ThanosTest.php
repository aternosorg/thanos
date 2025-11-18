<?php

namespace Aternos\Thanos\Tests;

use Aternos\IO\System\Directory\Directory;
use Aternos\Thanos\Exception\SnapException;
use Aternos\Thanos\Pattern\InhabitedTimePattern;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\Pattern\RangePattern;
use Aternos\Thanos\Thanos;
use Aternos\Thanos\World\World;

class ThanosTest extends ThanosTestCase
{
    public function testGettersAndSetters(): void
    {
        $thanos = new Thanos([]);

        $thanos->setPatterns([new ListPattern([[1, 2]])]);
        $thanos->addPattern(new RangePattern(1, 1, 4, 4));
        $patterns = $thanos->getPatterns();
        $this->assertCount(2, $patterns);
        $this->assertInstanceOf(ListPattern::class, $patterns[0]);
        $this->assertInstanceOf(RangePattern::class, $patterns[1]);

        $thanos->setDefaultWorkerCount(10);
        $this->assertEquals(10, $thanos->getDefaultWorkerCount());
        $thanos->setDefaultTaskTimeout(120.5);
        $this->assertEquals(120.5, $thanos->getDefaultTaskTimeout());
    }

    public function testSnap(): void
    {
        $thanos = new Thanos([new InhabitedTimePattern(0, false)]);
        $world = World::open(static::TEST_WORLD);
        $target = new Directory(static::TEST_DATA . "/snap_target")->create();
        $deletedChunks = $thanos->snap($world, $target);
        $this->assertEquals(3660, $deletedChunks);
    }

    public function testSnapError(): void
    {
        $target = new Directory(static::TEST_DATA . "/snap_target")->create();
        file_put_contents(static::TEST_DATA . "/snap_target/region", "This is a file, not a directory");

        $thanos = new Thanos([new InhabitedTimePattern(0, false)]);
        $world = World::open(static::TEST_WORLD);

        $this->expectException(SnapException::class);
        $thanos->snap($world, $target);
    }
}
