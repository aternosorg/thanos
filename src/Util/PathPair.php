<?php

namespace Aternos\Thanos\Util;

class PathPair
{
    /**
     * @param string $source
     * @param string $destination
     */
    public function __construct(
        protected string $source,
        protected string $destination,
    )
    {
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }
}
