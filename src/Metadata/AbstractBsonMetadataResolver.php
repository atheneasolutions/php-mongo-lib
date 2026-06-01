<?php

namespace Athenea\MongoLib\Metadata;

use Athenea\MongoLib\Attribute\BsonDiscriminator;
use Athenea\MongoLib\Attribute\BsonSerialize;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * Shared base for resolving BSON serialization metadata.
 *
 * Handles the common logic: class hierarchy traversal, attribute scanning,
 * property listing, snake-case conversion, discriminator map extraction,
 * and caching.  Subclasses provide a PropertyInfoExtractor instance
 * configured for their Symfony version, and implement the version-specific
 * type resolution via {@see resolvePropertyTypes()}.
 *
 * @see Symfony62MetadataResolver  For Symfony 5.x / 6.x.
 * @see Symfony7MetadataResolver   For Symfony 7.x / 8.x.
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
abstract class AbstractBsonMetadataResolver implements BsonMetadataResolverInterface
{
    /** @var array<string, BsonMetadata> */
    private array $cache = [];

    /** @var array<string, ReflectionClass> */
    private array $reflectionClassCache = [];

    /** @var array<string, ReflectionProperty[]> */
    private array $classPropertiesCache = [];

    private PropertyInfoExtractor $propertyInfo;

    public function __construct(PropertyInfoExtractor $propertyInfo)
    {
        $this->propertyInfo = $propertyInfo;
    }

    public function resolve(string $className): BsonMetadata
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $serializable = $this->resolveSerializableProps($className);
        $deserializable = $this->resolveDeserializableProps($className);
        $discriminatorMap = $this->resolveDiscriminatorMap($className);

        $metadata = new BsonMetadata($className, $serializable, $deserializable, $discriminatorMap);
        $this->cache[$className] = $metadata;

