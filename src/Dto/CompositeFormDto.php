<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CompositeFormDto {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Regex('/^[a-z]+(?:-[a-z]+)*$/')]
        public string $name,
        #[Assert\Email]
        public string $email,
        public AddressDto $address,
    ) { }
}
