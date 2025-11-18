<?php

namespace Aternos\Thanos\World;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\Interfaces\IOElementInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\IO\Interfaces\Types\FileInterface;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\Factory\DimensionPatternFactoryInterface;
use Aternos\Thanos\Task\CopyFileTask;
use Aternos\Thanos\Task\CreateDirectoryTask;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\Task\ThanosTask;
use Aternos\Thanos\Util\PathPair;
use Generator;

class DimensionTaskGenerator
{
    /**
     * @param IOElementInterface $directory
     * @return bool
     * @throws IOException
     */
    public static function isDimension(IOElementInterface $directory): bool
    {
        if (!$directory instanceof DirectoryInterface || !$directory instanceof GetPathInterface) {
            return false;
        }

        foreach ($directory->getChildren() as $child) {
            if ($child instanceof DirectoryInterface && $child->getName() === "region") {
                return true;
            }
        }

        return false;
    }

    /**
     * @param GetPathInterface&DirectoryInterface $source
     * @param GetPathInterface&DirectoryInterface $target
     * @param (ChunkPatternInterface|DimensionPatternFactoryInterface)[] $patterns
     */
    public function __construct(
        protected DirectoryInterface&GetPathInterface $source,
        protected DirectoryInterface&GetPathInterface $target,
        protected array                               $patterns = [],
    )
    {
    }

    /**
     * @return Generator
     * @throws IOException
     */
    public function generateTasks(): Generator
    {
        yield new CreateDirectoryTask($this->target->getPath());

        $region = null;
        $entities = null;
        $poi = null;
        foreach ($this->source->getChildren() as $child) {
            if (!$child instanceof GetPathInterface) {
                continue;
            }
            $name = $child->getName();
            if ($name === "region" && $child instanceof DirectoryInterface) {
                $region = $child;
                continue;
            } else if ($name === "entities" && $child instanceof DirectoryInterface) {
                $entities = $child;
                continue;
            } else if ($name === "poi" && $child instanceof DirectoryInterface) {
                $poi = $child;
                continue;
            }

            if (static::isDimension($child)) {
                /** @var DirectoryInterface&GetPathInterface $child */
                /** @var DirectoryInterface&GetPathInterface $newTarget */
                $newTarget = $this->target->getChild($child->getName(), DirectoryInterface::class, GetPathInterface::class);
                yield from new DimensionTaskGenerator($child, $newTarget, $this->patterns)->generateTasks();
                continue;
            }

            yield from $this->generateCopyTasks($child);
        }

        yield from $this->generateRegionTasks(
            $region,
            $entities,
            $poi,
        );
    }

    /**
     * @param (GetPathInterface&DirectoryInterface)|null $region
     * @param (GetPathInterface&DirectoryInterface)|null $entities
     * @param (GetPathInterface&DirectoryInterface)|null $poi
     * @return Generator
     * @throws IOException
     */
    protected function generateRegionTasks(
        (DirectoryInterface&GetPathInterface)|null $region,
        (DirectoryInterface&GetPathInterface)|null $entities,
        (DirectoryInterface&GetPathInterface)|null $poi,
    ): Generator
    {
        $patterns = [];
        foreach ($this->patterns as $pattern) {
            if ($pattern instanceof DimensionPatternFactoryInterface) {
                $patterns[] = $pattern->makePattern($this);
            } else {
                $patterns[] = $pattern;
            }
        }

        $processedRegions = [];
        $processedEntities = [];
        $processedPoi = [];
        if ($region !== null) {
            foreach ($region->getChildren() as $regionFile) {
                if (!$regionFile instanceof GetPathInterface) {
                    continue;
                }

                if (!$regionFile instanceof FileInterface ||
                    !preg_match(McaReader::MCA_FILE_PATTERN, $regionFile->getName()) ||
                    $regionFile->getSize() === 0
                ) {
                    continue;
                }

                $processedRegions[] = $regionFile->getName();
                $entitiesFile = $this->getMcaFile($entities, $regionFile->getName());
                if ($entitiesFile !== null) {
                    $processedEntities[] = $entitiesFile->getName();
                }
                $poiFile = $this->getMcaFile($poi, $regionFile->getName());
                if ($poiFile !== null) {
                    $processedPoi[] = $poiFile->getName();
                }
                yield new ProcessRegionTask(
                    $this->getMcaFilePathPair($regionFile),
                    $this->getMcaFilePathPair($entitiesFile),
                    $this->getMcaFilePathPair($poiFile),
                    $patterns
                );
            }
        }

        yield from $this->generateRemainingFilesInRegionDirectory($region, $processedRegions);
        yield from $this->generateRemainingFilesInRegionDirectory($entities, $processedEntities);
        yield from $this->generateRemainingFilesInRegionDirectory($poi, $processedPoi);
    }

