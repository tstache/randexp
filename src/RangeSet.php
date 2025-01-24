<?php

declare(strict_types=1);

namespace RandExp;

use function array_merge;
use function array_slice;
use function count;
use function implode;
use function max;
use function min;

/**
 * Class to define a set for different ranges.
 *
 * @see https://github.com/fent/node-drange
 */
class RangeSet
{
    /**
     * @var Range[]
     */
    public array $ranges;
    public int $length;

    public function __construct(?int $low = null, ?int $high = null)
    {
        $this->ranges = [];
        $this->length = 0;
        if ($low !== null) {
            $this->add($low, $high);
        }
    }

    public function addRangeSet(RangeSet $rangeSet): static
    {
        foreach ($rangeSet->ranges as $range) {
            $this->addRange($range);
        }

        return $this;
    }

    public function add(int $low, ?int $high = null): static
    {
        $this->addRange(new Range($low, $high ?? $low));

        return $this;
    }

    public function subtractRangeSet(RangeSet $rangeSet): static
    {
        foreach ($rangeSet->ranges as $range) {
            $this->subtractRange($range);
        }

        return $this;
    }

    public function subtract(int $low, ?int $high = null): static
    {
        $this->subtractRange(new Range($low, $high ?? $low));

        return $this;
    }

    public function intersectRangeSet(RangeSet $low): static
    {
        $newRanges = [];
        foreach ($low->ranges as $range) {
            $this->intersectRange($range, $newRanges);
        }
        $this->ranges = $newRanges;
        $this->updateLength();

        return $this;
    }

    public function intersect(int $low, ?int $high = null): static
    {
        $newRanges = [];
        if ($high === null) {
            $high = $low;
        }
        $this->intersectRange(new Range($low, $high), $newRanges);
        $this->ranges = $newRanges;
        $this->updateLength();

        return $this;
    }

    public function index($index): int
    {
        for ($i = 0; $i < count($this->ranges) && $this->ranges[$i]->length <= $index; $i++) {
            $index -= $this->ranges[$i]->length;
        }

        return $this->ranges[$i]->low + $index;
    }

    public function __toString(): string
    {
        return '[ ' . implode(', ', $this->ranges) . ' ]';
    }

    public function toArray(): array
    {
        $ranges = [];
        foreach ($this->ranges as $range) {
            $ranges = [... $ranges, ...$range->toArray()];
        }
        return $ranges;
    }

    private function updateLength(): void
    {
        $this->length = 0;
        foreach ($this->ranges as $range) {
            $this->length += $range->length;
        }
    }

    private function addRange(Range $subRange): void
    {
        $i = 0;
        while ($i < count($this->ranges) && !$subRange->touches($this->ranges[$i])) {
            $i++;
        }
        $newRanges = array_slice($this->ranges, 0, $i);
        while ($i < count($this->ranges) && $subRange->touches($this->ranges[$i])) {
            $subRange = $subRange->add($this->ranges[$i]);
            $i++;
        }
        $newRanges[] = $subRange;
        $this->ranges = array_merge($newRanges, array_slice($this->ranges, $i));
        $this->updateLength();
    }

    private function subtractRange(Range $subRange): void
    {
        $i = 0;
        while ($i < count($this->ranges) && !$subRange->overlaps($this->ranges[$i])) {
            $i++;
        }
        $newRanges = array_slice($this->ranges, 0, $i);
        while ($i < count($this->ranges) && $subRange->overlaps($this->ranges[$i])) {
            $newRanges = [...$newRanges, ...$this->ranges[$i]->subtract($subRange)];
            $i++;
        }
        $this->ranges = array_merge($newRanges, array_slice($this->ranges, $i));
        $this->updateLength();
    }

    private function intersectRange(Range $subRange, array &$newRanges): void
    {
        $i = 0;
        while ($i < count($this->ranges) && !$subRange->overlaps($this->ranges[$i])) {
            $i++;
        }
        while ($i < count($this->ranges) && $subRange->overlaps($this->ranges[$i])) {
            $low = max($this->ranges[$i]->low, $subRange->low);
            $high = min($this->ranges[$i]->high, $subRange->high);
            $newRanges[] = new Range($low, $high);
            $i++;
        }
    }
}
