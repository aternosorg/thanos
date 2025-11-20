<?php

namespace Aternos\Thanos\Mca\Entry;

enum CompressionMethod: int
{
    case GZIP = 1;
    case ZLIB = 2;
    case RAW = 3;
    case LZ4 = 4;
    case CUSTOM = 127;

    case EXTERNAL_GZIP = -127;
    case EXTERNAL_ZLIB = -126;
    case EXTERNAL_RAW = -125;
    case EXTERNAL_LZ4 = -124;

    /**
     * @return bool
     */
    public function isExternal(): bool
    {
        return match ($this) {
            self::EXTERNAL_GZIP,
            self::EXTERNAL_ZLIB,
            self::EXTERNAL_RAW,
            self::EXTERNAL_LZ4 => true,
            default => false,
        };
    }
}
