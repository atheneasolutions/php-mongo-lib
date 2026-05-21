<?php

namespace Athenea\MongoLib\Tests\Model;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class BasePerformanceTest extends TestCase
{
    private function resetStaticCache(): void
    {
        $ref = new ReflectionClass(\Athenea\MongoLib\Model\Base::class);

        $propertyInfoCache = $ref->getProperty('propertyInfoCache');
        $propertyInfoCache->setAccessible(true);
        $propertyInfoCache->setValue(null, null);

        $propertyAccessorCache = $ref->getProperty('propertyAccessorCache');
        $propertyAccessorCache->setAccessible(true);
        $propertyAccessorCache->setValue(null, null);

        $reflectionClassCache = $ref->getProperty('reflectionClassCache');
        $reflectionClassCache->setAccessible(true);
        $reflectionClassCache->setValue(null, []);

        $classPropertiesCache = $ref->getProperty('classPropertiesCache');
        $classPropertiesCache->setAccessible(true);
        $classPropertiesCache->setValue(null, []);

        $serializablePropertiesCache = $ref->getProperty('serializablePropertiesCache');
        $serializablePropertiesCache->setAccessible(true);
        $serializablePropertiesCache->setValue(null, []);

        $deserializablePropertiesCache = $ref->getProperty('deserializablePropertiesCache');
        $deserializablePropertiesCache->setAccessible(true);
        $deserializablePropertiesCache->setValue(null, []);
    }

    protected function setUp(): void
    {
        $this->resetStaticCache();
    }

    public function testBsonSerializeCachesPropertyInfoAcrossInstances(): void
    {
        $obj1 = new TestModel();
        $obj1->setName('test1');
        $obj1->setValue(10);
        $obj1->setField('f1');

        $obj2 = new TestModel();
        $obj2->setName('test2');
        $obj2->setValue(20);
        $obj2->setField('f2');

        $serialized1 = $obj1->bsonSerialize();
        $serialized2 = $obj2->bsonSerialize();

        $ref = new ReflectionClass(\Athenea\MongoLib\Model\Base::class);
        $propertyInfoCache = $ref->getProperty('propertyInfoCache');
        $propertyInfoCache->setAccessible(true);
        $cached = $propertyInfoCache->getValue(null);

        $this->assertNotNull($cached, 'propertyInfoCache should be populated after first serialization');
        $this->assertSame($cached, $propertyInfoCache->getValue(null), 'Same PropertyInfoExtractor instance should be reused');
        $this->assertSame('test1', $serialized1->name);
        $this->assertSame('test2', $serialized2->name);
    }

    public function testBsonSerializeCachesReflectionClass(): void
    {
        $obj = new TestModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(\Athenea\MongoLib\Model\Base::class);
        $cache = $ref->getProperty('reflectionClassCache');
        $cache->setAccessible(true);
        $cachedArray = $cache->getValue(null);

        $this->assertArrayHasKey(TestModel::class, $cachedArray, 'reflectionClassCache should contain TestModel');
        $this->assertInstanceOf(ReflectionClass::class, $cachedArray[TestModel::class]);
    }

    public function testBsonSerializeCachesClassProperties(): void
    {
        $obj = new TestModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(\Athenea\MongoLib\Model\Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cachedArray = $cache->getValue(null);

        $this->assertArrayHasKey(TestModel::class, $cachedArray, 'classPropertiesCache should contain TestModel');
        $this->assertNotEmpty($cachedArray[TestModel::class]);
    }

    public function testChildModelCachesSeparately(): void
    {
        $parent = new TestModel();
        $parent->setName('parent');
        $parent->bsonSerialize();

        $child = new ChildTestModel();
        $child->setName('child');
        $child->setExtra('extra');
        $child->bsonSerialize();

        $ref = new ReflectionClass(\Athenea\MongoLib\Model\Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cachedArray = $cache->getValue(null);

        $this->assertArrayHasKey(TestModel::class, $cachedArray);
        $this->assertArrayHasKey(ChildTestModel::class, $cachedArray);

        $parentProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cachedArray[TestModel::class]);
        $childProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cachedArray[ChildTestModel::class]);

        $this->assertContains('extra', $childProps);
        $this->assertNotContains('extra', $parentProps);
    }

    public function testSerializationIsFasterWithCaching(): void
    {
        $this->resetStaticCache();

        $iterations = 50;

        $startUncached = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->resetStaticCache();
            $obj = new TestModel();
            $obj->setName("test$i");
            $obj->setValue($i);
            $obj->setField("f$i");
            $obj->bsonSerialize();
        }
        $uncachedDuration = hrtime(true) - $startUncached;

        $this->resetStaticCache();

        $obj0 = new TestModel();
        $obj0->setName('warmup');
        $obj0->bsonSerialize();

        $startCached = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $obj = new TestModel();
            $obj->setName("test$i");
            $obj->setValue($i);
            $obj->setField("f$i");
            $obj->bsonSerialize();
        }
        $cachedDuration = hrtime(true) - $startCached;

        $this->assertLessThan(
            $uncachedDuration * 0.8,
            $cachedDuration,
            "Cached serialization should be at least 20% faster than uncached (uncached: $uncachedDuration ns, cached: $cachedDuration ns)"
        );
    }
}