<?php

namespace Athenea\MongoLib\PropertyAccess;

/**
 * Framework-agnostic interface for reading and writing object properties.
 *
 * Abstracts away Symfony's PropertyAccessor so that the serializer
 * can work without Symfony as a hard dependency.  Implementations provided:
 *
 * - {@see SymfonyPropertyAccessor} — delegates to Symfony's PropertyAccess (default).
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
interface BsonPropertyAccessorInterface
{
    /**
     * Read the value of a property on the given object.
     *
     * Must support both public and private/protected properties,
     * following the same accessibility rules as Symfony's PropertyAccessor.
     *
     * @param object $object   The model instance to read from
     * @param string $property  The property name to read
     *
     * @return mixed The property value (scalar, array, object, or null)
     *
     * @throws \InvalidArgumentException if the property does not exist
     * @throws \LogicException           if the property is not accessible
     */
    public function getValue(object $object, string $property): mixed;

    /**
     * Write a value to a property on the given object.
     *
     * Must support both public and private/protected properties,
     * following the same accessibility rules as Symfony's PropertyAccessor.
     *
     * @param object $object   The model instance to write to
     * @param string $property  The property name to write
     * @param mixed  $value     The value to assign
     *
     * @throws \InvalidArgumentException if the property does not exist
     * @throws \LogicException           if the property is not accessible or is read-only
     */
    public function setValue(object $object, string $property, mixed $value): void;
}