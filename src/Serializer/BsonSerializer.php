<?php

namespace Athenea\MongoLib\Serializer;

use Athenea\MongoLib\Metadata\BsonDeserializableProperty;
use Athenea\MongoLib\Metadata\BsonDiscriminatorMap;
use Athenea\MongoLib\Metadata\BsonMetadata;
use Athenea\MongoLib\Metadata\BsonMetadataResolverInterface;
use Athenea\MongoLib\Metadata\BsonPropertyType;
use Athenea\MongoLib\Metadata\Symfony\Symfony62MetadataResolver;
use Athenea\MongoLib\Metadata\Symfony\Symfony7MetadataResolver;
use Athenea\MongoLib\Model\Base;
use Athenea\MongoLib\PropertyAccess\BsonPropertyAccessorInterface;
use Athenea\MongoLib\PropertyAccess\SymfonyPropertyAccessor;
use BackedEnum;
use DateTime;
use DateTimeInterface;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Type;
use MongoDB\BSON\Unserializable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\UTCDateTimeInterface;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use ReflectionClass;
use stdClass;

class BsonSerializer implements BsonSerializerInterface
{
    private static ?BsonPropertyAccessorInterface $propertyAccessorCache = null;

    private ?BsonMetadataResolverInterface $metadataResolver = null;

    public function __construct(?BsonPropertyAccessorInterface $propertyAccessor = null)
    {
        if ($propertyAccessor !== null) {
            self::$propertyAccessorCache = $propertyAccessor;
        }
    }

    public function setMetadataResolver(BsonMetadataResolverInterface $resolver): void
    {
        $this->metadataResolver = $resolver;
    }

    public function serialize(Base $object): array|stdClass
    {
        $resolver = $this->getResolver();
        $metadata = $resolver->resolve($object::class);
        $propertyAccessor = self::$propertyAccessorCache ??= SymfonyPropertyAccessor::getInstance();
        $normalization = new stdClass();

        foreach ($metadata->serializableProps as $propName => $prop) {
            try {
                $value = $propertyAccessor->getValue($object, $propName);
            } catch (\Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException) {
                continue;
            }
            $normalization->{$prop->bsonName} = $this->serializeValue($value);
        }

        return $normalization;
    }

    public function unserialize(Base $object, mixed $data): void
    {
        $resolver = $this->getResolver();
        $metadata = $resolver->resolve($object::class);
        $propertyAccessor = self::$propertyAccessorCache ??= SymfonyPropertyAccessor::getInstance();

        $object->__construct();

        foreach ($metadata->deserializableProps as $propName => $prop) {
            if (!$this->genericIsset($data, $prop->bsonName)) {
                continue;
            }

            $rawValue = $this->accesGeneric($data, $prop->bsonName);
            $value = $this->unserializeValue($rawValue, $prop->type);

            if ($value !== null || $prop->type->isNullable) {
                $propertyAccessor->setValue($object, $propName, $value);
            }
        }
    }

    public function diff(Base $old, Base $new): array
    {
        return $this->computeDiff($this->serialize($old), $this->serialize($new));
    }

    // -- Private: value serialization (recursive) --

    private function serializeValue(mixed $value): mixed
    {
        $builtinType = gettype($value);

        if ($builtinType === 'object') {
            $className = get_class($value);

            if ($className === DateTime::class) {
                return new UTCDateTime($value->getTimestamp() * 1000);
            }
            if (is_subclass_of($className, DateTimeInterface::class)) {
                return new UTCDateTime($value->getTimestamp() * 1000);
            }
            if (is_subclass_of($className, Serializable::class)) {
                return $value->bsonSerialize();
            }
            if (is_subclass_of($className, Type::class)) {
                return $value;
            }
            if (is_subclass_of($className, BackedEnum::class)) {
                return $value->value;
            }
            if ($value instanceof stdClass) {
                $newObj = new stdClass();
                foreach ($value as $k => $v) {
                    $k = $this->serializeValue($k);
                    $v = $this->serializeValue($v);
                    $newObj->{$k} = $v;
                }
                return $newObj;
            }

            throw new Exception("Class $className can't be bsonNormalized");
        }

        if ($builtinType === 'array') {
            $newArray = [];
            foreach ($value as $k => $v) {
                $k = $this->serializeValue($k);
                $v = $this->serializeValue($v);
                $newArray[$k] = $v;
            }
            return $newArray;
        }

        return $value;
    }

