<?php

namespace Aternos\Thanos\Pattern\Factory;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\ExistsInterface;
use Aternos\IO\Interfaces\Features\GetSizeInterface;
use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\Nbt\IO\Reader\GZipCompressedStringReader;
use Aternos\Nbt\NbtFormat;
use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\LongArrayTag;
use Aternos\Nbt\Tag\Tag;
use Aternos\Nbt\Tag\TagType;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\World\DimensionTaskGenerator;
use Exception;

class ForceLoadedChunkPatternFactory implements DimensionPatternFactoryInterface
{

    /**
     * @inheritDoc
     */
    public function makePattern(DimensionTaskGenerator $dimension): ChunkPatternInterface
    {
        try {
            $coordinates = $this->getForceLoadedChunks($dimension->getSource());
        } catch (Exception) {
            $coordinates = [];
        }
        return new ListPattern($coordinates);
    }

    /**
     * @param DirectoryInterface $dimension
     * @return int[][]
     * @throws IOException
     * @throws Exception
     */
    protected function getForceLoadedChunks(DirectoryInterface $dimension): array
    {
        /** @var ExistsInterface&ReadInterface&IsEndOfFileInterface&GetSizeInterface $dataFile */
        $dataFile = $dimension->getChild("data/chunks.dat", ExistsInterface::class, ReadInterface::class, IsEndOfFileInterface::class);
        if (!$dataFile->exists()) {
            return [];
        }

        $content = $dataFile->read($dataFile->getSize());
        $reader = new GZipCompressedStringReader($content, NbtFormat::JAVA_EDITION);
        $tag = Tag::load($reader);

        return $this->getForceLoadedChunksFromTag($tag);
    }

    /**
     * @param Tag $tag
     * @return int[][]
     */
    protected function getForceLoadedChunksFromTag(Tag $tag): array
    {
        if (!$tag instanceof CompoundTag) {
            return [];
        }

        $data = $tag->getCompound("data");
        if ($data === null) {
            return [];
        }

        $coordinates = [];
        $list = $data->getLongArray("Forced");
        if ($list !== null) {
            $coordinates = $this->parseLegacyForgeLoadedChunks($list);
        }

        $tickets = $data->getList("tickets", TagType::TAG_Compound);
        if ($tickets === null) {
            return $coordinates;
        }

        /** @var CompoundTag $ticket */
        foreach ($tickets as $ticket) {
            if ($ticket->getString("type")?->getValue() !== "minecraft:forced") {
                continue;
            }
            $position = $ticket->getIntArray("chunk_pos");
            if ($position === null || count($position) !== 2) {
                continue;
            }
            $coordinates[] = [$position[0], $position[1]];
        }

        return $coordinates;
    }

    /**
     * @param LongArrayTag $list
     * @return int[][]
     */
    protected function parseLegacyForgeLoadedChunks(LongArrayTag $list): array
    {
        $data = $list->getRawValue();
        $coordinates = [];
        $currentCoordinate = [];
        for ($i = 0; $i < count($list) * 2; $i++) {
            $currentCoordinate[] = unpack("N", $data, $i * 4)[1] << 32 >> 32;
            if ($i % 2 === 1) {
                $coordinates[] = $currentCoordinate;
                $currentCoordinate = [];
            }
        }
        return $coordinates;
    }
}
