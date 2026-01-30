<?php

declare(strict_types=1);

namespace App\MapPayload;

/**
 * @implements \IteratorAggregate<string, string[]>
 */
class ViolationList implements \Stringable, \IteratorAggregate, \Countable
{
    /** @var array<string, string[]> */
    private array $violations = [];

    public function __construct(
        private string $subject,
    ) {}

    public function add(string $message, ?string $path = null): self
    {
        $this->violations[$path ?: 'root'][] = $message;

        return $this;
    }

    /**
     * Get violations for a specific path
     *
     * @return string[]
     */
    public function __call(string $name, mixed $argument): array
    {
        return $this->violations[$name] ?? [];
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->violations;
    }

    #[\Override]
    public function count(): int
    {
        return max(0, (int) array_reduce($this->violations, fn ($sum, $item) => $sum + \count($item), 0));
    }

    #[\Override]
    public function __toString(): string
    {
        $ret = $this->subject . \PHP_EOL . \PHP_EOL;

        foreach ($this->violations as $path => $violation) {
            if (1 === \count($violation)) {
                $ret .= '  - ' . $path . ': ' . $violation[0] . \PHP_EOL;
            } elseif (\count($violation) > 1) {
                $ret .= '  - ' . $path . ':' . \PHP_EOL;

                foreach ($violation as $message) {
                    $ret .= '    - ' . $message . \PHP_EOL;
                }
                $ret .= \PHP_EOL;
            }
        }

        return $ret;
    }
}
