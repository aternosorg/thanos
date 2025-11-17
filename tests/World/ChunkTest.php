<?php

namespace Aternos\Thanos\Tests\World;

use Aternos\Nbt\IO\Writer\StringWriter;
use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\IntTag;
use Aternos\Nbt\Tag\LongTag;
use Aternos\Nbt\Tag\StringTag;
use Aternos\Thanos\Mca\Entry\McaEntry;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\World\Chunk;
use PHPUnit\Framework\Attributes\TestWith;

class ChunkTest extends ThanosTestCase
{
    protected McaReader $chunks;
    protected Chunk $chunk;
    protected McaEntry $chunkEntry;
    protected McaEntry $entityEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunks = McaReader::open(static::TEST_REGION);
        $entities = McaReader::open(static::TEST_ENTITIES);

        $this->chunkEntry = $this->chunks->getChunkAt(0, 0);
        $this->entityEntry = $entities->getChunkAt(0, 0);

        $this->chunk = new Chunk($this->chunkEntry, $this->entityEntry, null, 0, 0);
    }

    #[TestWith([LongTag::class, "InhabitedTime", 8, 27])]
    #[TestWith([LongTag::class, "LastUpdate", 8, 27])]
    #[TestWith([StringTag::class, "Status", 64, "minecraft:full"])]
    #[TestWith([IntTag::class, "yPos", 4, -4])]
    public function testFindTag(string $tagClass, string $key, int $length, mixed $value): void
    {
        $tag = $this->chunk->findChunkTag($tagClass, "$key", $length);
        $this->assertInstanceOf($tagClass, $tag);
        $this->assertEquals("$key", $tag->getName());
        $this->assertEquals($value, $tag->getValue());
    }

    public function testReturnNullOnMissingTag(): void
    {
        $tag = $this->chunk->findChunkTag(StringTag::class, "NonExistentTag", 64);
        $this->assertNull($tag);
    }

    public function testDoNotFailWhenMaxLengthExceedsDataLength(): void
    {
        $tag = $this->chunk->findChunkTag(StringTag::class, "Status", 1024*1024*10);
        $this->assertInstanceOf(StringTag::class, $tag);
        $this->assertEquals("Status", $tag->getName());
        $this->assertEquals("minecraft:full", $tag->getValue());
    }

    public function testUseCacheWhenSameTagIsSearchedTwice(): void
    {
        $tag1 = $this->chunk->findChunkTag(StringTag::class, "Status", 64);
        $tag2 = $this->chunk->findChunkTag(StringTag::class, "Status", 64);
        $this->assertSame($tag1, $tag2);
    }

    public function testReturnNullOnInvalidTag(): void
    {
        $tag = new CompoundTag();
        $tag->set("Test", new IntTag()->setValue(123));

        $writer = new StringWriter();
        $tag->write($writer);
        $data = $writer->getStringData();

        $entryData = $this->makeEntry(substr($data, 0, -2)); // Corrupt the data
        $file = $this->getDataFile($entryData);

        $entry = new McaEntry($file, 0, 4096, 0, 0);
        $chunk = new Chunk($entry, null, null, 0, 0);

        $tag = $chunk->findChunkTag(CompoundTag::class, "", 128);
        $this->assertNull($tag);
    }

    public function testReadAcrossChunks(): void
    {
        $this->chunkEntry->setReadChunkSize(2);
        $tag = $this->chunk->findChunkTag(StringTag::class, "Status", 64);
        $this->assertInstanceOf(StringTag::class, $tag);
        $this->assertEquals("Status", $tag->getName());
        $this->assertEquals("minecraft:full", $tag->getValue());
    }

    public function testGetters(): void
    {
        $this->assertSame($this->chunkEntry, $this->chunk->getChunkEntry());
        $this->assertSame($this->entityEntry, $this->chunk->getEntityEntry());
        $this->assertNull($this->chunk->getPointsOfInterestEntry());
    }

    public function testGetCoordinates(): void
    {
        $chunk = new Chunk($this->chunks->getChunk(738), null, null, 2, 3);
        $this->assertEquals(2, $chunk->getXPos());
        $this->assertEquals(23, $chunk->getZPos());
        $this->assertEquals(2, $chunk->getRegionXPos());
        $this->assertEquals(3, $chunk->getRegionZPos());
        $this->assertEquals(2 * 32 + 2, $chunk->getGlobalXPos());
        $this->assertEquals(3 * 32 + 23, $chunk->getGlobalZPos());
    }
}
