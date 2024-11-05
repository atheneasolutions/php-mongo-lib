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
    public static function date(DateTimeInterface $date): UTCDateTime
    {
        return new UTCDateTime($date->getTimestamp() * 1000);
    }

    /**
     * Obté la UTCDateTime de mongo que representa la data actual
     * @return UTCDateTime data de mongo que representa la data actual
     */
    public static function now(): UTCDateTime
    {
        return self::date(new DateTime());
    }

    public static function insertOne(\MongoDB\Collection $collection, &$document, array $options = []) {
        if( is_array($document)){
            $document['updated_at'] = self::now();
            $document['created_at'] = self::now();
        }
        
        if(is_a($document, MongoDocument::class)) {
            $document->updated_at = self::now();
            $document->created_at = self::now();
        }
        return $collection->insertOne($document, $options);
    }

    public static function insertMany(\MongoDB\Collection $collection, array &$documents, array $options = []) {
        foreach($documents as $document){
            if( is_array($document)){
                $document['updated_at'] = self::now();
                $document['created_at'] = self::now();
            }
            
            if(is_a($document, MongoDocument::class)) {
                $document->updated_at = self::now();
                $document->created_at = self::now();
            }
        }
        return $collection->insertMany($documents, $options);
    }

    public static function insertByChunks(\MongoDB\Collection $collection, &$documents, int $chunks = 100, array $options = []) {
        $buffer = [];
        $i = 0;
        foreach($documents as $document){
            if( is_array($document)){
                $document['updated_at'] = self::now();
                $document['created_at'] = self::now();
            }
            
            if(is_a($document, MongoDocument::class)) {
                $document->updated_at = self::now();
                $document->created_at = self::now();
            }
            $buffer[] = $document;
            ++$i;
            if($i >= $chunks){
                $collection->insertMany($buffer, $options);
                $i = 0;
                $buffer = [];
            }
        }
        if(sizeof($buffer) > 0 )$collection->insertMany($buffer, $options);
    }

    public static function updateOne(\MongoDB\Collection $collection, $filter,  $update, array $options = []) {
        $update['$set']['updated_at'] = self::now();
        $update['$setOnInsert']['created_at'] = self::now();
        return $collection->updateOne($filter, $update, $options);
    }

    public static function updateMany(\MongoDB\Collection $collection, $filter,  $update, array $options = []) {
        $update['$set']['updated_at'] = self::now();
        $update['$setOnInsert']['created_at'] = self::now();
        return $collection->updateMany($filter, $update, $options);
    }

    public static function replaceOne(\MongoDB\Collection $collection, $filter, &$document, array $options = []){
        if(is_a($document, MongoDocument::class)) $document->updated_at = self::now();
        if(is_array($document)) $document['updated_at'] = self::now();
        $replace_result = $collection->replaceOne($filter, $document, $options);
        if($replace_result->getUpsertedCount() > 0) 
            $collection->updateOne(['_id' => $replace_result->getUpsertedId()], ['created_at' => new DateTime()]);
        return $replace_result;
    }

    public static function findOneAndUpdate(\MongoDB\Collection $collection, $filter, $update, array $options = []){
        $update['$set']['updated_at'] = self::now();
        return $collection->findOneAndUpdate($filter, $update, $options);
    }

    public static function findOneAndReplace(\MongoDB\Collection $collection, $filter, &$document, array $options = []){
        $document['updated_at'] = self::now();
        $replace_result =  $collection->findOneAndReplace($filter, $document, $options);
        if($replace_result && $replace_result->getUpsertedCount() > 0) 
            $collection->updateOne(['_id' => $replace_result->getUpsertedId()], ['created_at' => new DateTime()]);
        return $replace_result;
    }

}
