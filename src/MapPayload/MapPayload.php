<?php

declare(strict_types=1);

namespace App\MapPayload;

#[\Attribute(flags: \Attribute::TARGET_PARAMETER)]
class MapPayload
{
    public function __construct(
        public readonly string $type,
    ) {}
}
