<?php

namespace Aternos\Thanos\Mca\Compression;

use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Generator;
use InflateContext;
use RuntimeException;

class ZLibMcaDataReader extends RawMcaDataReader
{
    /**
     * @param ReadInterface&SetPositionInterface&IsEndOfFileInterface $input
     * @param int $startOffset
     * @param int $length
     * @param int $chunkSize
     * @param int $encoding
     */
    public function __construct(
        ReadInterface&IsEndOfFileInterface&SetPositionInterface $input,
        int                                                     $startOffset,
        int                                                     $length,
        int                                                     $chunkSize,
        protected int                                           $encoding
    )
    {
        parent::__construct($input, $startOffset, $length, $chunkSize);
    }

    /**
     * @return InflateContext
     * @codeCoverageIgnore inflate_init cannot return false without options
     */
    protected function initContext(): InflateContext
    {
        $context = @inflate_init($this->encoding);
        if ($context === false) {
            throw new RuntimeException("Could not initialize zlib inflate context");
        }
        return $context;
    }

    /**
     * @inheritDoc
     */
    public function read(): Generator
    {
        $context = $this->initContext();

        foreach (parent::read() as $data) {
            error_clear_last();
            $decompressed = @inflate_add($context, $data);
            if ($decompressed === false) {
                $error = error_get_last();
                $message = 'unknown error';
                if ($error !== null && isset($error['message'])) {
                    $message = $error['message'];
                }
                throw new McaFileException("Could not decompress zlib data: " . $message);
            }
            yield $decompressed;
        }
    }
}
