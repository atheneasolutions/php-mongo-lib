<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Descriptor for a single deserializable property.
 *
 * Maps one property of a model class to:
 * - The BSON field name to look up in raw data.
 * - The {@see BsonPropertyType} describing how to convert the raw value
 *   (instantiate a class, iterate a collection, or keep as-is).
 *
 * Unlike the serializable props, the property name is NOT stored here — it
 * is the key in the `$deserializableProps` map of {@see BsonMetadata}.
 *
 * @see BsonMetadata::$deserializableProps
 * @see BsonPropertyType
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
final readonly class BsonDeserializableProperty
{
    /**
     * @param string          $bsonName BSON field name to read from raw data.
     * @param BsonPropertyType $type     Type descriptor driving conversion.
     */
    public function __construct(
        public string $bsonName,
        public BsonPropertyType $type,
    ) {}
}
