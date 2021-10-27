<?php

namespace Aternos\Thanos\Reader;

use Exception;

/**
 * Class ZlibReader
 * Read zlib compressed data from a resource
 *
 * @package Aternos\Thanos\Reader
 */
class ZlibReader implements ReaderInterface
{
    /**
     * @var int
     */
    protected int $offset;

    /**
     * @var int
     */
    protected int $length;

    /**
     * @var int
     */
    protected int $resourcePointer;

    /**
     * @var int
     */
    protected int $pointer = 0;

    /**
     * @var string
     */
    protected string $data = '';

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var int
     */
    protected int $compression;

    /**
     * @var resource
     */
    protected $inflateContext = null;

    /**
     * ZlibReader constructor.
     *
     * @param $resource
     * @param int $compression
     * @param int $offset
     * @param int $length
     */
    public function __construct(
        $resource,
        int $compression = ZLIB_ENCODING_RAW,
        int $offset = 0,
        int $length = -1
    ) {
        $this->offset = $offset;
        $this->resourcePointer = $offset;
        $this->length = $length;
        $this->resource = $resource;
        $this->compression = $compression;
        $this->inflateContext = inflate_init($this->compression);
    }

    /**
     * Read $length bytes of data
     *
     * @param int $length
     * @return string
     * @throws Exception
     */
    public function read(int $length): string
    {
        $remaining = $this->length !== -1
            ? $this->offset + $this->length - $this->resourcePointer
            : $length
        ;
        $readLength = max(
            $length - (strlen($this->data) - $this->pointer),
            0
        );

        if ($readLength > 0 && $remaining > 0) {
            fseek($this->resource, $this->resourcePointer);
            $rawData = fread(
                $this->resource,
                min(
                    max(512, $readLength),
                    $remaining
                )
            );
            if($rawData === false) {
                throw new Exception("Failed to read compressed input data.");
            }

            $uncompressedData = inflate_add(
                $this->inflateContext,
                $rawData
            );
            if($uncompressedData === false) {
                throw new Exception("Failed to inflate input data.");
            }
            $this->data .= $uncompressedData;
            $this->resourcePointer = ftell($this->resource) ?: $this->resourcePointer + strlen($rawData);
        }

        $data = substr($this->data, $this->pointer, $length);
        $this->pointer += strlen($data);

        return $data;
    }

    /**
     * Set pointer position to $offset
     *
     * @param int $offset
     */
    public function seek(int $offset): void
    {
        $this->pointer = max($offset, 0);
    }

    /**
     * Set pointer position to 0
     *
     */
    public function rewind(): void
    {
        $this->pointer = 0;
    }

    public function eof(): bool
    {
        return ($this->resourcePointer >= $this->offset + $this->length || feof($this->resource))
            && $this->pointer >= strlen($this->data);
    }

    /**
     * Get current pointer position
     *
     * @return int
     */
    public function tell(): int
    {
        return $this->pointer;
    }
}
