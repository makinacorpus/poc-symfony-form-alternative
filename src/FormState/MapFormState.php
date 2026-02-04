<?php

declare(strict_types=1);

namespace App\FormState;

#[\Attribute(flags: \Attribute::TARGET_PARAMETER)]
class MapFormState
{
    public function __construct(
        public readonly string $type,
    ) {}
}
