<?php

namespace Aternos\Thanos\Tests\World;

use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\System\Directory\Directory;
use Aternos\IO\System\Directory\TempDirectory;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\IO\System\Link\Link;
use Aternos\Thanos\Pattern\Factory\ForceLoadedChunkPatternFactory;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\Pattern\RangePattern;
use Aternos\Thanos\Task\CopyFileTask;
use Aternos\Thanos\Task\CreateDirectoryTask;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\Task\ThanosTask;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\Util\PathPair;
use Aternos\Thanos\World\DimensionTaskGenerator;
use Generator;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionClass;

class DimensionTaskGeneratorTest extends ThanosTestCase
{
    protected function normalizePath(string $path): string
    {
        $path = trim($path, "/");
        $path = preg_replace("#/+#", "/", $path);
        return $path;
    }

    protected function getSourcePaths(ThanosTask $task, string $basePath): array
    {
        $basePath = $this->normalizePath($basePath) . "/";

        $reflection = new ReflectionClass($task);
        if ($task instanceof CopyFileTask) {
            return [
                substr($this->normalizePath($reflection->getProperty('paths')->getValue($task)->getSource()), strlen($basePath))
            ];
        }

        if ($task instanceof ProcessRegionTask) {
            /** @var PathPair $chunkRegion */
            $chunkRegion = $reflection->getProperty('chunkRegion')->getValue($task);
            /** @var PathPair|null $entityRegion */
            $entityRegion = $reflection->getProperty('entityRegion')->getValue($task);
            /** @var PathPair|null $poiRegion */
            $poiRegion = $reflection->getProperty('poiRegion')->getValue($task);
            $results = [];
            foreach ([$chunkRegion, $entityRegion, $poiRegion] as $region) {
                if ($region === null) {
                    continue;
                }
                $results[] = substr($this->normalizePath($region->getSource()), strlen($basePath));
            }
            return $results;
        }

        return [];
    }

    protected function getDestinationPaths(ThanosTask $task, string $basePath): array
    {
        $basePath = $this->normalizePath($basePath) . "/";

        $reflection = new ReflectionClass($task);
        if ($task instanceof CopyFileTask) {
            return [
                substr($this->normalizePath($reflection->getProperty('paths')->getValue($task)->getDestination()), strlen($basePath))
            ];
        }

        if ($task instanceof ProcessRegionTask) {
            /** @var PathPair $chunkRegion */
            $chunkRegion = $reflection->getProperty('chunkRegion')->getValue($task);
            /** @var PathPair|null $entityRegion */
            $entityRegion = $reflection->getProperty('entityRegion')->getValue($task);
            /** @var PathPair|null $poiRegion */
            $poiRegion = $reflection->getProperty('poiRegion')->getValue($task);
            $results = [];
            foreach ([$chunkRegion, $entityRegion, $poiRegion] as $region) {
                if ($region === null) {
                    continue;
                }
                $results[] = substr($this->normalizePath($region->getDestination()), strlen($basePath));
            }
            return $results;
        }

        if ($task instanceof CreateDirectoryTask) {
            return [
                substr($this->normalizePath($reflection->getProperty('path')->getValue($task)), strlen($basePath))
            ];
        }

        return [];
    }

    protected function getWorldFiles(bool $includeDirectories = false, string $worldPath = self::TEST_WORLD): array
    {
        $directory = new Directory($worldPath);
        $files = [];
        foreach ($directory->getChildrenRecursive() as $child) {
            if (!$child instanceof GetPathInterface) {
                continue;
            }
            if (!$includeDirectories && $child instanceof Directory) {
                continue;
            }
            $files[] = $this->normalizePath($child->getRelativePathTo($directory));
        }
        return $files;
    }