    /**
     * @param (GetPathInterface&DirectoryInterface)|null $directory
     * @param array $alreadyProcessed
     * @return Generator
     * @throws IOException
     */
    protected function generateRemainingFilesInRegionDirectory(
        (DirectoryInterface&GetPathInterface)|null $directory,
        array                                      $alreadyProcessed
    ): Generator
    {
        if ($directory === null) {
            return;
        }

        /** @var DirectoryInterface&GetPathInterface $targetDirectory */
        $targetDirectory = $this->target->getChild(
            $directory->getRelativePathTo($this->source),
            DirectoryInterface::class,
            GetPathInterface::class
        );
        yield new CreateDirectoryTask($targetDirectory->getPath());

        foreach ($directory->getChildren() as $child) {
            if (!$child instanceof GetPathInterface) {
                continue;
            }
            if (in_array($child->getName(), $alreadyProcessed, true)) {
                continue;
            }
            yield from $this->generateCopyTasks($child);
        }
    }

    /**
     * @param GetPathInterface|null $source
     * @return PathPair|null
     * @throws IOException
     */
    protected function getMcaFilePathPair(?GetPathInterface $source): ?PathPair
    {
        if ($source === null) {
            return null;
        }

        /** @var GetPathInterface $target */
        $target = $this->target->getChild($source->getRelativePathTo($this->source), GetPathInterface::class);

        return new PathPair(
            $source->getPath(),
            $target->getPath()
        );
    }

    /**
     * @param DirectoryInterface|null $directory
     * @param string $filename
     * @return (FileInterface&GetPathInterface)|null
     * @throws IOException
     */
    protected function getMcaFile(?DirectoryInterface $directory, string $filename): (FileInterface&GetPathInterface)|null
    {
        if ($directory === null) {
            return null;
        }

        /** @var FileInterface&GetPathInterface $file */
        $file = $directory->getChild($filename, FileInterface::class, GetPathInterface::class);
        if (!$file->exists() || $file->getSize() === 0) {
            return null;
        }

        return $file;
    }

    /**
     * @param GetPathInterface $source
     * @return Generator<ThanosTask>
     * @throws IOException
     */
    protected function generateCopyTasks(GetPathInterface $source): Generator
    {
        $relativePath = $source->getRelativePathTo($this->source);
        /** @var GetPathInterface $targetPath */
        $targetPath = $this->target->getChild($relativePath, GetPathInterface::class);
        if ($source instanceof FileInterface) {
            yield new CopyFileTask(new PathPair(
                $source->getPath(),
                $targetPath->getPath()
            ));
            return;
        }

        if (!$source instanceof DirectoryInterface) {
            return;
        }

        yield new CreateDirectoryTask($targetPath->getPath());
        foreach ($source->getChildren() as $child) {
            if (!$child instanceof GetPathInterface) {
                continue;
            }
            yield from $this->generateCopyTasks($child);
        }
    }

    /**
     * @return GetPathInterface&DirectoryInterface
     */
    public function getSource(): GetPathInterface&DirectoryInterface
    {
        return $this->source;
    }
}
