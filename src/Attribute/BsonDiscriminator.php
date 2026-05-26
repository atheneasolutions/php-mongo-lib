<?php

namespace Athenea\MongoLib\Attribute;

use Attribute;

/**
 * Declares a discriminator map on an abstract base class for polymorphic deserialization.
 *
 * When the serializer encounters an abstract class during deserialization,
 * it looks for this attribute on the class. The `typeProperty` tells it which
 * BSON field holds the discriminator value, and the `mapping` maps each value
 * to a concrete subclass.
 *
 * ## Example
 *
 * ```php
 * #[BsonDiscriminator('type', ['cat' => CatModel::class, 'dog' => DogModel::class])]
 * abstract class AbstractAnimal extends Base { ... }
 * ```
 *
 * During deserialization, if `$data['type'] === 'cat'`, the serializer
 * instantiates `CatModel`.
 *
 * This attribute replaces Symfony's `#[DiscriminatorMap]` — no dependency
 * on `symfony/serializer` is required.
 *
 * @see \Athenea\MongoLib\Metadata\BsonDiscriminatorMap
 * @see \Athenea\MongoLib\Metadata\BsonMetadata
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
#[Attribute(Attribute::TARGET_CLASS)]
class BsonDiscriminator
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