<?php

namespace Athenea\MongoLib\PropertyAccess;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Symfony-backed property accessor implementation.
 *
 * Delegates to Symfony's PropertyAccessor which handles magic
 * getters/setters (__get, __set), property paths, and other
 * advanced features.
 *
 * This is the default implementation used by {@see BsonSerializer}.
 *
 * @see BsonPropertyAccessorInterface
 * @see ReflectionPropertyAccessor
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
class SymfonyPropertyAccessor implements BsonPropertyAccessorInterface
{
    private static ?BsonPropertyAccessorInterface $instance = null;

    public function getValue(object $object, string $property): mixed
    {
        return PropertyAccess::createPropertyAccessor()->getValue($object, $property);
    }

    public function setValue(object $object, string $property, mixed $value): void
    {
        PropertyAccess::createPropertyAccessor()->setValue($object, $property, $value);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
}