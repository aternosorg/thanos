<?php

namespace Aternos\Thanos\Tests\Pattern\Factory;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\Directory\Directory;
use Aternos\IO\System\FilesystemElement;
use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\IntArrayTag;
use Aternos\Nbt\Tag\ListTag;
use Aternos\Nbt\Tag\StringTag;
use Aternos\Thanos\Pattern\Factory\ForceLoadedChunkPatternFactory;
use Aternos\Thanos\Pattern\ListPattern;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\World\OldDimensionTaskGenerator;
use ReflectionClass;

class ForceLoadedChunkPatternFactoryTest extends ThanosTestCase
{
    protected ForceLoadedChunkPatternFactory $factory;
    protected ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ForceLoadedChunkPatternFactory();
        $this->reflection = new ReflectionClass($this->factory);
    }

    protected function makeDimension(string $path): OldDimensionTaskGenerator
    {
        $source = new Directory($path);
        return new OldDimensionTaskGenerator(
            $source,
            new Directory("/tmp/destination"),
            []
        );
    }

    public function testParseForceLoadedChunks(): void
    {
        $dimension = $this->makeDimension(static::TEST_WORLD);
        $pattern = $this->factory->makePattern($dimension);
        $this->assertInstanceOf(ListPattern::class, $pattern);
        $reflection = new ReflectionClass($pattern);
        $chunks = $reflection->getProperty("chunks")->getValue($pattern);
        $this->assertEquals([[1, 1]], $chunks);
    }

    public function testParseLegacyForceLoadedChunks(): void
    {
        $dimension = $this->makeDimension(static::TEST_LEGACY_FORCELOAD);
        $pattern = $this->factory->makePattern($dimension);
        $this->assertInstanceOf(ListPattern::class, $pattern);
        $reflection = new ReflectionClass($pattern);
        $chunks = $reflection->getProperty("chunks")->getValue($pattern);
        $this->assertEquals([[2, 5], [3, 5], [1, 5], [5, 3], [4, 1], [2, 1], [3, 1], [4, 3], [1, 4], [3, 4], [2, 2], [5, 4], [4, 2], [5, 5], [5, 1], [1, 3], [4, 5], [1, 1], [3, 3], [2, 3], [5, 2], [4, 4], [1, 2], [3, 2], [2, 4]], $chunks);
    }

    public function testMissingChunkDataFile(): void
    {
        $dimension = $this->makeDimension(static::TEST_WORLD . "/region");
        $pattern = $this->factory->makePattern($dimension);
        $this->assertInstanceOf(ListPattern::class, $pattern);
        $reflection = new ReflectionClass($pattern);
        $chunks = $reflection->getProperty("chunks")->getValue($pattern);
        $this->assertEquals([], $chunks);
    }

    public function testInvalidRootTag(): void
    {
        $chunks = $this->reflection->getMethod("getForceLoadedChunksFromTag")->invoke($this->factory, new StringTag());
        $this->assertEquals([], $chunks);
    }

    public function testMissingDataTag(): void
    {
        $chunks = $this->reflection->getMethod("getForceLoadedChunksFromTag")->invoke($this->factory, new CompoundTag());
        $this->assertEquals([], $chunks);
    }

    public function testMissingTicketsTag(): void
    {
        $chunks = $this->reflection->getMethod("getForceLoadedChunksFromTag")->invoke($this->factory, new CompoundTag()->set("data", new CompoundTag()));
        $this->assertEquals([], $chunks);
    }

    public function testSkipUnknownTicketType(): void
    {
        $tickets = new ListTag();
        $tickets[] = new CompoundTag()->set("type", new StringTag()->setValue("minecraft:unknown"));

        $data = new CompoundTag()
            ->set("data", new CompoundTag()
                ->set("tickets", $tickets));

        $chunks = $this->reflection->getMethod("getForceLoadedChunksFromTag")->invoke($this->factory, $data);
        $this->assertEquals([], $chunks);
    }

    public function testSkipInvalidPosition(): void
    {
        $pos = new IntArrayTag();
        $pos[] = 1;

        $tickets = new ListTag();
        $tickets[] = new CompoundTag()
            ->set("type", new StringTag()->setValue("minecraft:forced"))
            ->set("chunk_pos", $pos);

        $data = new CompoundTag()
            ->set("data", new CompoundTag()
                ->set("tickets", $tickets));

        $chunks = $this->reflection->getMethod("getForceLoadedChunksFromTag")->invoke($this->factory, $data);
        $this->assertEquals([], $chunks);
    }

    public function testSkipOnIOError(): void
    {
        $source = new class(static::TEST_WORLD) extends Directory
        {
            public bool $throwError = true;

            public function getChild(string $name, string ...$features): FilesystemElement
            {
                if ($this->throwError) {
                    throw new IOException("Simulated IO error");
                }
                return parent::getChild($name, ...$features);
            }
        };

        $dimension = new OldDimensionTaskGenerator(
            $source,
            new Directory("/tmp/destination"),
            []
        );
        $pattern = $this->factory->makePattern($dimension);
        $this->assertInstanceOf(ListPattern::class, $pattern);
        $reflection = new ReflectionClass($pattern);
        $chunks = $reflection->getProperty("chunks")->getValue($pattern);
        $this->assertEquals([], $chunks);
    }
}
