<?php

namespace Aternos\Thanos\Reader;

use Exception;

/**
 * @package Aternos\Thanos\Reader
 */
abstract class BufferedReader implements ReaderInterface
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
     * ZlibReader constructor.
     *
     * @param $resource
     * @param int $offset
     * @param int $length
     */
    public function __construct(
        $resource,
        int $offset,
        int $length
    ) {
        $this->offset = $offset;
        $this->resourcePointer = $offset;
        $this->length = $length;
        $this->resource = $resource;
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
        $readLength = max(
            $length - (strlen($this->data) - $this->pointer),
            0
        );

        if ($readLength > 0) {
            $chunk = "";
            while (strlen($chunk) < $readLength && $this->getRemainingRawLength() > 0) {
                $chunk .= $this->getRawChunk($readLength - strlen($chunk));
            }
            $this->data .= $chunk;
        }

        $data = substr($this->data, $this->pointer, $length);
        $this->pointer += strlen($data);

        return $data;
    }

    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    protected function readRaw(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        fseek($this->resource, $this->resourcePointer);
        $readLength = min(
            $length,
            $this->getRemainingRawLength()
        );

        $rawData = fread($this->resource, $readLength);
        if($rawData === false) {
            throw new Exception("Failed to read compressed input data.");
        }

        if (strlen($rawData) < $readLength && feof($this->resource)) {
            throw new Exception("Reached end of file while reading compressed input data.");
        }

        $this->resourcePointer = ftell($this->resource) ?: $this->resourcePointer + strlen($rawData);
        return $rawData;
    }

    /**
     * @return int
     */
    protected function getRemainingRawLength(): int
    {
        return $this->offset + $this->length - $this->resourcePointer;
    }

    /**
     * Read and uncompress a chunk of data
     * $length is just a suggestion, the actual length of the returned data may be longer or shorter
     *
     * @param int $length
     * @return string
     */
    protected abstract function getRawChunk(int $length): string;

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

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->data = '';
        $this->pointer = 0;
        $this->resourcePointer = $this->offset;
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
