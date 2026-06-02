<?php

namespace Athenea\MongoLib\Model;

use Athenea\MongoLib\Serializer\BsonSerializer;
use Athenea\MongoLib\Serializer\BsonSerializerInterface;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;
use stdClass;

/**
 * Classe per modelar documents de mongo i que siguin serialitzables.
 *
 * Si una classe hereda d'aquesta, es pot serialitzar i deserialitzar a mongoDB
 * sempre i quan es compleixi el següent:
 * * Tots els camps que es volen serialitzar/deserialitzar implementen {@see Athenea\MongoLib\Attribute\BsonSerialize}
 * * Només es poden serialitzar/deserialitzar elements estandards de mongoDB, primitius, arrays, stdClass i classes que implementen {@see Serializable} o {@see Unserializable}
 * * Per deserialitzar correctament cal posar els tipus de tots els camps o bé a PHP o bé a la documentació
 * * Cal que els camps que siguin serialitzables implementin getters i els deserializables setters o bé que siguin públics
 * * Els camps de tipus {@see UTCDateTimeInterface} es deserialitzen a tipus {@see DateTime} i viceversa
 * * Per deserialitzar els camps només poden tenir un tipus. Si en tenen més s'inferirà el primer que es trobi
 * * Les arrays i els stdClass es deserialitzen/serialitzen recursivament
 *
 * @author  Lluc Bové <lluc.bove@atheneasolutions.com>
 */
abstract class Base implements Serializable, Unserializable {

    private static ?BsonSerializerInterface $defaultSerializer = null;

    public function __construct()
    {

    }

    public function bsonSerialize(): array|stdClass
    {
        return self::getSerializer()->serialize($this);
    }

    public function bsonUnserialize($data): void
    {
        self::getSerializer()->unserialize($this, $data);
    }

    public function bsonChanges(Base $b): array
    {
        return self::getSerializer()->diff($this, $b);
    }

    /**
     * Get or lazily create the default serializer shared by all models.
     *
     * Override with setDefaultSerializer() in tests to inject a mock
     * or pre-configured serializer.
     */
    public static function getSerializer(): BsonSerializerInterface
    {
        if (self::$defaultSerializer === null) {
            self::$defaultSerializer = new BsonSerializer();
        }
        return self::$defaultSerializer;
    }

    /**
     * Inject a custom serializer (e.g. for testing).
     *
     * Set to null to force re-initialization on next serialization call.
     */
    public static function setDefaultSerializer(?BsonSerializerInterface $serializer): void
    {
        self::$defaultSerializer = $serializer;
    }
}