        return $metadata;
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->reflectionClassCache = [];
        $this->classPropertiesCache = [];
    }

    // -- Serialization resolution (identical for all Symfony versions) --

    /**
     * @return array<string, BsonSerializableProperty>
     */
    private function resolveSerializableProps(string $className): array
    {
        $reflection = $this->reflectionClassCache[$className] ??= new ReflectionClass($className);
        $fields = $this->classProperties($className);
        $fieldsById = array_reduce($fields, fn(array $acc, ReflectionProperty $x) => array_merge($acc, [$x->getName() => $x]), []);
        $propertyInfo = $this->propertyInfo;
        $props = $propertyInfo->getProperties($className);
        $result = [];

        foreach ($props as $prop) {
            $attribute = $this->findBsonSerializeAttribute($prop, $fieldsById, $reflection);
            if (!$attribute) {
                continue;
            }

            $fieldName = $this->toSnakeCase($prop);
            $name = $attribute->name ?? $fieldName;

            $result[$prop] = new BsonSerializableProperty((string) $name);
        }

        return $result;
    }

    /**
     * @return array<string, BsonDeserializableProperty>
     */
    private function resolveDeserializableProps(string $className): array
    {
        $fields = $this->classProperties($className);
        $fieldsById = array_reduce($fields, fn(array $acc, ReflectionProperty $x) => array_merge($acc, [$x->getName() => $x]), []);
        $propertyInfo = $this->propertyInfo;
        $props = $propertyInfo->getProperties($className);
        $reflection = $this->reflectionClassCache[$className] ??= new ReflectionClass($className);
        $result = [];

        foreach ($props as $prop) {
            $attribute = $this->findDeserializableAttribute($prop, $fieldsById, $reflection);
            if (!$attribute) {
                continue;
            }

            $propNameSnake = $this->toSnakeCase($prop);
            $bsonName = $attribute->name ?? $propNameSnake;
            $type = $this->resolvePropertyTypes($propertyInfo, $className, $prop);

            $result[$prop] = new BsonDeserializableProperty($bsonName, $type);
        }

        return $result;
    }

    /**
     * Find #[BsonSerialize] on a property (public) or on its getter method.
     *
     * @param array<string, ReflectionProperty> $fieldsById
     * @return ?BsonSerialize Resolved attribute instance, or null if none found.
     */
    private function findBsonSerializeAttribute(
        string $prop,
        array $fieldsById,
        ReflectionClass $reflection,
    ): ?BsonSerialize {
        $field = $fieldsById[$prop] ?? null;

        if ($field) {
            $attrs = $field->getAttributes(BsonSerialize::class);
            if (count($attrs) > 0) {
                return $attrs[0]->newInstance();
            }
        }

        foreach (['get', 'is', 'has', 'can'] as $prefix) {
            $getter = $prefix . $prop;
            if (!$reflection->hasMethod($getter)) {
                continue;
            }
            $method = $reflection->getMethod($getter);
            $attrs = $method->getAttributes(BsonSerialize::class);
            if (count($attrs) > 0) {
                return $attrs[0]->newInstance();
            }
        }

        return null;
    }

    /**
     * Find #[BsonSerialize] on a property (public) or on its setter method.
     */
    private function findDeserializableAttribute(
        string $prop,
        array $fieldsById,
        ReflectionClass $reflection,
    ): ?BsonSerialize {
        $field = $fieldsById[$prop] ?? null;

        if ($field) {
            $attrs = $field->getAttributes(BsonSerialize::class);
            if (count($attrs) > 0) {
                return $attrs[0]->newInstance();
            }
        }

        $setter = 'set' . $prop;
        if (!$reflection->hasMethod($setter)) {
            return null;
        }
        $method = $reflection->getMethod($setter);
        $attrs = $method->getAttributes(BsonSerialize::class);
        if (count($attrs) > 0) {
            return $attrs[0]->newInstance();
        }

        return null;
    }

    // -- Discriminator map resolution --

    private function resolveDiscriminatorMap(string $className): ?BsonDiscriminatorMap
    {
        $reflection = $this->reflectionClassCache[$className] ??= new ReflectionClass($className);

        // Prefer our own #[BsonDiscriminator] attribute
        $nativeAttrs = $reflection->getAttributes(BsonDiscriminator::class);
        if (count($nativeAttrs) > 0) {
            $attr = $nativeAttrs[0]->newInstance();
            return new BsonDiscriminatorMap($attr->typeProperty, $attr->mapping);
        }

        // Fallback: Symfony

        $attributes = [];

        if (class_exists(\Symfony\Component\Serializer\Attribute\DiscriminatorMap::class)) {
            $attributes = [...$attributes, ...$reflection->getAttributes(\Symfony\Component\Serializer\Attribute\DiscriminatorMap::class)];
        }

        if (class_exists(\Symfony\Component\Serializer\Annotation\DiscriminatorMap::class)) {
            $attributes = [...$attributes, ...$reflection->getAttributes(\Symfony\Component\Serializer\Annotation\DiscriminatorMap::class)];
        }

        foreach ($attributes as $refAttribute) {
            $discMap = $refAttribute->newInstance();

            $typeProperty = method_exists($discMap, 'getTypeProperty')
                ? $discMap->getTypeProperty()
                : $discMap->typeProperty;

            $mapping = method_exists($discMap, 'getMapping')
                ? $discMap->getMapping()
                : $discMap->mapping;

            return new BsonDiscriminatorMap($typeProperty, $mapping);
        }

        return null;
    }

    // -- Subclass contract --

    /**
     * Resolve the typed BSON property type for a given class property.
     *
     * This is the ONLY method that differs between Symfony versions.
     * Symfony 6.x returns `PropertyInfo\Type[]` from `getTypes()`.
     * Symfony 7+/8.x returns `TypeInfo\Type` from `getType()`.
     * Subclasses handle their respective API and convert to our
     * framework-agnostic {@see BsonPropertyType}.
     *
     * @param PropertyInfoExtractor $propertyInfo The extractor instance.
     * @param string                $className    The class being analysed.
     * @param string                $prop         The property name.
     * @return BsonPropertyType
     */
    abstract protected function resolvePropertyTypes(
        PropertyInfoExtractor $propertyInfo,
        string $className,
        string $prop
    ): BsonPropertyType;

    // -- Shared infrastructure --

    /**
     * @return ReflectionProperty[]
     */
    private function classProperties(string $className): array
    {
        if (isset($this->classPropertiesCache[$className])) {
            return $this->classPropertiesCache[$className];
        }

        $reflectionClass = $this->reflectionClassCache[$className] ??= new ReflectionClass($className);
        $props = $reflectionClass->getProperties();

        if ($parentClass = $reflectionClass->getParentClass()) {
            $parentProperties = $this->classProperties($parentClass->getName());
            $props = [...$parentProperties, ...$props];
        }

        $this->classPropertiesCache[$className] = $props;
        return $props;
    }

    /**
     * Convert a camelCase property name to snake_case.
     *
     * Uses the same regex logic as Symfony's UnicodeString::snake() but
     * without the UnicodeString dependency. Property names in PHP are
     * always ASCII, so mb_strtolower is sufficient.
     */
    private function toSnakeCase(string $value): string
    {
        return mb_strtolower(
            preg_replace(
                ['/(\p{Lu}+)(\p{Lu}\p{Ll})/u', '/([\p{Ll}0-9])(\p{Lu})/u'],
                '\1_\2',
                $value
            ),
            'UTF-8'
        );
    }
}
