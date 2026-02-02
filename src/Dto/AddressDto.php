<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class AddressDto {
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $locality,
        public string $country,
    )  { }
}
