<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Framework-agnostic discriminator map for polymorphic deserialization.
 *
 * Encapsulates the mapping from a discriminator field value to a concrete
 * class name.  This is extracted from Symfony's #[DiscriminatorMap] attribute
 * at metadata-resolution time, so the serializer never touches the attribute
 * directly.
 *
 * ## Usage
 *
 * Given a model with `#[DiscriminatorMap('type', ['cat' => CatModel::class, 'dog' => DogModel::class])]`,
 * the resolver produces:
 *
 * ```php
 * new BsonDiscriminatorMap('type', ['cat' => CatModel::class, 'dog' => DogModel::class])
 * ```
 *
 * During deserialization, the serializer looks up the discriminator value
 * in `$data['type']` and instantiates the matching concrete class.
 *
 * @see BsonMetadata Where this map is stored.
 * @see BsonSerializer::findConcreteClass() Where this map is consumed.
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
final readonly class BsonDiscriminatorMap
{
    /**
     * @param string $typeProperty The BSON field name that holds the discriminator value (e.g. 'type', 'origin').
     * @param array<string, string> $mapping Map from discriminator value to fully-qualified concrete class name.
     */
    public function __construct(
        public string $typeProperty,
        public array $mapping,
    ) {}
}