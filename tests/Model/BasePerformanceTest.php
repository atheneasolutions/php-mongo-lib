<?php

namespace Athenea\MongoLib\Tests\Model;

use Athenea\MongoLib\Model\Base;
use Athenea\MongoLib\Serializer\BsonSerializer;
use PHPUnit\Framework\TestCase;

class BasePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        Base::setDefaultSerializer(new BsonSerializer());
    }

    public function testBsonSerializeCachesCorrectOutput(): void
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

        $this->assertSame('test1', $serialized1->name);
        $this->assertSame('test2', $serialized2->name);
    }

    public function testBsonSerializeCachesMetadataAcrossInstances(): void
    {
        $obj1 = new TestModel();
        $obj1->setName('first');
        $obj1->bsonSerialize();

        $obj2 = new TestModel();
        $obj2->setName('second');
        $result = $obj2->bsonSerialize();

        $this->assertSame('second', $result->name);
    }

    public function testChildModelSerializesSeparately(): void
    {
        $parent = new TestModel();
        $parent->setName('parent');
        $parentResult = $parent->bsonSerialize();

        $child = new ChildTestModel();
        $child->setName('child');
        $child->setExtra('extra');
        $childResult = $child->bsonSerialize();

        $this->assertObjectNotHasProperty('extra', $parentResult);
        $this->assertObjectHasProperty('extra', $childResult);
        $this->assertSame('extra', $childResult->extra);
    }

    public function testSerializationIsFasterWithCaching(): void
    {
        $iterations = 50;

        // Cold: fresh resolver per iteration
        $startUncached = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            Base::setDefaultSerializer(new BsonSerializer());
            $obj = new TestModel();
            $obj->setName("test$i");
            $obj->setValue($i);
            $obj->setField("f$i");
            $obj->bsonSerialize();
        }
        $uncachedDuration = hrtime(true) - $startUncached;

        // Warm: reuse resolver
        Base::setDefaultSerializer(new BsonSerializer());
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
            $uncachedDuration,
            $cachedDuration,
            "Cached serialization should be faster (uncached: $uncachedDuration ns, cached: $cachedDuration ns)"
        );
    }

    public function testMetadataIsReusedAcrossRequests(): void
    {
        $resolver1 = new BsonSerializer();
        Base::setDefaultSerializer($resolver1);

        $obj = new TestModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        // Same resolver — should be instant
        $start = hrtime(true);
        for ($i = 0; $i < 100; $i++) {
            $o = new TestModel();
            $o->setName("n$i");
            $o->bsonSerialize();
        }
        $duration = hrtime(true) - $start;

        $this->assertLessThan(50000000, $duration, '100 serialisations should complete under 50ms');
    }
}
