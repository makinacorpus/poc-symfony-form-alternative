<?php

declare(strict_types=1);

namespace App\MapPayload;

/**
 * @template T of object
 */
class MappedPayload
{
    /**
     * @param ?T $object
     */
    public function __construct(
        public readonly ?object $object,
        public readonly ViolationList $violationList,
    ) {}

    /** @phpstan-assert-if-true !null $this->object */
    public function isValid(): bool
    {
        return !$this->empty() && (0 === \count($this->violationList));
    }

    public function empty(): bool
    {
        return null === $this->object;
    }
}
