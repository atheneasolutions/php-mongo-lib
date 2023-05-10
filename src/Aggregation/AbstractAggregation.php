<?php

namespace Athenea\MongoLib\Aggregation;

abstract class AbstractAggregation implements MongoAggregation
{

    public const TZ = "Europe/Madrid";


    protected function not($e){
        return ['$not' => $e];
    }

    protected function and(...$args){
        return ['$and' => $args];
    }

    protected function getField($field, $input){
        return ['$getField' => ['field' => $field, 'input' => $input] ];
    }

    protected function arrayElemAt($array, $index){
        return ['$arrayElemAt' => [$array, $index] ];
    }

    protected function mod($a, $b){
        return ['$mod' => [$a,$b]];
    }

    protected function subtract($a, $b){
        return ['$subtract' => [$a,$b]];
    }

    protected function add($a, $b){
        return ['$add' => [$a,$b]];
    }
    
    protected function eq($exp1, $exp2){
        return ['$eq' => [$exp1, $exp2]];
    }
    protected function dateAdd($date, $amount, $unit, $tz = null){
        return ['$dateAdd' => ['startDate' => $date, 'unit' => $unit, 'amount' => $amount, 'timezone' => $tz ?? self::TZ]];
    }

    protected function dateDiff($startDate, $endDate, $unit, $startOfWeek = null, $tz = null){
        $dateDiff = ['startDate' => $startDate, 'endDate' => $endDate, 'unit' => $unit, 'timezone' => $tz ?? self::TZ];
        if(!is_null($startOfWeek)) $dateDiff['startOfWeek'] = $startOfWeek;
        return ['$dateDiff' => $dateDiff];
    }

    protected function firstDayOfMonth($date, $tz = null){
        return ['$dateTrunc' => ['date' => $date, 'unit' => 'month', 'timezone' => $tz ?? self::TZ]];
    }

    protected function lastDayOfMonth($date){
        $firstMonth = $this->firstDayOfMonth($date);
        $nextMonth = $this->dateAdd($firstMonth, 1, 'month');
        return $this->dateAdd($nextMonth, -1, 'day');
    }

    protected function dateTrunc($date, $unit, $startOfWeek = null, $tz = null){
        $dateTrunc = ['date' => $date, 'unit' => $unit, 'timezone' => $tz ?? self::TZ];
        if(!is_null($startOfWeek)) $dateTrunc['startOfWeek'] = $startOfWeek;
        return ['$dateTrunc' => $dateTrunc];
    }

    protected function removeTime($date, $tz = null){
        return ['$dateTrunc' => ['date' => $date, 'unit' => 'day', 'timezone' => $tz ?? self::TZ]];
    }

    protected function dayOfMonth($date, $tz = null){
        return ['$dayOfMonth' => ['date' => $date, 'timezone' =>  $tz ?? self::TZ]];
    }

    protected function month($date, $tz = null){
        return ['$month' => ['date' => $date, 'timezone' =>  $tz ?? self::TZ]];
    }
    protected function dayOfWeek($date, $tz = null){
        return ['$isoDayOfWeek' => ['date' => $date, 'timezone' =>  $tz ?? self::TZ ]];
    }

    protected function week($date, $tz = null){
        return ['$isoWeek' => ['date' => $date, 'timezone' =>  $tz ?? self::TZ ]];
    }

    protected function cond($if, $then, $else){
        return ['$cond' => ['if' => $if, 'then' => $then, 'else' => $else]];
    }
    
}
