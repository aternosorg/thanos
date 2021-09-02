<?php

namespace Aternos\Thanos;

use Aternos\Nbt\Tag\LongArrayTag;
use Aternos\Thanos\RegionDirectory\AnvilRegionDirectory;
use Aternos\Thanos\World\AnvilWorld;

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
        if(!isset($chunksDat["data"]) || !isset($chunksDat["data"]["Forced"])) {
            return [];
        }

        $list = $chunksDat["data"]["Forced"];
        if(!($list instanceof LongArrayTag)) {
            return [];
        }

        $data = $list->getRawValue();
        $coords = [];
        $coord = [];
        for($i = 0; $i < count($list)*2; $i++) {
            $coord[] = unpack("N", $data, $i*4)[1] << 32 >> 32;
            if($i % 2 === 1) {
                $coords[] = $coord;
                $coord = [];
            }
        }
        return $coords;
    }
}
