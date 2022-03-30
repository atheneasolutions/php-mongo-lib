<?php

namespace Athenea\MongoLib\BSON;

use MongoDB\BSON\ObjectId;

if (!\function_exists(oid::class)) {
    /**
     * Àlies de {@see MongoDB\Bson\ObjectId}
     * 
     * Crea un nou ObjectId donat l'identificador
     * 
     * @param string $id Identificador de mongo
     * @return ObjectId Representació BSON de l'identificador
     */
    function oid(string $id): ObjectId
    {
        return new ObjectId($id);
    }
}