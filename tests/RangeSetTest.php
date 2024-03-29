<?php

declare(strict_types=1);

namespace Tests\RandExp;

use RandExp\RangeSet;
use PHPUnit\Framework\TestCase;

class RangeSetTest extends TestCase
{
    public function testEmptyRangeSet(): void
    {
        $rangeSet = new RangeSet();
        static::assertEquals('[  ]', (string)$rangeSet);
    }

    public function testAddSets(): void
    {
        $rangeSet = new RangeSet(5);
        static::assertEquals('[ 5 ]', (string)$rangeSet);
        static::assertEquals('[ 5-6 ]', (string)$rangeSet->add(6));
        static::assertEquals('[ 5-6, 8 ]', (string)$rangeSet->add(8));
        static::assertEquals('[ 5-8 ]', (string)$rangeSet->add(7));
        static::assertEquals(4, $rangeSet->length);

        $rangeSet = new RangeSet(1, 5);
        static::assertEquals('[ 1-5 ]', (string)$rangeSet);
        static::assertEquals('[ 1-10 ]', (string)$rangeSet->add(6, 10));
        static::assertEquals('[ 1-10, 15-20 ]', (string)$rangeSet->add(15, 20));
        static::assertEquals('[ 0-20 ]', (string)$rangeSet->add(0, 14));
        static::assertEquals(21, $rangeSet->length);

        $rangeSet = new RangeSet(1, 5);
        $rangeSet->add(15, 20);
        $rangeSet->addRangeSet((new RangeSet(6))->add(17, 30));

        static::assertEquals('[ 1-6, 15-30 ]', (string)$rangeSet);
        static::assertEquals(22, $rangeSet->length);
    }

    public function testSubtractSets(): void
    {
        $rangeSet = new RangeSet(1, 10);
        static::assertEquals('[ 1-4, 6-10 ]', (string)$rangeSet->subtract(5));
        static::assertEquals('[ 1-4, 6, 8-10 ]', (string)$rangeSet->subtract(7));
        static::assertEquals('[ 1-4, 8-10 ]', (string)$rangeSet->subtract(6));
        static::assertEquals(7, $rangeSet->length);

        $rangeSet = new RangeSet(1, 100);
        static::assertEquals('[ 1-4, 16-100 ]', (string)$rangeSet->subtract(5, 15));
        static::assertEquals('[ 1-4, 16-89 ]', (string)$rangeSet->subtract(90, 200));
        static::assertEquals(78, $rangeSet->length);

        $rangeSet = new RangeSet(0, 100);
        $rangeSet->subtractRangeSet((new RangeSet(6))->add(17, 30)->add(0, 2));
        static::assertEquals('[ 3-5, 7-16, 31-100 ]', (string)$rangeSet);
        static::assertEquals(83, $rangeSet->length);
    }

    public function testIntersectSets(): void
    {
        $rangeSet = new RangeSet(5, 20);
        static::assertEquals('[ 5-20 ]', (string)$rangeSet);
        static::assertEquals('[ 7 ]', (string)$rangeSet->intersect(7));

        $rangeSet = new RangeSet(1, 5);
        static::assertEquals('[ 1-5 ]', (string)$rangeSet);
        static::assertEquals('[  ]', (string)$rangeSet->intersect(6, 10));
        static::assertEquals('[ 15-20 ]', (string)$rangeSet->add(15, 20));
        static::assertEquals('[ 15-18 ]', (string)$rangeSet->intersect(0, 18));
        static::assertEquals(4, $rangeSet->length);

        $rangeSet = new RangeSet(1, 5);
        $rangeSet->add(15, 20);
        $rangeSet->intersectRangeSet((new RangeSet(3, 6))->add(17, 30));
        static::assertEquals('[ 3-5, 17-20 ]', (string)$rangeSet);
        static::assertEquals(7, $rangeSet->length);
    }

    public function testIndexSets(): void
    {
        $rangeSet = new RangeSet(0, 9);
        $rangeSet->add(20, 29);
        $rangeSet->add(40, 49);
        static::assertEquals(5, $rangeSet->index(5));
        static::assertEquals(25, $rangeSet->index(15));
        static::assertEquals(45, $rangeSet->index(25));
        static::assertEquals(30, $rangeSet->length);
    }

    public function testCloneSets(): void
    {
        $rangeSet = new RangeSet(0, 9);
        static::assertEquals('[ 0-9 ]', (string)$rangeSet);
        static::assertEquals('[ 0-4, 6-9 ]', (string)(clone $rangeSet)->subtract(5));
    }

    public function testToArray(): void
    {
        $rangeSet = new RangeSet(0, 5);
        static::assertEquals([0, 1, 2, 3, 4, 5], $rangeSet->toArray());

        $rangeSet->add(7, 9);
        static::assertEquals([0, 1, 2, 3, 4, 5, 7, 8, 9], $rangeSet->toArray());

        $rangeSet->subtract(2);
        static::assertEquals([0, 1, 3, 4, 5, 7, 8, 9], $rangeSet->toArray());

        $rangeSet->subtract(5, 8);
        static::assertEquals([0, 1, 3, 4, 9], $rangeSet->toArray());

        $rangeSet->intersect(2, 8);
        static::assertEquals([3, 4], $rangeSet->toArray());
    }
}
