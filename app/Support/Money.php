<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Immutable money value object — integer centavos only, never float.
 *
 * v1 is single-currency PHP; the currency code is fixed but retained so the
 * multi-currency v2 (§17) can widen it without touching call sites.
 */
final class Money implements Stringable
{
    public function __construct(
        public readonly int $minor,
        public readonly string $currency = 'PHP',
    ) {}

    public static function of(int $minor, string $currency = 'PHP'): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency = 'PHP'): self
    {
        return new self(0, $currency);
    }

    /**
     * Build from a whole-peso integer (e.g. 1_000_000 pesos -> 100_000_000 centavos).
     */
    public static function fromPesos(int $pesos, string $currency = 'PHP'): self
    {
        return new self($pesos * 100, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /**
     * @return int -1|0|1
     */
    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    public function toDecimal(): string
    {
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function format(): string
    {
        return '₱'.number_format((float) $this->toDecimal(), 2);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