    #[TestWith([self::TEST_WORLD])]
    #[TestWith([self::TEST_WORLD_26_1])]
    #[TestWith([self::TEST_LEGACY_FORCELOAD])]
    public function testGenerateTasksForEachSourceFile(string $worldPath): void
    {
        $generator = new DimensionTaskGenerator([]);
        $tasks = $generator->generateTasks(new Directory($worldPath), new Directory("/tmp/"));
        $sourcePaths = [];
        foreach ($tasks as $task) {
            array_push($sourcePaths, ...$this->getSourcePaths($task, $worldPath));
        }

        $worldFiles = $this->getWorldFiles(worldPath: $worldPath);

        $this->assertEquals(count($worldFiles), count($sourcePaths), "Number of world files and generated tasks' source paths do not match.");
        foreach ($worldFiles as $worldFile) {
            $this->assertContains($worldFile, $sourcePaths, "World file '" . $worldFile . "' is missing in generated tasks' source paths.");
        }
    }

    #[TestWith([self::TEST_WORLD])]
    #[TestWith([self::TEST_WORLD_26_1])]
    #[TestWith([self::TEST_LEGACY_FORCELOAD])]
    public function testGenerateTasksForEachDestinationFile(string $worldPath): void
    {
        $generator = new DimensionTaskGenerator([]);
        $tasks = $generator->generateTasks(new Directory($worldPath), new Directory("/tmp/"));
        $destinationPaths = [];
        foreach ($tasks as $task) {
            array_push($destinationPaths, ...$this->getDestinationPaths($task, "/tmp/"));
        }

        $worldFiles = $this->getWorldFiles(true, $worldPath);
        foreach ($worldFiles as $worldFile) {
            $this->assertContains($worldFile, $destinationPaths, "World file '" . $worldFile . "' is missing in generated tasks' destination paths.");
        }
    }

    public function testGenerateCopyTasksSkipLinks(): void
    {
        $generator = new DimensionTaskGenerator([]);
        $reflection = new ReflectionClass($generator);

        $source = new TempDirectory();
        $target = new TempDirectory();

        $link = new Link($source->getPath() . "/test")->setTarget(new Directory(static::TEST_WORLD));

        $tasks = iterator_to_array($reflection->getMethod("copyFilesInDirectory")->invoke($generator, $source, $target));

        $link->delete();
        $target->delete();
        $source->delete();

        $this->assertCount(0, $tasks, "No tasks should be generated for links.");
    }

    public function testSkipInvalidChildren(): void
    {
        $generator = new DimensionTaskGenerator([]);

        $testDir = new class(static::TEST_DATA) extends Directory
        {
            public function getChildren(bool $allowOutsideLinks = false): Generator
            {
                yield new TempMemoryFile();
            }
        };

        $tasks = iterator_to_array($generator->generateTasks($testDir, new TempDirectory()));
        $this->assertCount(1, $tasks, "Only the directory creation task should be generated.");

        $task = $tasks[0];
        $this->assertInstanceOf(CreateDirectoryTask::class, $task);
    }

    public function testGetMcaFileReturnsNullIfDirectoryIsNull(): void
    {
        $generator = new DimensionTaskGenerator([]);
        $reflection = new ReflectionClass($generator);

        $this->assertNull($reflection->getMethod("getMcaFile")->invoke($generator, null, "r.0.0.mca"));
    }

    public function testRunDimensionPatternFactories(): void
    {
        $generator = new DimensionTaskGenerator([new ForceLoadedChunkPatternFactory()]);
        $tasks = $generator->generateTasks(new Directory(static::TEST_WORLD), new Directory("/tmp/"));
        foreach ($tasks as $task) {
            if (!$task instanceof ProcessRegionTask) {
                continue;
            }
            $patterns = new ReflectionClass($task)->getProperty('patterns')->getValue($task);
            $this->assertCount(1, $patterns);
            $this->assertInstanceOf(ListPattern::class, $patterns[0]);
        }
    }

    public function testAddPatternsToProcessTasks(): void
    {
        $pattern = new RangePattern(0, 0, 10, 10);
        $generator = new DimensionTaskGenerator([$pattern]);
        $tasks = $generator->generateTasks(new Directory(static::TEST_WORLD), new Directory("/tmp/"));
        foreach ($tasks as $task) {
            if (!$task instanceof ProcessRegionTask) {
                continue;
            }
            $patterns = new ReflectionClass($task)->getProperty('patterns')->getValue($task);
            $this->assertCount(1, $patterns);
            $this->assertSame($pattern, $patterns[0]);
        }
    }
}
