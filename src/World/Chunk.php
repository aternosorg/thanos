<?php

namespace Aternos\Thanos\World;

use Aternos\Nbt\IO\Reader\StringReader;
use Aternos\Nbt\NbtFormat;
use Aternos\Nbt\Tag\Tag;
use Aternos\Thanos\Exception\McaExceptionInterface;
use Aternos\Thanos\Mca\Entry\McaEntry;
use Aternos\Thanos\Mca\Entry\McaEntryInterface;
use Exception;

class Chunk
{
    /**
     * @var Tag[]
     */
    protected array $chunkTagCache = [];

    /**
     * @param McaEntry $chunkEntry
     * @param McaEntryInterface|null $entityEntry
     * @param McaEntryInterface|null $pointsOfInterestEntry
     * @param int $regionXPos
     * @param int $regionZPos
     */
    public function __construct(
        protected McaEntryInterface $chunkEntry,
        protected ?McaEntryInterface $entityEntry,
        protected ?McaEntryInterface $pointsOfInterestEntry,
        protected int $regionXPos,
        protected int $regionZPos,
    )
    {
    }

    /**
     * @return McaEntry
     */
    public function getChunkEntry(): McaEntry
    {
        return $this->chunkEntry;
    }

    /**
     * @return McaEntry
     */
    public function getEntityEntry(): McaEntry
    {
        return $this->entityEntry;
    }

    /**
     * @return McaEntryInterface|null
     */
    public function getPointsOfInterestEntry(): ?McaEntryInterface
    {
        return $this->pointsOfInterestEntry;
    }


    /**
     * @template T of Tag
     * @param class-string<T> $tagClass
     * @param string $key
     * @param int $maxContentLength
     * @return T|null
     * @throws McaExceptionInterface
     */
    public function findChunkTag(string $tagClass, string $key, int $maxContentLength): ?Tag
    {
        if ($tag = $this->getCachedTag($tagClass, $key)) {
            return $tag;
        }
        $tag = $this->findTag($this->chunkEntry, $tagClass, $key, $maxContentLength);
        if ($tag !== null) {
            $this->chunkTagCache[] = $tag;
        }
        return $tag;
    }

    /**
     * @template T of Tag
     * @param class-string<T> $tagClass
     * @param string $key
     * @return Tag|null
     */
    protected function getCachedTag(string $tagClass, string $key): ?Tag
    {
        foreach ($this->chunkTagCache as $tag) {
            if (is_a($tag, $tagClass) && $tag->getName() === $key) {
                return $tag;
            }
        }
        return null;
    }

    /**
     * @template T of Tag
     * @param McaEntryInterface $entry
     * @param class-string<T> $tagClass
     * @param string $key
     * @param int $maxContentLength
     * @return Tag|null
     * @throws McaExceptionInterface
     */
    protected function findTag(McaEntryInterface $entry, string $tagClass, string $key, int $maxContentLength): ?Tag
    {
        $prefix = pack("Cn", $tagClass::TYPE, strlen($key)) . $key;
        $content = $this->readEntryAfter($entry, $prefix, $maxContentLength);
        if ($content === null) {
            return null;
        }

        $reader = new StringReader($prefix . $content, NbtFormat::JAVA_EDITION);
        try {
            return $tagClass::load($reader);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param McaEntryInterface $entry
     * @param string $prefix
     * @param int $length
     * @return string|null
     * @throws McaExceptionInterface
     */
    protected function readEntryAfter(McaEntryInterface $entry, string $prefix, int $length): ?string
    {
        $remainder = "";
        $reading = false;
        $result = "";
        foreach ($entry->getData() as $chunk) {
            if ($reading) {
                $result .= $chunk;
                if (strlen($result) >= $length) {
                    return substr($result, 0, $length);
                }
                continue;
            }

            $chunk = $remainder . $chunk;
            $pos = strpos($chunk, $prefix);
            if ($pos !== false) {
                $reading = true;
                $startPos = $pos + strlen($prefix);
                $result .= substr($chunk, $startPos);
                if (strlen($result) >= $length) {
                    return substr($result, 0, $length);
                }
                continue;
            }
            $remainderLength = strlen($prefix) - 1;
            $remainder = substr($chunk, -$remainderLength);
        }

        if ($reading) {
            return $result;
        }

        return null;
    }

    /**
     * @return int
     */
    public function getXPos(): int
    {
        return $this->chunkEntry->getXPos();
    }

    /**
     * @return int
     */
    public function getZPos(): int
    {
        return $this->chunkEntry->getZPos();
    }

    /**
     * @return int
     */
    public function getRegionXPos(): int
    {
        return $this->regionXPos;
    }

    /**
     * @return int
     */
    public function getRegionZPos(): int
    {
        return $this->regionZPos;
    }

    /**
     * @return int
     */
    public function getGlobalXPos(): int
    {
        return $this->regionXPos * 32 + $this->getXPos();
    }

    /**
     * @return int
     */
    public function getGlobalZPos(): int
    {
        return $this->regionZPos * 32 + $this->getZPos();
    }
}
