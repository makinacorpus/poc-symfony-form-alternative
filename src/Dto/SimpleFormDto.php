<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class SimpleFormDto {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Regex('/^[a-z]+(?:-[a-z]+)*$/')]
        public string $name,
        #[Assert\Email]
        public string $email,
        #[Assert\GreaterThanOrEqual(7)]
        #[Assert\LessThanOrEqual(77)]
        public int $age,
        public ?string $message = null,
    )  { }
}
