<?php

namespace Aternos\Thanos;

use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\LongArrayTag;
use Aternos\Thanos\RegionDirectory\AnvilRegionDirectory;
use Aternos\Thanos\World\AnvilWorld;
use Exception;

/**
 * Class Thanos
 * Automatically delete chunks from Minecraft worlds
 *
 * @package Aternos\Thanos
 */
class Thanos
{

    /**
     * @var int
     */
    protected int $minInhabitedTime = 0;

    protected bool $removeUnknownChunks = false;

    /**
     * @param int $minInhabitedTime
     */
    public function setMinInhabitedTime(int $minInhabitedTime): void
    {
        $this->minInhabitedTime = $minInhabitedTime;
    }

    /**
     * @return int $maxInhabitedTime
     */
    public function getMinInhabitedTime(): int
    {
        return $this->minInhabitedTime;
    }

    /**
     * Remove all unused chunks
     *
     * @param AnvilWorld $world
     * @return int
     * @throws Exception
     */
    public function snap(AnvilWorld $world): int
    {
        $world->copyOtherFiles();
        $removedChunks = 0;

        foreach ($world->getRegionDirectories() as $regionDirectory) {
            $forcedChunks = $this->getForceLoadedChunks($regionDirectory);
            foreach ($regionDirectory as $chunk) {
                if(in_array([$chunk->getGlobalXPos(), $chunk->getGlobalYPos()], $forcedChunks, true)) {
                    $chunk->save();
                    continue;
                }
                $time = $chunk->getInhabitedTime();
                if ($time > $this->minInhabitedTime || ($time === -1 && !$this->removeUnknownChunks)) {
                    $chunk->save();
                } else {
                    $removedChunks++;
                }
            }
        }

        return $removedChunks;
    }

    /**
     * Get all force-loaded chunks
     * If a chunk was manually loaded, it should not be removed
     *
     * @param AnvilRegionDirectory $regionDirectory
     * @return array
     * @throws Exception
     */
    protected function getForceLoadedChunks(AnvilRegionDirectory $regionDirectory): array
    {
        $chunksDat = $regionDirectory->readDataFile("chunks.dat");
        if(!$chunksDat instanceof CompoundTag) {
            return [];
        }

        $data = $chunksDat->getCompound("data");
        if($data === null) {
            return [];
        }

        $list = $data->getLongArray("Forced");
        if($list === null) {
            return [];
        }

        $data = $list->getRawValue();
        $coordinates = [];
        $currentCoordinate = [];
        for($i = 0; $i < count($list)*2; $i++) {
            $currentCoordinate[] = unpack("N", $data, $i*4)[1] << 32 >> 32;
            if($i % 2 === 1) {
                $coordinates[] = $currentCoordinate;
                $currentCoordinate = [];
            }
        }
        return $coordinates;
    }

    /**
     * @param bool $removeUnknownChunks
     */
    public function setRemoveUnknownChunks(bool $removeUnknownChunks): void
    {
        $this->removeUnknownChunks = $removeUnknownChunks;
    }

    /**
     * @return bool
     */
    public function isRemoveUnknownChunks(): bool
    {
        return $this->removeUnknownChunks;
    }
}
