<?php

namespace Athenea\MongoLib\Tests\Serializer;

use Athenea\MongoLib\Metadata\AbstractBsonMetadataResolver;
use Athenea\MongoLib\Metadata\BsonDeserializableProperty;
use Athenea\MongoLib\Metadata\BsonMetadata;
use Athenea\MongoLib\Metadata\BsonPropertyType;
use Athenea\MongoLib\Metadata\BsonSerializableProperty;
use Athenea\MongoLib\Tests\Model\AbstractAnimal;
use Athenea\MongoLib\Tests\Model\ArrayModel;
use Athenea\MongoLib\Tests\Model\CatModel;
use Athenea\MongoLib\Tests\Model\ChildModel;
use Athenea\MongoLib\Tests\Model\CustomNameModel;
use Athenea\MongoLib\Tests\Model\EnumModel;
use Athenea\MongoLib\Tests\Model\ExtendedSimpleModel;
use Athenea\MongoLib\Tests\Model\MethodAttributeModel;
use Athenea\MongoLib\Tests\Model\NoAttributeModel;
use Athenea\MongoLib\Tests\Model\PublicPropertyModel;
use Athenea\MongoLib\Tests\Model\SimpleModel;
use PHPUnit\Framework\TestCase;

/**
 * Shared test contract for ALL metadata resolver implementations.
 *
 * Concrete subclasses only need to override {@see createResolver()} to
 * provide a specific Symfony-version resolver.  All other tests are
 * inherited and run identically.
 *
 * @see Symfony62MetadataResolverTest
 * @see Symfony7MetadataResolverTest
 */
abstract class AbstractMetadataResolverTest extends TestCase
{
    abstract protected function createResolver(): AbstractBsonMetadataResolver;

    // -- Basic contract --

    public function testResolveReturnsBsonMetadata(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(SimpleModel::class);

        $this->assertInstanceOf(BsonMetadata::class, $metadata);
        $this->assertSame(SimpleModel::class, $metadata->className);
    }

    public function testResolveSimpleModelSerializableProps(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(SimpleModel::class);

        $props = $metadata->serializableProps;
        $this->assertArrayHasKey('name', $props);
        $this->assertArrayHasKey('count', $props);
        $this->assertArrayHasKey('active', $props);
        $this->assertArrayHasKey('ratio', $props);

        $this->assertInstanceOf(BsonSerializableProperty::class, $props['name']);
        $this->assertSame('name', $props['name']->bsonName);
        $this->assertSame('count', $props['count']->bsonName);
        $this->assertSame('active', $props['active']->bsonName);
        $this->assertSame('ratio', $props['ratio']->bsonName);
    }

    public function testResolveSimpleModelDeserializableProps(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(SimpleModel::class);

        $props = $metadata->deserializableProps;
        $this->assertArrayHasKey('name', $props);
        $this->assertArrayHasKey('count', $props);

        $this->assertInstanceOf(BsonDeserializableProperty::class, $props['name']);
        $this->assertSame('name', $props['name']->bsonName);
        $this->assertInstanceOf(BsonPropertyType::class, $props['name']->type);
    }

    // -- Caching --

    public function testResolveCachesIdenticalResult(): void
    {
        $resolver = $this->createResolver();

        $a = $resolver->resolve(SimpleModel::class);
        $b = $resolver->resolve(SimpleModel::class);

        $this->assertSame($a, $b, 'Same metadata instance should be returned on repeated calls');
    }

    public function testResetClearsCache(): void
    {
        $resolver = $this->createResolver();

        $a = $resolver->resolve(SimpleModel::class);
        $resolver->reset();
        $b = $resolver->resolve(SimpleModel::class);

        $this->assertNotSame($a, $b, 'New metadata instance should be created after reset');
    }

    // -- Inheritance --

    public function testInheritedPropertiesAreIncluded(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(ChildModel::class);

        $this->assertArrayHasKey('name', $metadata->serializableProps);
        $this->assertArrayHasKey('count', $metadata->serializableProps);
        $this->assertArrayHasKey('extra', $metadata->serializableProps);
    }

    public function testAbstractClassCanBeResolved(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(AbstractAnimal::class);

        $this->assertArrayHasKey('name', $metadata->serializableProps);
    }

    public function testCatModelInheritsAnimalProps(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(CatModel::class);

        $this->assertArrayHasKey('name', $metadata->serializableProps, 'Should inherit from AbstractAnimal');
        $this->assertArrayHasKey('lives', $metadata->serializableProps);
    }

    // -- Custom BSON field names --

    public function testCustomBsonFieldName(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(CustomNameModel::class);

        $this->assertSame('_id', $metadata->serializableProps['id']->bsonName);
        $this->assertSame('custom_field_name', $metadata->serializableProps['field']->bsonName);
    }

    public function testSubclassHasCustomBsonName(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(ExtendedSimpleModel::class);

        $this->assertSame('renamed_extra', $metadata->serializableProps['extra']->bsonName);
    }

    // -- Attribute placement (property vs method) --

    public function testMethodAttributeForGetter(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(MethodAttributeModel::class);

        $this->assertArrayHasKey('by', $metadata->serializableProps);
        $this->assertSame('by', $metadata->serializableProps['by']->bsonName);
    }

    public function testMethodAttributeForSetter(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(MethodAttributeModel::class);

        $this->assertArrayHasKey('by', $metadata->deserializableProps);
        $this->assertSame('by', $metadata->deserializableProps['by']->bsonName);
    }

    // -- Edge cases --

    public function testNoAttributeClassReturnsEmpty(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(NoAttributeModel::class);

        $this->assertEmpty($metadata->serializableProps);
        $this->assertEmpty($metadata->deserializableProps);
    }

    public function testPublicPropertiesAreDetected(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(PublicPropertyModel::class);

        $this->assertArrayHasKey('name', $metadata->serializableProps);
        $this->assertArrayHasKey('value', $metadata->serializableProps);
    }

    // -- Types (version-dependent, but basic assertions work for both) --

    public function testEnumPropertiesHaveType(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(EnumModel::class);

        $this->assertArrayHasKey('platform', $metadata->deserializableProps);
        $this->assertArrayHasKey('status', $metadata->deserializableProps);

        $this->assertInstanceOf(BsonPropertyType::class, $metadata->deserializableProps['platform']->type);
    }

    public function testArrayModelHasCollectionType(): void
    {
        $resolver = $this->createResolver();
        $metadata = $resolver->resolve(ArrayModel::class);

        $this->assertArrayHasKey('items', $metadata->deserializableProps);
        $this->assertArrayHasKey('tags', $metadata->deserializableProps);
    }
}
