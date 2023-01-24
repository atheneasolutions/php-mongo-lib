<?php

namespace Athenea\MongoLib;

use DateTime;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\UTCDateTimeInterface;

class Utils{

    public static function normalize(\MongoDB\Model\BSONDocument $bSONDocument){
        $serialization = $bSONDocument->jsonSerialize();
        $newSerialization = [];
        foreach($serialization as $key => $value){
            if(is_a($value, \MongoDB\Model\BSONArray::class)) {
                $newSerialization[$key] = self::normalizeArray($value);
            }
            else if(is_a($value, \MongoDB\Model\BSONDocument::class)) {
                $newSerialization[$key] = self::normalize($value);
            }
            else if(is_a($value, UTCDateTimeInterface::class)){
                $newSerialization[$key] = $value->toDateTime();
            }
            else $newSerialization[$key] = $value;
        }
        return $newSerialization;
    }

    public static function normalizeArray(\MongoDB\Model\BSONArray $bSONArray){
        $newSerialization = [];
        $serialization = $bSONArray->jsonSerialize();
        foreach($serialization as $value){
            if(is_a($value, \MongoDB\Model\BSONArray::class)) {
                $newSerialization[] = self::normalizeArray($value);
            }
            else if(is_a($value, \MongoDB\Model\BSONDocument::class)) {
                $newSerialization[] = self::normalize($value);
            }
            else if(is_a($value, UTCDateTimeInterface::class)){
                $newSerialization[] = $value->toDateTime();
            }
            else $newSerialization[] = $value;
        }
        return $newSerialization;
    }

    public static function normalizeIterable($iterable){
        $newSerialization = [];
        foreach($iterable as $value){
            if(is_a($value, \MongoDB\Model\BSONArray::class)) {
                $newSerialization[] = self::normalizeArray($value);
            }
            else if(is_a($value, \MongoDB\Model\BSONDocument::class)) {
                $newSerialization[] = self::normalize($value);
            }
            else if(is_a($value, UTCDateTimeInterface::class)){
                $newSerialization[] = $value->toDateTime();
            }
            else $newSerialization[] = $value;
        }
        return $newSerialization;
    }



    public static function normalizeGeneric($obj){
        if(!$obj) return null;
        if(is_a($obj, \MongoDB\Model\BSONDocument::class)) return self::normalize($obj);
        if(is_a($obj, \MongoDB\Model\BSONArray::class)) return self::normalizeArray($obj);
        if(is_a($obj, \MongoDB\Model\BSONIterator::class)) return self::normalizeIterable($obj);
        if(is_a($obj, UTCDateTimeInterface::class)) return $obj->toDateTime();
        return null;
    }

    /**
     * Donat un Datetime obté la UTCDateTime de mongo corresponent
     * 
     * @param DateTimeInterface $date data a convertir
     * @return UTCDateTime Data de mongo que correspon a $date
     */
    public function date(DateTimeInterface $date): UTCDateTime
    {
        return new UTCDateTime($date->getTimestamp() * 1000);
    }

    /**
     * Obté la UTCDateTime de mongo que representa la data actual
     * @return UTCDateTime data de mongo que representa la data actual
     */
    public function now(): UTCDateTime
    {
        return $this->date(new DateTime());
    }
}