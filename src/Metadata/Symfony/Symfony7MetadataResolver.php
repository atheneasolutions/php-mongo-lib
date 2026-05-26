<?php

namespace Athenea\MongoLib\Metadata\Symfony;

use Athenea\MongoLib\Metadata\AbstractBsonMetadataResolver;
use Athenea\MongoLib\Metadata\BsonPropertyType;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type as TypeInfoType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Metadata resolver for Symfony 7.x / 8.x.
 *
 * Symfony 7+/8.x uses the new `TypeInfo` namespace with a type hierarchy:
 * `BuiltinType`, `ObjectType`, `CollectionType`, `NullableType`,
 * `CompositeType`.
 *
 * `PropertyInfoExtractor::getType()` returns a single `TypeInfoType` (or null),
 * replacing the old `getTypes()` that returned `Type[]`.
 *
 * This converter extracts the same information (className, isCollection,
 * isNullable) and stores it in our framework-agnostic {@see BsonPropertyType}.
 *
 * @see AbstractBsonMetadataResolver
 * @see Symfony62MetadataResolver
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 *
 * @internal This class requires Symfony ≥7.  It is loaded conditionally and
 *           should only be instantiated when `TypeInfo\Type` is available.
 */
class Symfony7MetadataResolver extends AbstractBsonMetadataResolver
{
    public function __construct()
    {
        parent::__construct(new PropertyInfoExtractor(
            [new ReflectionExtractor()],
            [new PhpDocExtractor(), new ReflectionExtractor()],
            [new PhpDocExtractor()],
            [new ReflectionExtractor()],
            [new ReflectionExtractor()]
        ));
    }
    protected function resolvePropertyTypes(
        PropertyInfoExtractor $propertyInfo,
        string $className,
        string $prop
    ): BsonPropertyType {
        // Symfony 7+ provides getType() (singular) returning a single TypeInfoType or null.
        // Fall back to getTypes() (plural) on older 7.x releases where both may coexist.
        $type = method_exists($propertyInfo, 'getType')
            ? $propertyInfo->getType($className, $prop)
            : $this->legacyGetSingleType($propertyInfo, $className, $prop);

        return $this->convertTypeInfoToBsonPropertyType($type);
    }

    private function convertTypeInfoToBsonPropertyType(?TypeInfoType $type): BsonPropertyType
    {
        if ($type === null) {
            return new BsonPropertyType();
        }

        $isNullable = false;

        if ($type instanceof NullableType) {
            $isNullable = true;
            $type = $type->getWrappedType();
        }

        if ($type instanceof CompositeTypeInterface) {
            $types = $type->getTypes();
            $firstNonNull = null;
            foreach ($types as $t) {
                if ($t instanceof NullableType) {
                    $inner = $t->getWrappedType();
                    if (!$inner->isIdentifiedBy(TypeIdentifier::NULL)) {
                        $firstNonNull = $inner;
                        break;
                    }
                }
                if (!$t->isIdentifiedBy(TypeIdentifier::NULL)) {
                    $firstNonNull = $t;
                    break;
                }
            }
            if ($firstNonNull !== null && $firstNonNull !== $type) {
                $isNullable = true;
                $type = $firstNonNull;
            }
        }

        if ($type instanceof ObjectType) {
            return new BsonPropertyType(
                className: $type->getClassName(),
                isCollection: false,
                isNullable: $isNullable,
            );
        }

        if ($type instanceof CollectionType) {
            $valueType = $type->getCollectionValueType();
            $classForColl = ($valueType instanceof ObjectType)
                ? $valueType->getClassName()
                : null;

            return new BsonPropertyType(
                className: $classForColl,
                isCollection: true,
                isNullable: $isNullable,
            );
        }

        return new BsonPropertyType(isNullable: $isNullable);
    }

    /**
     * Fallback for Symfony 7.x releases that still expose getTypes().
     *
     * @return TypeInfoType|null
     */
    private function legacyGetSingleType(
        PropertyInfoExtractor $propertyInfo,
        string $className,
        string $prop
    ): ?TypeInfoType {
        $types = $propertyInfo->getTypes($className, $prop);
        if (is_array($types) && count($types) > 0) {
            return $types[0];
        }
        return null;
    }
}