    // -- Private: value deserialization (recursive) --

    private function unserializeValue(mixed $value, BsonPropertyType $type): mixed
    {
        $builtinType = gettype($value);

        if ($builtinType === 'object') {
            if ($value instanceof UTCDateTimeInterface) {
                return $value->toDateTime();
            }
            if ($value instanceof BSONDocument) {
                return $this->unserializeValue($value->bsonSerialize(), $type);
            }
            if ($value instanceof BSONArray) {
                return $this->unserializeValue($value->bsonSerialize(), $type);
            }
            if ($value instanceof Unserializable) {
                $className = get_class($value);
                $x = new $className();
                $x->bsonUnserialize((array) $value);
                return $x;
            }
            if ($value instanceof stdClass && $type->className && is_subclass_of($type->className, Unserializable::class)) {
                $concreteClass = $this->findConcreteClass($value, $type->className);
                $x = new $concreteClass();
                $x->bsonUnserialize((array) $value);
                return $x;
            }
            if ($value instanceof stdClass && $type->isCollection) {
                $result = [];
                foreach ($value as $k => $v) {
                    $result[$k] = $this->unserializeValue($v, new BsonPropertyType());
                }
                return $result;
            }
            if ($value instanceof stdClass) {
                $newObj = new stdClass();
                foreach ($value as $k => $v) {
                    $newObj->{$k} = $this->unserializeValue($v, new BsonPropertyType());
                }
                return $newObj;
            }
            return $value;
        }

        if ($builtinType === 'array') {
            if ($type->isCollection) {
                $newArray = [];
                $elementType = $type->className
                    ? new BsonPropertyType(className: $type->className, isCollection: false)
                    : new BsonPropertyType();
                foreach ($value as $k => $v) {
                    $newArray[$k] = $this->unserializeValue($v, $elementType);
                }
                return $newArray;
            }

            if ($type->className && is_subclass_of($type->className, Unserializable::class)) {
                $concreteClass = $this->findConcreteClass($value, $type->className);
                $x = new $concreteClass();
                $x->bsonUnserialize($value);
                return $x;
            }

            $newArray = [];
            foreach ($value as $k => $v) {
                $newArray[$k] = $this->unserializeValue($v, new BsonPropertyType());
            }
            return $newArray;
        }

        if ($builtinType === 'string' || $builtinType === 'integer') {
            if ($type->className && is_subclass_of($type->className, BackedEnum::class)) {
                return $type->className::from($value);
            }
        }

        return $value;
    }

    // -- Private: diff --

