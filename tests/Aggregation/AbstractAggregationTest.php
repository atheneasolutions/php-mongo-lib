<?php

namespace Athenea\MongoLib\Tests\Aggregation;

use Athenea\MongoLib\Aggregation\AbstractAggregation;
use Athenea\MongoLib\Aggregation\MongoAggregation;
use PHPUnit\Framework\TestCase;

/** Concrete subclass that exposes all protected helpers for testing. */
class TestAggregation extends AbstractAggregation
{
    public function getAggregation(): array { return []; }

    public function not($e) { return parent::not($e); }
    public function and(...$args) { return parent::and(...$args); }
    public function getField($field, $input) { return parent::getField($field, $input); }
    public function arrayElemAt($array, $index) { return parent::arrayElemAt($array, $index); }
    public function mod($a, $b) { return parent::mod($a, $b); }
    public function subtract($a, $b) { return parent::subtract($a, $b); }
    public function add($a, $b) { return parent::add($a, $b); }
    public function eq($e1, $e2) { return parent::eq($e1, $e2); }
    public function dateAdd($date, $amount, $unit, $tz = null) { return parent::dateAdd($date, $amount, $unit, $tz); }
    public function dateDiff($start, $end, $unit, $sow = null, $tz = null) { return parent::dateDiff($start, $end, $unit, $sow, $tz); }
    public function firstDayOfMonth($date, $tz = null) { return parent::firstDayOfMonth($date, $tz); }
    public function lastDayOfMonth($date) { return parent::lastDayOfMonth($date); }
    public function dateTrunc($date, $unit, $sow = null, $tz = null) { return parent::dateTrunc($date, $unit, $sow, $tz); }
    public function removeTime($date, $tz = null) { return parent::removeTime($date, $tz); }
    public function dayOfMonth($date, $tz = null) { return parent::dayOfMonth($date, $tz); }
    public function month($date, $tz = null) { return parent::month($date, $tz); }
    public function dayOfWeek($date, $tz = null) { return parent::dayOfWeek($date, $tz); }
    public function week($date, $tz = null) { return parent::week($date, $tz); }
    public function cond($if, $then, $else) { return parent::cond($if, $then, $else); }
}

class AbstractAggregationTest extends TestCase
{
    private TestAggregation $agg;

    protected function setUp(): void
    {
        $this->agg = new TestAggregation();
    }

    public function testImplementsMongoAggregation(): void
    {
        $this->assertInstanceOf(MongoAggregation::class, $this->agg);
    }

    public function testNot(): void
    {
        $this->assertSame(['$not' => '$field'], $this->agg->not('$field'));
    }

    public function testAnd(): void
    {
        $result = $this->agg->and(['$a' => 1], ['$b' => 2]);
        $this->assertSame(['$and' => [['$a' => 1], ['$b' => 2]]], $result);
    }

    public function testGetField(): void
    {
        $result = $this->agg->getField('name', '$doc');
        $this->assertSame(['$getField' => ['field' => 'name', 'input' => '$doc']], $result);
    }

    public function testArrayElemAt(): void
    {
        $result = $this->agg->arrayElemAt('$items', 0);
        $this->assertSame(['$arrayElemAt' => ['$items', 0]], $result);
    }

    public function testMod(): void
    {
        $this->assertSame(['$mod' => [10, 3]], $this->agg->mod(10, 3));
    }

    public function testSubtract(): void
    {
        $this->assertSame(['$subtract' => [5, 2]], $this->agg->subtract(5, 2));
    }

    public function testAdd(): void
    {
        $this->assertSame(['$add' => [1, 2]], $this->agg->add(1, 2));
    }

    public function testEq(): void
    {
        $this->assertSame(['$eq' => ['$a', '$b']], $this->agg->eq('$a', '$b'));
    }

    public function testDateAddWithDefaultTz(): void
    {
        $result = $this->agg->dateAdd('$date', 1, 'day');
        $this->assertSame('$dateAdd', array_key_first($result));
        $this->assertSame('day', $result['$dateAdd']['unit']);
        $this->assertSame(AbstractAggregation::TZ, $result['$dateAdd']['timezone']);
    }

    public function testDateAddWithCustomTz(): void
    {
        $result = $this->agg->dateAdd('$date', 1, 'hour', 'UTC');
        $this->assertSame('UTC', $result['$dateAdd']['timezone']);
    }

    public function testDateDiffWithoutStartOfWeek(): void
    {
        $result = $this->agg->dateDiff('$start', '$end', 'day');
        $this->assertArrayHasKey('$dateDiff', $result);
        $this->assertArrayNotHasKey('startOfWeek', $result['$dateDiff']);
    }

    public function testDateDiffWithStartOfWeek(): void
    {
        $result = $this->agg->dateDiff('$start', '$end', 'week', 'monday');
        $this->assertSame('monday', $result['$dateDiff']['startOfWeek']);
    }

    public function testFirstDayOfMonth(): void
    {
        $result = $this->agg->firstDayOfMonth('$date');
        $this->assertArrayHasKey('$dateTrunc', $result);
        $this->assertSame('month', $result['$dateTrunc']['unit']);
    }

    public function testLastDayOfMonth(): void
    {
        $result = $this->agg->lastDayOfMonth('$date');
        // Should be dateAdd(dateAdd(firstDayOfMonth, 1, month), -1, day)
        $this->assertArrayHasKey('$dateAdd', $result);
        $this->assertSame(-1, $result['$dateAdd']['amount']);
        $this->assertSame('day', $result['$dateAdd']['unit']);
    }

    public function testDateTruncWithoutStartOfWeek(): void
    {
        $result = $this->agg->dateTrunc('$date', 'week');
        $this->assertArrayHasKey('$dateTrunc', $result);
        $this->assertArrayNotHasKey('startOfWeek', $result['$dateTrunc']);
    }

    public function testDateTruncWithStartOfWeek(): void
    {
        $result = $this->agg->dateTrunc('$date', 'week', 'monday');
        $this->assertSame('monday', $result['$dateTrunc']['startOfWeek']);
    }

    public function testRemoveTime(): void
    {
        $result = $this->agg->removeTime('$date');
        $this->assertSame('day', $result['$dateTrunc']['unit']);
    }

    public function testDayOfMonth(): void
    {
        $result = $this->agg->dayOfMonth('$date');
        $this->assertArrayHasKey('$dayOfMonth', $result);
    }

    public function testMonth(): void
    {
        $result = $this->agg->month('$date');
        $this->assertArrayHasKey('$month', $result);
    }

    public function testDayOfWeek(): void
    {
        $result = $this->agg->dayOfWeek('$date');
        $this->assertArrayHasKey('$isoDayOfWeek', $result);
    }

    public function testWeek(): void
    {
        $result = $this->agg->week('$date');
        $this->assertArrayHasKey('$isoWeek', $result);
    }

    public function testCond(): void
    {
        $result = $this->agg->cond('$if', 'then', 'else');
        $this->assertSame(['$cond' => ['if' => '$if', 'then' => 'then', 'else' => 'else']], $result);
    }
}
