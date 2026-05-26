<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Immutable value object containing resolved serialization metadata for a
 * single class.
 *
 * Each instance holds two maps keyed by property name (as known by Symfony
 * PropertyAccessor):
 *
 * - `serializableProps`: Maps a property name to its target BSON field name.
 * - `deserializableProps`: Maps a property name to its BSON field name and
 *   the type descriptor that drives value conversion during deserialization.
 *
 * Both map values are strongly-typed value objects:
 * - {@see BsonSerializableProperty} — just a BSON field name.
 * - {@see BsonDeserializableProperty} — BSON field name + {@see BsonPropertyType}.
 *
 * Instances are created by {@see BsonMetadataResolver} and cached
 * internally.  A class's metadata never changes at runtime, so this
 * value object is readonly and immutable.
 *
 * This class has zero framework dependencies.  The resolver translates
 * Symfony `TypeInfoType` into `BsonPropertyType` internally — the
 * serializer never touches Symfony's type system directly.
 *
 * ## Usage
 *
 * ```php
 * $metadata = $resolver->resolve(MyModel::class);
 *
 * // Serialization
 * foreach ($metadata->serializableProps as $propName => $prop) {
 *     $value = $accessor->getValue($object, $propName);
 *     $bson->{$prop->bsonName} = $serializer->serializeValue($value);
 * }
 *
 * // Deserialization
 * foreach ($metadata->deserializableProps as $propName => $prop) {
 *     if (isset($data[$prop->bsonName])) {
 *         $value = $serializer->unserializeValue(
 *             $data[$prop->bsonName],
 *             $prop->type
 *         );
 *         $accessor->setValue($object, $propName, $value);
 *     }
 * }
 * ```
 *
 * @see BsonSerializableProperty
 * @see BsonDeserializableProperty
 * @see BsonPropertyType
 * @see BsonMetadataResolver
 * @see BsonSerializer
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
final readonly class BsonMetadata
{
    /**
     * @param string $className Fully qualified class name this metadata describes.
     *
     * @param array<string, BsonSerializableProperty> $serializableProps
     *   Map of property name → BSON serialization descriptor.
     *
     * @param array<string, BsonDeserializableProperty> $deserializableProps
     *   Map of property name → BSON deserialization descriptor.
     *
     * @param ?BsonDiscriminatorMap $discriminatorMap
     *   Discriminator map for abstract classes that use polymorphic deserialization.
     *   Null when the class is concrete or has no discriminator map.
     */
    public function __construct(
        public string $className,
        public array $serializableProps,
        public array $deserializableProps,
        public ?BsonDiscriminatorMap $discriminatorMap = null,
    ) {}
}
