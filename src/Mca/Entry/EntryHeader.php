<?php

namespace Aternos\Thanos\Mca\Entry;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\Thanos\Exception\McaFileException;

class EntryHeader
{
    public const int HEADER_LENGTH = 5;

    /**
     * @param ReadInterface $input
     * @return static
     * @throws McaFileException
     */
    public static function load(ReadInterface $input): static
    {
        try {
            $data = $input->read(static::HEADER_LENGTH);
        } catch (IOException $e) {
            throw new McaFileException("Could not read entry header", previous: $e);
        }
        $values = @unpack("Nlength/ccompressionMethod", $data);
        if ($values === false) {
            throw new McaFileException("Could not parse entry header");
        }

        $length = $values["length"];
        $method = $values["compressionMethod"];
        $compressionMethod = CompressionMethod::tryFrom($method);
        if ($compressionMethod === null) {
            throw new McaFileException("Unknown compression method: " . $method);
        }

        $customCompressionMethod = null;
        if ($compressionMethod === CompressionMethod::CUSTOM) {
            try {
                $customCompressionMethod = self::readCustomMethod($input);
            } catch (IOException $e) {
                throw new McaFileException("Could not read custom compression method", previous: $e);
            }
        }

        return new static($length, $compressionMethod, $customCompressionMethod);
    }

    /**
     * @param ReadInterface $input
     * @return string
     * @throws IOException
     * @throws McaFileException
     */
    protected static function readCustomMethod(ReadInterface $input): string
    {
        $lengthData = $input->read(2);
        $values = @unpack("n", $lengthData);
        if ($values === false) {
            throw new McaFileException("Could not read custom compression method length");
        }
        $length = $values[1];
        $method = $input->read($length);
        if (strlen($method) !== $length) {
            throw new McaFileException("Could not read full custom compression method");
        }
        return $method;
    }

    /**
     * @param int $length
     * @param CompressionMethod $compressionMethod
     * @param string|null $customCompressionMethod
     */
    public function __construct(
        protected int               $length,
        protected CompressionMethod $compressionMethod,
        protected ?string           $customCompressionMethod = null,
    )
    {
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getDataLength(): int
    {
        $length = $this->length - 1;
        if ($this->compressionMethod === CompressionMethod::CUSTOM && $this->customCompressionMethod !== null) {
            $length -= 2 + strlen($this->customCompressionMethod);
        }
        return $length;
    }

    /**
     * @return CompressionMethod
     */
    public function getCompressionMethod(): CompressionMethod
    {
        return $this->compressionMethod;
    }

    /**
     * @return string|null
     */
    public function getCustomCompressionMethod(): ?string
    {
        return $this->customCompressionMethod;
    }
}
