<?php

namespace Aternos\Thanos;

use Aternos\Thanos\RegionDirectory\AnvilRegionDirectory;
use Aternos\Thanos\World\AnvilWorld;
use Nbt\Tag;

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
    protected $minInhabitedTime = 0;

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
     * @throws \Exception
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
                if ($time > $this->minInhabitedTime || $time === -1) {
                    $chunk->save();
                } else {
                    $removedChunks++;
                }
            }
        }

        return $removedChunks;
    }

    protected function getForceLoadedChunks(AnvilRegionDirectory $regionDirectory): array
    {
        $chunksDat = $regionDirectory->readDataFile("chunks.dat");
        if(is_null($chunksDat)) {
            return [];
        }
        $dataTag = $chunksDat->findChildByName("data");
        if(!$dataTag) {
            return [];
        }
        $forcedChunks = $dataTag->findChildByName("Forced");
        if(!$forcedChunks || $forcedChunks->getType() !== Tag::TAG_LONG_ARRAY) {
            return [];
        }
        return $forcedChunks->getValue();
    }
}
