<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Framework-agnostic descriptor of a property's type for deserialization.
 *
 * When deserializing BSON into a model, the serializer needs to answer three
 * questions:
 *
 * 1. Does this property hold an object that needs recursive deserialization?
 *    → `$className` tells us which class to instantiate.
 * 2. Is it a collection (array of objects)?
 *    → `$isCollection` tells us to iterate and deserialize each element.
 * 3. Can it be null?
 *    → `$isNullable` tells us to allow null assignment without skipping.
 *
 * This value object is created by {@see BsonMetadataResolver} from Symfony
 * PropertyInfo's `TypeInfoType`, but it has zero framework dependencies.
 * {@see BsonSerializer} consumes it without ever touching Symfony's type
 * system directly.
 *
 * ## Conversion table (Symfony → BsonPropertyType)
 *
 * | Symfony TypeInfoType                                   | BsonPropertyType                                      |
 * |--------------------------------------------------------|-------------------------------------------------------|
 * | `BuiltinType('string')`                                | `new BsonPropertyType()` (className=null)             |
 * | `ObjectType(Device::class)`                            | `new BsonPropertyType(Device::class)`                 |
 * | `CollectionType(..., ObjectType(Item::class))`         | `new BsonPropertyType(Item::class, isCollection:true)`|
 * | `NullableType(ObjectType(User::class))`                | `new BsonPropertyType(User::class, isNullable:true)`  |
 * | `CompositeType` → first non-null type                  | Same as the non-null part, with `isNullable: true`     |
 * | Unknown / can't infer                                  | `new BsonPropertyType()` (className=null)             |
 *
 * ## Usage in serializer
 *
 * ```php
 * $type = $metadata->deserializableProps['items']['type'];
 *
 * if ($type->className && $type->isCollection) {
 *     // Array of objects: iterate, instantiate each via bsonUnserialize()
 *     foreach ($rawValue as $item) {
 *         $obj = new ($type->className)();
 *         $obj->bsonUnserialize($item);
 *         $result[] = $obj;
 *     }
 * } elseif ($type->className) {
 *     // Single object: instantiate via bsonUnserialize()
 *     $obj = new ($type->className)();
 *     $obj->bsonUnserialize($rawValue);
 *     $result = $obj;
 * } else {
 *     // Scalar / unknown: use raw value as-is
 *     $result = $rawValue;
 * }
 * ```
 *
 * @see BsonMetadata           Where this type descriptor is stored.
 * @see BsonMetadataResolver   Where instances are created from Symfony TypeInfo.
 * @see BsonSerializer         Where instances are consumed for deserialization.
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
final readonly class BsonPropertyType
{
    /**
     * @param ?string $className    Fully qualified class name of the expected
     *                              object or BackedEnum type.  `null` means a
     *                              scalar / primitive where no recursive
     *                              deserialization is needed — the raw value
     *                              is used as-is (except for BackedEnum
     *                              conversions handled by the serializer at
     *                              runtime via `is_subclass_of`).
     *
     * @param bool    $isCollection Whether the property holds an array or
     *                              collection of values.  When true and
     *                              `$className` is set, the serializer
     *                              iterates each element.
     *
     * @param bool    $isNullable   Whether null is a valid value.  When true,
     *                              the serializer allows setting the property
     *                              to null.  When false, null values are
     *                              skipped.
     */
    public function __construct(
        public ?string $className = null,
        public bool $isCollection = false,
        public bool $isNullable = false,
    ) {}
}
