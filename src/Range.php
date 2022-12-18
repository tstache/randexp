<?php

declare(strict_types=1);

namespace RandExp;

/**
 * Class which defines a range from given low value and high value
 *
 * @see https://github.com/fent/node-drange
 */
class Range
{
    public int $low;
    public int $high;
    public int $length;

    public function __construct(int $low, int $high)
    {
        $this->low = $low;
        $this->high = $high;
        $this->length = 1 + $high - $low;
    }

    /**
     * Tests if this range overlaps with another range
     *
     * @param Range $range
     *
     * @return bool
     */
    public function overlaps(Range $range): bool
    {
        return !($this->high < $range->low || $this->low > $range->high);
    }

    /**
     * Tests if this range touches another range
     *
     * @param Range $range
     *
     * @return bool
     */
    public function touches(Range $range): bool
    {
        return !($this->high + 1 < $range->low || $this->low - 1 > $range->high);
    }

    /**
     * Returns inclusive combination of SubRanges as a SubRange.
     *
     * @param Range $range
     *
     * @return Range
     */
    public function add(Range $range): Range
    {
        return new Range(
            min($this->low, $range->low),
            max($this->high, $range->high)
        );
    }

    /**
     * Returns subtraction of SubRanges as an array of SubRanges (There's a case where subtraction divides it in 2)
     *
     * @param Range $range
     *
     * @return Range[]
     */
    public function subtract(Range $range): array
    {
        if ($range->low <= $this->low && $range->high >= $this->high) {
            return [];
        }

        if ($range->low > $this->low && $range->high < $this->high) {
            return [
                new Range($this->low, $range->low - 1),
                new Range($range->high + 1, $this->high),
            ];
        }

        if ($range->low <= $this->low) {
            return [new Range($range->high + 1, $this->high)];
        }

        return [new Range($this->low, $range->low - 1)];
    }

    public function __toString(): string
    {
        return $this->low === $this->high ? (string)$this->low : $this->low . '-' . $this->high;
    }

    public function toArray(): array
    {
        return range($this->low, $this->high);
    }
}
