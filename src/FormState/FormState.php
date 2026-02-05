<?php

declare(strict_types=1);

namespace App\FormState;

/**
 * @template T of object
 */
class FormState
{
    /**
     * @param ?T $data
     */
    public function __construct(
        public readonly ?object $data,
        public readonly ViolationList $violationList,
    ) {}

    /** @phpstan-assert-if-true !null $this->data */
    public function isValid(): bool
    {
        return !$this->empty() && (0 === \count($this->violationList));
    }

    public function empty(): bool
    {
        return null === $this->data;
    }
}
