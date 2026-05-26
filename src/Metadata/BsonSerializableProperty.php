<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Descriptor for a single serializable property.
 *
 * Maps one property of a model class (identified by the property name used as
 * the array key in {@see BsonMetadata}) to its BSON field name in snake_case.
 *
 * This is a simple value object — it only carries the target BSON field name.
 * The property name itself is the key in the `$serializableProps` map, not
 * stored here, to avoid redundancy.
 *
 * @see BsonMetadata::$serializableProps
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
final readonly class BsonSerializableProperty
{
    /**
     * @param string $bsonName BSON field name (snake_case) where the value
     *                         should be written during serialization.
     */
    public function __construct(
        public string $bsonName,
    ) {}
}
