<?php

namespace Aternos\Thanos\Tests\Task;

use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\Tests\TestPattern;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\Util\PathPair;

class ProcessRegionTaskTest extends ThanosTestCase
{
    protected PathPair $chunkRegion;
    protected PathPair $entityRegion;
    protected PathPair $poiRegion;

    protected function setUp(): void
    {
        parent::setUp();
        $destinationPath = static::TEST_DATA . "/processed_region";
        mkdir($destinationPath);
        $this->chunkRegion = new PathPair(
            static::TEST_WORLD . "/region/r.-1.0.mca",
            $destinationPath . "/region/r.-1.0.mca"
        );
        $this->entityRegion = new PathPair(
            static::TEST_WORLD . "/entities/r.-1.0.mca",
            $destinationPath . "/entities/r.-1.0.mca"
        );
        $this->poiRegion = new PathPair(
            static::TEST_WORLD . "/poi/r.-1.0.mca",
            $destinationPath . "/poi/r.-1.0.mca"
        );
    }

    public function testProcessRegion(): void
    {
        $pattern = new TestPattern();
        $task = new ProcessRegionTask(
            $this->chunkRegion,
            $this->entityRegion,
            $this->poiRegion,
            [$pattern]
        );

        $result = $task->run();
        $this->assertEquals(0, $result);
        $this->assertFileExists($this->chunkRegion->getDestination());
        $this->assertFileExists($this->entityRegion->getDestination());
        $this->assertFileExists($this->poiRegion->getDestination());

        $this->assertCount(650, $pattern->chunks);
    }

    public function testEntitiesAndPoiAreOptional(): void
    {
        $pattern = new TestPattern();
        $task = new ProcessRegionTask(
            $this->chunkRegion,
            null,
            null,
            [$pattern]
        );

        $task->run();
        $this->assertFileExists($this->chunkRegion->getDestination());
        $this->assertFileDoesNotExist($this->entityRegion->getDestination());
        $this->assertFileDoesNotExist($this->poiRegion->getDestination());

        $this->assertCount(650, $pattern->chunks);
    }

    public function testDeleteAllChunks(): void
    {
        $pattern = new TestPattern(0);
        $task = new ProcessRegionTask(
            $this->chunkRegion,
            $this->entityRegion,
            $this->poiRegion,
            [$pattern]
        );

        $result = $task->run();
        $this->assertEquals(650, $result);
        $this->assertFileDoesNotExist($this->chunkRegion->getDestination());
        $this->assertFileDoesNotExist($this->entityRegion->getDestination());
        $this->assertFileDoesNotExist($this->poiRegion->getDestination());

        $this->assertCount(650, $pattern->chunks);
    }

    public function testDeleteSomeChunks(): void
    {
        $pattern = new TestPattern(456);
        $task = new ProcessRegionTask(
            $this->chunkRegion,
            $this->entityRegion,
            $this->poiRegion,
            [$pattern]
        );

        $result = $task->run();
        $this->assertEquals(650 - 456, $result);
        $this->assertFileExists($this->chunkRegion->getDestination());
        $this->assertFileExists($this->entityRegion->getDestination());
        $this->assertFileExists($this->poiRegion->getDestination());

        $this->assertCount(650, $pattern->chunks);
    }

    public function testBaseDirectoryCreationError(): void
    {
        file_put_contents(dirname($this->chunkRegion->getDestination()), "This is a file, not a directory");
        $pattern = new TestPattern();
        $task = new ProcessRegionTask(
            $this->chunkRegion,
            $this->entityRegion,
            $this->poiRegion,
            [$pattern]
        );

        $this->expectException(FileSystemException::class);
        $task->run();
    }
}
