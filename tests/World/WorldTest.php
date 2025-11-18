<?php

namespace Aternos\Thanos\Tests\World;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\Directory\Directory;
use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\Tests\Pattern\Factory\TestWorldPatternFactory;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\World\World;
use Generator;
use ReflectionClass;

class WorldTest extends ThanosTestCase
{
    public function testGetSource(): void
    {
        $world = World::open(static::TEST_WORLD);
        $source = $world->getSource();
        $this->assertEquals(static::TEST_WORLD, $source->getPath());
    }

    public function testRunWorldPatternFactories(): void
    {
        $world = World::open(static::TEST_WORLD);
        $taskFactory = $world->getTaskFactory([new TestWorldPatternFactory()], $world->getSource());

        while (($task = $taskFactory->createNextTask(null)) !== null) {
            if (!$task instanceof ProcessRegionTask) {
                continue;
            }
            $reflection = new ReflectionClass($task);
            $patterns = $reflection->getProperty('patterns')->getValue($task);
            $this->assertCount(1, $patterns);
            $this->assertInstanceOf(ListPattern::class, $patterns[0]);
        }
    }
}