    /**
     * @param array<int|string, mixed> $changes
     * @return array<int|string, mixed>
     */
    private function computeDiff(array|stdClass $aBson, array|stdClass $bBson, string $prefix = ''): array
    {
        $changes = [];
        $push = [];
        $set = [];
        $unset = [];
        $pull = [];

        foreach ($aBson as $aKey => $aValue) {
            $bValue = $this->accesGeneric($bBson, $aKey);

            if ($bValue === null && $aValue !== null) {
                $unset[$prefix . $aKey] = '';
            } elseif (gettype($aValue) !== gettype($bValue)) {
                $set[$prefix . $aKey] = $bValue;
            } elseif (is_object($aValue) && !is_object($bValue)) {
                $set[$prefix . $aKey] = $bValue;
            } elseif (!is_object($aValue) && is_object($bValue)) {
                $set[$prefix . $aKey] = $bValue;
            } elseif (!is_object($aValue) && !is_object($bValue)) {
                if ($aValue !== $bValue) {
                    $set[$prefix . $aKey] = $bValue;
                }
            } else {
                if (get_class($aValue) !== get_class($bValue)) {
                    $set[$prefix . $aKey] = $bValue;
                } else {
                    if ($aValue instanceof stdClass || (is_array($aValue) && $this->arrayIsAssoc($aValue))) {
                        $diff = $this->computeDiff($aValue, $bValue, $prefix . $aKey . '.');
                        if (count($diff) > 0) {
                            if (isset($diff['$push'])) {
                                $push = [...$push, ...$diff['$push']];
                            }
                            if (isset($diff['$pull'])) {
                                $pull = [...$pull, ...$diff['$pull']];
                            }
                            if (isset($diff['$set'])) {
                                $set = [...$set, ...$diff['$set']];
                            }
                            if (isset($diff['$unset'])) {
                                $unset = [...$unset, ...$diff['$unset']];
                            }
                        }
                    } elseif (is_array($aValue)) {
                        $set[$prefix . $aKey] = $bValue;
                    } else {
                        if ($aValue instanceof ObjectId) {
                            if ((string) $aValue !== (string) $bValue) {
                                $set[$prefix . $aKey] = $bValue;
                            }
                        } elseif ($aValue instanceof UTCDateTimeInterface) {
                            if ($aValue->toDateTime() != $bValue->toDateTime()) {
                                $set[$prefix . $aKey] = $bValue;
                            }
                        } else {
                            $set[$prefix . $aKey] = $bValue;
                        }
                    }
                }
            }
        }

        // fields in B but not in A
        foreach ($bBson as $bKey => $bValue) {
            $aValue = $this->accesGeneric($aBson, $bKey);
            if ($aValue === null && $bValue !== null) {
                $set[$prefix . $bKey] = $bValue;
            }
        }

        if (count($push) > 0) {
            $changes['$push'] = $push;
        }
        if (count($pull) > 0) {
            $changes['$pull'] = $pull;
        }
        if (count($set) > 0) {
            $changes['$set'] = $set;
        }
        if (count($unset) > 0) {
            $changes['$unset'] = $unset;
        }

        return $changes;
    }

    // -- Private: discriminator map --

    private function findConcreteClass(mixed $value, string $className): string
    {
        $classInfo = new ReflectionClass($className);
        if (!$classInfo->isAbstract()) {
            return $className;
        }

        $metadata = $this->getResolver()->resolve($className);
        $discMap = $metadata->discriminatorMap;

        if ($discMap !== null) {
            $typeProperty = $discMap->typeProperty;
            $valueType = is_array($value) ? ($value[$typeProperty] ?? null) : ($value->{$typeProperty} ?? null);

            $newClass = $discMap->mapping[$valueType] ?? null;
            if ($newClass !== null) {
                return $this->findConcreteClass($value, $newClass);
            }
        }

        throw new \LogicException(sprintf(
            'Cannot instantiate abstract class "%s" during deserialization. Provide a DiscriminatorMap attribute or use a concrete class type.',
            $className
        ));
    }

    // -- Private: utilities --

    private function genericIsset(mixed $data, string $key): bool
    {
        if (is_array($data)) {
            return isset($data[$key]);
        }
        if (is_object($data)) {
            return isset($data->{$key});
        }
        return false;
    }

    private function accesGeneric(mixed $data, string $key): mixed
    {
        if (is_array($data)) {
            return $data[$key] ?? null;
        }
        if (is_object($data)) {
            return $data->{$key} ?? null;
        }
        return null;
    }

    private function arrayIsAssoc(array $arr): bool
    {
        if (count($arr) === 0) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function getResolver(): BsonMetadataResolverInterface
    {
        if ($this->metadataResolver === null) {
            // Auto-detect: try Symfony7 first, fall back to Symfony62
            if (class_exists('Symfony\Component\TypeInfo\Type')) {
                $this->metadataResolver = new Symfony7MetadataResolver();
            } else {
                $this->metadataResolver = new Symfony62MetadataResolver();
            }
        }
        return $this->metadataResolver;
    }
}
