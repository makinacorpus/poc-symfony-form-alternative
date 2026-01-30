<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Input
{
    public string $id;
    public string $name;
    public bool $required = false;
    public ?string $label = null;
    public ?string $help = null;
    /** @var ?string[] */
    public ?array $errors = null;
    public mixed $value = null;
}
