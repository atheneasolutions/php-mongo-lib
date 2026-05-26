<?php

namespace Athenea\MongoLib\Metadata\Symfony;

use Athenea\MongoLib\Metadata\AbstractBsonMetadataResolver;
use Athenea\MongoLib\Metadata\BsonPropertyType;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * Metadata resolver for Symfony 5.x / 6.x.
 *
 * Symfony 6.x uses `PropertyInfo\Type` (a single class representing
 * built-in, object, and collection types).  `PropertyInfoExtractor::getTypes()`
 * returns `Type[]` — we take the first type and convert it to {@see BsonPropertyType}.
 *
 * @see AbstractBsonMetadataResolver
 * @see Symfony7MetadataResolver
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
class Symfony62MetadataResolver extends AbstractBsonMetadataResolver
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
        $types = $propertyInfo->getTypes($className, $prop);

        if (!is_array($types) || count($types) === 0) {
            return new BsonPropertyType();
        }

        /** @var Type $type */
        $type = $types[0];

        $isNullable = $type->isNullable();
        $isCollection = $type->isCollection();
        $builtin = $type->getBuiltinType();

        $className = null;
        if ($builtin === Type::BUILTIN_TYPE_OBJECT) {
            $className = $type->getClassName();
        } elseif ($isCollection) {
            $valueTypes = $type->getCollectionValueTypes();
            if (count($valueTypes) > 0 && $valueTypes[0]->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT) {
                $className = $valueTypes[0]->getClassName();
            }
        }

        return new BsonPropertyType(
            className: $className,
            isCollection: $isCollection,
            isNullable: $isNullable,
        );
    }
}
