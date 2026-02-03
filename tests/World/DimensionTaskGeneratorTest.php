<?php

namespace Aternos\Thanos\Tests\World;

use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\System\Directory\Directory;
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
use Aternos\Thanos\World\OldDimensionTaskGenerator;
use Generator;
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

    protected function getWorldFiles(bool $includeDirectories = false): array
    {
        $directory = new Directory(static::TEST_WORLD);
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

    public function testGenerateTasksForEachSourceFile(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_WORLD), new Directory("/tmp/"), []);
        $tasks = $generator->generateTasks();
        $sourcePaths = [];
        foreach ($tasks as $task) {
            array_push($sourcePaths, ...$this->getSourcePaths($task, static::TEST_WORLD));
        }

        $worldFiles = $this->getWorldFiles();
        $this->assertEquals(count($worldFiles), count($sourcePaths), "Number of world files and generated tasks' source paths do not match.");
        foreach ($worldFiles as $worldFile) {
            $this->assertContains($worldFile, $sourcePaths, "World file '" . $worldFile . "' is missing in generated tasks' source paths.");
        }
    }

    public function testGenerateTasksForEachDestinationFile(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_WORLD), new Directory("/tmp/"), []);
        $tasks = $generator->generateTasks();
        $sourcePaths = [];
        foreach ($tasks as $task) {
            array_push($sourcePaths, ...$this->getDestinationPaths($task, "/tmp/"));
        }

        $worldFiles = $this->getWorldFiles(true);
        foreach ($worldFiles as $worldFile) {
            $this->assertContains($worldFile, $sourcePaths, "World file '" . $worldFile . "' is missing in generated tasks' source paths.");
        }
    }

    public function testGenerateCopyTasksSkipLinks(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $link = new Link(static::TEST_DATA . "/link")->setTarget(new Directory(static::TEST_DATA));
        $tasks = iterator_to_array($reflection->getMethod("generateCopyTasks")->invoke($generator, $link));
        $this->assertCount(0, $tasks, "No tasks should be generated for links.");
    }

    public function testGenerateCopyTasksSkipInvalidChildren(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $testDir = new class(static::TEST_DATA) extends Directory
        {
            public function getChildren(bool $allowOutsideLinks = false): Generator
            {
                yield new TempMemoryFile();
            }
        };

        $tasks = iterator_to_array($reflection->getMethod("generateCopyTasks")->invoke($generator, $testDir));
        $this->assertCount(1, $tasks, "Only the directory creation task should be generated.");

        $task = $tasks[0];
        $this->assertInstanceOf(CreateDirectoryTask::class, $task);
    }

    public function testGetMcaFileReturnsNullIfDirectoryIsNull(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $this->assertNull($reflection->getMethod("getMcaFile")->invoke($generator, null, "r.0.0.mca"));
    }

    public function testGenerateRemainingFilesInRegionDirectorySkipsMissingDirectories(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $tasks = iterator_to_array($reflection->getMethod("generateRemainingFilesInRegionDirectory")->invoke($generator, null, []));
        $this->assertCount(0, $tasks);
    }

    public function testGenerateRemainingFilesInRegionDirectorySkipsInvalidChildren(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $testDir = new class(static::TEST_DATA) extends Directory
        {
            public function getChildren(bool $allowOutsideLinks = false): Generator
            {
                yield new TempMemoryFile();
            }
        };

        $tasks = iterator_to_array($reflection->getMethod("generateRemainingFilesInRegionDirectory")->invoke($generator, $testDir, []));
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertInstanceOf(CreateDirectoryTask::class, $task);
    }

    public function testGenerateRegionTasksSkipsInvalidChildren(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_DATA), new Directory("/tmp/"), []);
        $reflection = new ReflectionClass($generator);

        $testDir = new class(static::TEST_DATA) extends Directory
        {
            public function getChildren(bool $allowOutsideLinks = false): Generator
            {
                yield new TempMemoryFile();
            }
        };

        $tasks = iterator_to_array($reflection->getMethod("generateRegionTasks")->invoke($generator, $testDir, null, null));
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertInstanceOf(CreateDirectoryTask::class, $task);
    }

    public function testGenerateTasksSkipsInvalidChildren(): void
    {
        $testDir = new class(static::TEST_DATA) extends Directory
        {
            public function getChildren(bool $allowOutsideLinks = false): Generator
            {
                yield new TempMemoryFile();
            }
        };

        $generator = new OldDimensionTaskGenerator($testDir, new Directory("/tmp/"), []);

        $tasks = iterator_to_array($generator->generateTasks());
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertInstanceOf(CreateDirectoryTask::class, $task);
    }

    public function testGetSource(): void
    {
        $source = new Directory(static::TEST_DATA);
        $generator = new OldDimensionTaskGenerator($source, new Directory("/tmp/"), []);
        $this->assertSame($source, $generator->getSource());
    }

    public function testRunDimensionPatternFactories(): void
    {
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_WORLD), new Directory("/tmp/"), [new ForceLoadedChunkPatternFactory()]);
        $tasks = $generator->generateTasks();
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
        $generator = new OldDimensionTaskGenerator(new Directory(static::TEST_WORLD), new Directory("/tmp/"), [$pattern]);
        $tasks = $generator->generateTasks();
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
