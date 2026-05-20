<?php

namespace Athenea\MongoLib\Tests\Model;

use Athenea\MongoLib\Model\Base;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class BaseTest extends TestCase
{
    private function resetStaticCache(): void
    {
        $ref = new ReflectionClass(Base::class);
        foreach (['propertyInfoCache', 'propertyAccessorCache', 'reflectionClassCache', 'classPropertiesCache'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, $prop === 'propertyInfoCache' || $prop === 'propertyAccessorCache' ? null : []);
        }
    }

    protected function setUp(): void
    {
        $this->resetStaticCache();
    }

    // ========================================
    // CACHING TESTS
    // ========================================

    public function testPropertyInfoIsCachedAcrossInstances(): void
    {
        $obj1 = new SimpleModel();
        $obj1->setName('a');
        $obj1->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('propertyInfoCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $this->assertNotNull($cached);

        $obj2 = new SimpleModel();
        $obj2->setName('b');
        $obj2->bsonSerialize();

        $this->assertSame($cached, $cache->getValue(null), 'Same PropertyInfoExtractor instance');
    }

    public function testReflectionClassIsCached(): void
    {
        $obj = new SimpleModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('reflectionClassCache');
        $cache->setAccessible(true);

        $this->assertArrayHasKey(SimpleModel::class, $cache->getValue(null));
    }

    public function testClassPropertiesIsCached(): void
    {
        $obj = new SimpleModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);

        $this->assertArrayHasKey(SimpleModel::class, $cache->getValue(null));
    }

    public function testChildClassCachedSeparately(): void
    {
        $parent = new SimpleModel();
        $parent->setName('p');
        $parent->bsonSerialize();

        $child = new ChildModel();
        $child->setName('c');
        $child->setExtra('e');
        $child->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $this->assertArrayHasKey(SimpleModel::class, $cached);
        $this->assertArrayHasKey(ChildModel::class, $cached);

        $parentProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[SimpleModel::class]);
        $childProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[ChildModel::class]);

        $this->assertNotContains('extra', $parentProps);
        $this->assertContains('extra', $childProps);
    }

    public function testPropertyAccessorIsCached(): void
    {
        $obj = new SimpleModel();
        $obj->setName('test');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('propertyAccessorCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $this->assertNotNull($cached);
    }

    public function testCachedSerializationIsFasterThanUncached(): void
    {
        $iterations = 50;

        $startUncached = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->resetStaticCache();
            $obj = new SimpleModel();
            $obj->setName("test$i");
            $obj->setCount($i);
            $obj->bsonSerialize();
        }
        $uncachedDuration = hrtime(true) - $startUncached;

        $this->resetStaticCache();
        $warmup = new SimpleModel();
        $warmup->setName('warmup');
        $warmup->bsonSerialize();

        $startCached = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $obj = new SimpleModel();
            $obj->setName("test$i");
            $obj->setCount($i);
            $obj->bsonSerialize();
        }
        $cachedDuration = hrtime(true) - $startCached;

        $this->assertLessThan(
            $uncachedDuration * 0.8,
            $cachedDuration,
            "Cached should be at least 20% faster (uncached: $uncachedDuration ns, cached: $cachedDuration ns)"
        );
    }

    // ========================================
    // INHERITANCE / SUBCLASS PROPERTY MAPPING
    // ========================================

    public function testSerializeChildIncludesAllParentFields(): void
    {
        $obj = new ChildModel();
        $obj->setName('parent_field');
        $obj->setCount(10);
        $obj->setActive(true);
        $obj->setExtra('child_field');

        $result = $obj->bsonSerialize();

        $this->assertSame('parent_field', $result->name);
        $this->assertSame(10, $result->count);
        $this->assertTrue($result->active);
        $this->assertSame('child_field', $result->extra);
    }

    public function testSerializeGrandchildIncludesAllAncestorFields(): void
    {
        $obj = new GrandchildModel();
        $obj->setName('from_simple');
        $obj->setCount(5);
        $obj->setActive(false);
        $obj->setRatio(1.5);
        $obj->setExtra('from_child');
        $obj->setDeep('from_grandchild');

        $result = $obj->bsonSerialize();

        $this->assertSame('from_simple', $result->name);
        $this->assertSame(5, $result->count);
        $this->assertFalse($result->active);
        $this->assertEquals(1.5, $result->ratio);
        $this->assertSame('from_child', $result->extra);
        $this->assertSame('from_grandchild', $result->deep);
    }

    public function testSubclassCustomNameOverridesInherited(): void
    {
        $obj = new ExtendedSimpleModel();
        $obj->setName('inherited');
        $obj->setExtra('custom');
        $obj->setPlatform(TestPlatform::Mobile);

        $result = $obj->bsonSerialize();

        $this->assertSame('inherited', $result->name);
        $this->assertObjectHasProperty('renamed_extra', $result);
        $this->assertSame('custom', $result->renamed_extra);
        $this->assertSame('mobile', $result->platform);
    }

    public function testSubclassDoesNotLeakToParentSerialization(): void
    {
        $parent = new SimpleModel();
        $parent->setName('parent_only');

        $child = new ChildModel();
        $child->setName('child_val');
        $child->setExtra('only_in_child');

        $parentResult = $parent->bsonSerialize();
        $childResult = $child->bsonSerialize();

        $this->assertObjectNotHasProperty('extra', $parentResult);
        $this->assertObjectHasProperty('extra', $childResult);
        $this->assertSame('only_in_child', $childResult->extra);
    }

    public function testClassPropertiesCacheIncludesInheritedPropsForSubclass(): void
    {
        $obj = new GrandchildModel();
        $obj->setName('a');
        $obj->setExtra('b');
        $obj->setDeep('c');
        $obj->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $this->assertArrayHasKey(GrandchildModel::class, $cached);

        $propNames = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[GrandchildModel::class]);

        $this->assertContains('name', $propNames, 'From SimpleModel');
        $this->assertContains('count', $propNames, 'From SimpleModel');
        $this->assertContains('active', $propNames, 'From SimpleModel');
        $this->assertContains('ratio', $propNames, 'From SimpleModel');
        $this->assertContains('extra', $propNames, 'From ChildModel');
        $this->assertContains('deep', $propNames, 'From GrandchildModel');
    }

    // ========================================
    // DISCRIMINATOR MAP / ABSTRACT SUBCLASS
    // ========================================

    public function testSerializeConcreteSubclassOfAbstract(): void
    {
        $cat = new CatModel();
        $cat->setName('Whiskers');
        $cat->setLives(9);

        $result = $cat->bsonSerialize();

        $this->assertSame('Whiskers', $result->name);
        $this->assertSame(9, $result->lives);
    }

    public function testDifferentConcreteSubclassesSerializeIndependently(): void
    {
        $cat = new CatModel();
        $cat->setName('Cat');
        $cat->setLives(9);

        $dog = new DogModel();
        $dog->setName('Dog');
        $dog->setBreed('Labrador');

        $catResult = $cat->bsonSerialize();
        $dogResult = $dog->bsonSerialize();

        $this->assertObjectHasProperty('lives', $catResult);
        $this->assertObjectNotHasProperty('breed', $catResult);

        $this->assertObjectHasProperty('breed', $dogResult);
        $this->assertObjectNotHasProperty('lives', $dogResult);

        $this->assertSame('Cat', $catResult->name);
        $this->assertSame('Dog', $dogResult->name);
    }

    public function testAbstractSubclassCacheContainsOnlyOwnProperties(): void
    {
        $cat = new CatModel();
        $cat->setName('test');
        $cat->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $this->assertArrayHasKey(CatModel::class, $cached);
        $this->assertArrayHasKey(AbstractAnimal::class, $cached);

        $catPropNames = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[CatModel::class]);
        $abstractPropNames = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[AbstractAnimal::class]);

        $this->assertContains('lives', $catPropNames);
        $this->assertContains('name', $catPropNames, 'Inherited from AbstractAnimal');
        $this->assertContains('name', $abstractPropNames);
        $this->assertNotContains('lives', $abstractPropNames);
    }

    // ========================================
    // bsonSerialize: PRIMITIVE TYPES
    // ========================================

    public function testSerializePrimitives(): void
    {
        $obj = new SimpleModel();
        $obj->setName('hello');
        $obj->setCount(42);
        $obj->setActive(true);
        $obj->setRatio(3.14);

        $result = $obj->bsonSerialize();

        $this->assertSame('hello', $result->name);
        $this->assertSame(42, $result->count);
        $this->assertTrue($result->active);
        $this->assertEquals(3.14, $result->ratio);
    }

    public function testSerializeNullPrimitives(): void
    {
        $obj = new SimpleModel();

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('name', $result);
        $this->assertNull($result->name);
        $this->assertNull($result->count);
        $this->assertNull($result->active);
        $this->assertNull($result->ratio);
    }

    // ========================================
    // bsonSerialize: SNAKE_CASE CONVERSION
    // ========================================

    public function testSerializeSnakeCaseConversion(): void
    {
        $obj = new CustomNameModel();
        $obj->setField('test');

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('custom_field_name', $result);
        $this->assertSame('test', $result->custom_field_name);
    }

    public function testSerializeCustomNameOverridesSnakeCase(): void
    {
        $obj = new CustomNameModel();
        $obj->setId(new ObjectId());

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('_id', $result);
        $this->assertInstanceOf(ObjectId::class, $result->_id);
    }

    // ========================================
    // bsonSerialize: DATETIME
    // ========================================

    public function testSerializeDateTime(): void
    {
        $dt = new \DateTime('2024-06-15 10:30:00');
        $obj = new DateTimeModel();
        $obj->setTimestamp($dt);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(UTCDateTime::class, $result->timestamp);
        $this->assertEquals($dt, $result->timestamp->toDateTime());
    }

    public function testSerializeNullDateTime(): void
    {
        $obj = new DateTimeModel();

        $result = $obj->bsonSerialize();

        $this->assertNull($result->timestamp);
    }

    // ========================================
    // bsonSerialize: OBJECTID
    // ========================================

    public function testSerializeObjectId(): void
    {
        $oid = new ObjectId();
        $obj = new ObjectIdModel();
        $obj->setId($oid);
        $obj->setRefId($oid);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame((string) $oid, (string) $result->_id);
        $this->assertInstanceOf(ObjectId::class, $result->ref_id);
    }

    // ========================================
    // bsonSerialize: BACKED ENUM
    // ========================================

    public function testSerializeStringBackedEnum(): void
    {
        $obj = new EnumModel();
        $obj->setPlatform(TestPlatform::Mobile);

        $result = $obj->bsonSerialize();

        $this->assertSame('mobile', $result->platform);
    }

    public function testSerializeIntBackedEnum(): void
    {
        $obj = new EnumModel();
        $obj->setStatus(TestStatus::Active);

        $result = $obj->bsonSerialize();

        $this->assertSame(1, $result->status);
    }

    public function testSerializeNullEnum(): void
    {
        $obj = new EnumModel();

        $result = $obj->bsonSerialize();

        $this->assertNull($result->platform);
        $this->assertNull($result->status);
    }

    // ========================================
    // bsonSerialize: NESTED SERIALIZABLE
    // ========================================

    public function testSerializeNestedObject(): void
    {
        $child = new SimpleModel();
        $child->setName('child');
        $child->setCount(5);

        $obj = new NestedModel();
        $obj->setChild($child);

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('child', $result);
        $this->assertSame('child', $result->child->name);
        $this->assertSame(5, $result->child->count);
    }

    public function testSerializeNullNestedObject(): void
    {
        $obj = new NestedModel();

        $result = $obj->bsonSerialize();

        $this->assertNull($result->child);
    }

    // ========================================
    // bsonSerialize: ARRAYS
    // ========================================

    public function testSerializeArrayOfPrimitives(): void
    {
        $obj = new ArrayModel();
        $obj->setTags(['a', 'b', 'c']);

        $result = $obj->bsonSerialize();

        $this->assertSame(['a', 'b', 'c'], $result->tags);
    }

    public function testSerializeArrayOfNestedObjects(): void
    {
        $item1 = new SimpleModel();
        $item1->setName('first');
        $item2 = new SimpleModel();
        $item2->setName('second');

        $obj = new ArrayModel();
        $obj->setItems([$item1, $item2]);

        $result = $obj->bsonSerialize();

        $this->assertCount(2, $result->items);
        $this->assertSame('first', $result->items[0]->name);
        $this->assertSame('second', $result->items[1]->name);
    }

    public function testSerializeEmptyArray(): void
    {
        $obj = new ArrayModel();

        $result = $obj->bsonSerialize();

        $this->assertSame([], $result->items);
        $this->assertSame([], $result->tags);
    }

    // ========================================
    // bsonSerialize: stdClass
    // ========================================

    public function testSerializeStdClass(): void
    {
        $meta = new \stdClass();
        $meta->key = 'value';
        $meta->count = 3;

        $obj = new StdClassModel();
        $obj->setMetadata($meta);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result->metadata);
        $this->assertSame('value', $result->metadata->key);
        $this->assertSame(3, $result->metadata->count);
    }

    public function testSerializeStdClassWithNestedArray(): void
    {
        $meta = new \stdClass();
        $meta->list = ['a', 'b'];
        $meta->number = 42;

        $obj = new StdClassModel();
        $obj->setMetadata($meta);

        $result = $obj->bsonSerialize();

        $this->assertSame(['a', 'b'], $result->metadata->list);
        $this->assertSame(42, $result->metadata->number);
    }

    // ========================================
    // bsonSerialize: PUBLIC PROPERTIES
    // ========================================

    public function testSerializePublicProperties(): void
    {
        $obj = new PublicPropertyModel();
        $obj->name = 'public';
        $obj->value = 99;

        $result = $obj->bsonSerialize();

        $this->assertSame('public', $result->name);
        $this->assertSame(99, $result->value);
    }

    // ========================================
    // bsonSerialize: METHOD-LEVEL ATTRIBUTE
    // ========================================

    public function testSerializeMethodAttribute(): void
    {
        $obj = new MethodAttributeModel();
        $obj->setOrigin('user123');

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('by', $result);
        $this->assertSame('user123', $result->by);
    }

    // ========================================
    // bsonSerialize: VIRTUAL COMPUTED FIELDS
    // ========================================

    public function testSerializeVirtualComputedField(): void
    {
        $obj = new VirtualFieldModel();
        $obj->setName('test');

        $result = $obj->bsonSerialize();

        $this->assertObjectHasProperty('computed', $result);
        $this->assertSame('always_this', $result->computed);
    }

    // ========================================
    // bsonSerialize: NO ATTRIBUTE = EXCLUDED
    // ========================================

    public function testSerializeExcludesNonAttributedProperties(): void
    {
        $obj = new NoAttributeModel();
        $obj->setName('hidden');

        $result = $obj->bsonSerialize();

        $this->assertEmpty((array) $result, 'Properties without #[BsonSerialize] should not appear');
    }

    // ========================================
    // bsonSerialize: MIXED TYPE VALUES
    // ========================================

    public function testSerializeMixedString(): void
    {
        $obj = new MixedValueModel();
        $obj->setValue('hello');

        $result = $obj->bsonSerialize();
        $this->assertSame('hello', $result->value);
    }

    public function testSerializeMixedInt(): void
    {
        $obj = new MixedValueModel();
        $obj->setValue(42);

        $result = $obj->bsonSerialize();
        $this->assertSame(42, $result->value);
    }

    public function testSerializeMixedArray(): void
    {
        $obj = new MixedValueModel();
        $obj->setValue(['a', 'b']);

        $result = $obj->bsonSerialize();
        $this->assertSame(['a', 'b'], $result->value);
    }

    // ========================================
    // bsonSerialize: DEEPLY NESTED
    // ========================================

    public function testSerializeDeeplyNestedObjects(): void
    {
        $inner = new SimpleModel();
        $inner->setName('inner');
        $inner->setCount(1);

        $middle = new NestedModel();
        $middle->setChild($inner);

        $outer = new NestedModel();
        $outer->setChild($middle);

        $result = $outer->bsonSerialize();

        $this->assertObjectHasProperty('child', $result);
        $this->assertObjectHasProperty('child', $result->child);
        $this->assertSame('inner', $result->child->child->name);
        $this->assertSame(1, $result->child->child->count);
    }

    public function testSerializeNestedSubclass(): void
    {
        $cat = new CatModel();
        $cat->setName('Whiskers');
        $cat->setLives(9);

        $obj = new NestedModel();
        $obj->setChild($cat);

        $result = $obj->bsonSerialize();

        $this->assertSame('Whiskers', $result->child->name);
        $this->assertSame(9, $result->child->lives);
    }

    public function testSerializeArrayOfSubclasses(): void
    {
        $cat = new CatModel();
        $cat->setName('Cat');
        $cat->setLives(9);

        $dog = new DogModel();
        $dog->setName('Dog');
        $dog->setBreed('Poodle');

        $obj = new ArrayModel();
        $obj->setItems([$cat, $dog]);

        $result = $obj->bsonSerialize();

        $this->assertCount(2, $result->items);
        $this->assertSame('Cat', $result->items[0]->name);
        $this->assertSame(9, $result->items[0]->lives);
        $this->assertSame('Dog', $result->items[1]->name);
        $this->assertSame('Poodle', $result->items[1]->breed);
    }

    // ========================================
    // bsonSerialize: ARRAY OF SPECIAL TYPES
    // ========================================

    public function testSerializeArrayOfObjectIds(): void
    {
        $obj = new ArrayModel();
        $obj->setTags([new ObjectId(), new ObjectId()]);

        $result = $obj->bsonSerialize();

        $this->assertCount(2, $result->tags);
        $this->assertInstanceOf(ObjectId::class, $result->tags[0]);
        $this->assertInstanceOf(ObjectId::class, $result->tags[1]);
    }

    public function testSerializeArrayOfDateTimes(): void
    {
        $dt1 = new \DateTime('2024-01-01');
        $dt2 = new \DateTime('2024-06-15');

        $obj = new ArrayModel();
        $obj->setTags([$dt1, $dt2]);

        $result = $obj->bsonSerialize();

        $this->assertCount(2, $result->tags);
        $this->assertInstanceOf(UTCDateTime::class, $result->tags[0]);
        $this->assertInstanceOf(UTCDateTime::class, $result->tags[1]);
    }

    public function testSerializeArrayOfBackedEnums(): void
    {
        $obj = new ArrayModel();
        $obj->setTags([TestPlatform::Web, TestPlatform::Mobile]);

        $result = $obj->bsonSerialize();

        $this->assertSame(['web', 'mobile'], $result->tags);
    }

    // ========================================
    // bsonSerialize: EDGE CASES
    // ========================================

    public function testSerializeEmptyObjectProducesEmptyStdClass(): void
    {
        $obj = new NoAttributeModel();
        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEmpty((array) $result);
    }

    public function testSerializeAlwaysReturnsStdClass(): void
    {
        $obj = new SimpleModel();
        $obj->setName('test');

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testSerializeMultipleCallsAreConsistent(): void
    {
        $obj = new SimpleModel();
        $obj->setName('test');
        $obj->setCount(42);

        $result1 = $obj->bsonSerialize();
        $result2 = $obj->bsonSerialize();

        $this->assertEquals($result1, $result2);
    }

    public function testSerializeAfterMutationReflectsChange(): void
    {
        $obj = new SimpleModel();
        $obj->setName('original');
        $result1 = $obj->bsonSerialize();

        $obj->setName('modified');
        $result2 = $obj->bsonSerialize();

        $this->assertSame('original', $result1->name);
        $this->assertSame('modified', $result2->name);
    }

    public function testSerializeBoolWithIsPrefix(): void
    {
        $obj = new SimpleModel();
        $obj->setActive(true);

        $result = $obj->bsonSerialize();

        $this->assertTrue($result->active);
    }

    // ========================================
    // bsonChanges
    // ========================================

    public function testBsonChangesDetectsModifiedField(): void
    {
        $old = new SimpleModel();
        $old->setName('old');

        $new = new SimpleModel();
        $new->setName('new');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame('new', $changes['$set']['name']);
    }

    public function testBsonChangesNoChanges(): void
    {
        $old = new SimpleModel();
        $old->setName('same');

        $new = new SimpleModel();
        $new->setName('same');

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testBsonChangesEqualObjectIdsNoChange(): void
    {
        $id = new ObjectId();
        $old = new ObjectIdModel();
        $old->setId($id);
        $old->setRefId($id);

        $new = new ObjectIdModel();
        $new->setId($id);
        $new->setRefId($id);

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testBsonChangesFieldRemoved(): void
    {
        $old = new SimpleModel();
        $old->setName('test');
        $old->setCount(5);

        $new = new SimpleModel();
        $new->setName('test');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$unset', $changes);
        $this->assertArrayHasKey('count', $changes['$unset']);
    }

    public function testBsonChangesFieldAdded(): void
    {
        $old = new SimpleModel();
        $old->setName('test');

        $new = new SimpleModel();
        $new->setName('test');
        $new->setCount(5);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('count', $changes['$set']);
        $this->assertSame(5, $changes['$set']['count']);
    }

    public function testBsonChangesEqualUTCDateTimesNoChange(): void
    {
        $dt = new \DateTime('2024-06-15');
        $old = new DateTimeModel();
        $old->setTimestamp($dt);

        $new = new DateTimeModel();
        $new->setTimestamp($dt);

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testBsonChangesDetectsNewObjectIdInAdded(): void
    {
        $old = new ObjectIdModel();
        $old->setId(new ObjectId());

        $new = new ObjectIdModel();
        $new->setId($old->getId());
        $new->setRefId(new ObjectId());

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('ref_id', $changes['$set']);
    }

    public function testBsonChangesDetectsDateTimeChange(): void
    {
        $old = new DateTimeModel();
        $old->setTimestamp(new \DateTime('2024-01-01'));

        $new = new DateTimeModel();
        $new->setTimestamp(new \DateTime('2024-06-15'));

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('timestamp', $changes['$set']);
    }

    public function testBsonChangesWithEnumValueChange(): void
    {
        $old = new EnumModel();
        $old->setPlatform(TestPlatform::Web);

        $new = new EnumModel();
        $new->setPlatform(TestPlatform::Mobile);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame('mobile', $changes['$set']['platform']);
    }

    public function testBsonChangesWithBoolChange(): void
    {
        $old = new SimpleModel();
        $old->setActive(false);

        $new = new SimpleModel();
        $new->setActive(true);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertTrue($changes['$set']['active']);
    }

    public function testBsonChangesWithNullToValue(): void
    {
        $old = new SimpleModel();

        $new = new SimpleModel();
        $new->setName('new_value');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame('new_value', $changes['$set']['name']);
    }

    public function testBsonChangesWithValueToNull(): void
    {
        $old = new SimpleModel();
        $old->setName('existing');

        $new = new SimpleModel();

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$unset', $changes);
        $this->assertArrayHasKey('name', $changes['$unset']);
    }

    public function testBsonChangesSubclassFieldsTracked(): void
    {
        $old = new ChildModel();
        $old->setName('same');
        $old->setExtra('old_extra');

        $new = new ChildModel();
        $new->setName('same');
        $new->setExtra('new_extra');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('extra', $changes['$set']);
        $this->assertSame('new_extra', $changes['$set']['extra']);
        $this->assertArrayNotHasKey('name', $changes['$set'] ?? [], 'Unchanged parent field should not appear in $set');
    }

    public function testBsonChangesBetweenSubclassAndParent(): void
    {
        $old = new SimpleModel();
        $old->setName('same');

        $new = new ChildModel();
        $new->setName('same');
        $new->setExtra('added');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('extra', $changes['$set']);
    }

    // ========================================
    // bsonSerialize: REAL-WORLD PATTERNS
    // ========================================

    public function testSerializeGenericFieldPattern(): void
    {
        $obj = new MixedValueModel();
        $obj->setValue('ESTADO_CONCIENC');
        $obj->setPreviousValue('Alerta');

        $result = $obj->bsonSerialize();

        $this->assertSame('ESTADO_CONCIENC', $result->value);
        $this->assertSame('Alerta', $result->previous_value);
    }

    public function testSerializeModelWithCustomIdName(): void
    {
        $oid = new ObjectId('507f1f77bcf86cd799439011');
        $obj = new CustomNameModel();
        $obj->setId($oid);
        $obj->setField('data');
        $dt = new \DateTime('2024-03-15 12:00:00');
        $obj->setCreatedAt($dt);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame('507f1f77bcf86cd799439011', (string) $result->_id);
        $this->assertSame('data', $result->custom_field_name);
        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
    }

    // ========================================
    // CROSS-INSTANCE CACHE CONSISTENCY
    // ========================================

    public function testCacheConsistencyAcrossDifferentClassInstances(): void
    {
        $obj1 = new SimpleModel();
        $obj1->setName('first');
        $r1 = $obj1->bsonSerialize();

        $obj2 = new EnumModel();
        $obj2->setPlatform(TestPlatform::Web);
        $r2 = $obj2->bsonSerialize();

        $obj3 = new SimpleModel();
        $obj3->setName('third');
        $r3 = $obj3->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('propertyInfoCache');
        $cache->setAccessible(true);
        $piInstances = $cache->getValue(null);

        $this->assertNotNull($piInstances);

        $classPropsCache = $ref->getProperty('classPropertiesCache');
        $classPropsCache->setAccessible(true);
        $cpc = $classPropsCache->getValue(null);

        $this->assertArrayHasKey(SimpleModel::class, $cpc);
        $this->assertArrayHasKey(EnumModel::class, $cpc);

        $this->assertSame('first', $r1->name);
        $this->assertSame('web', $r2->platform);
        $this->assertSame('third', $r3->name);
    }

    public function testSubclassSerializationDoesNotPolluteParentCache(): void
    {
        $parent = new SimpleModel();
        $parent->bsonSerialize();

        $child = new ChildModel();
        $child->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $parentProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[SimpleModel::class]);

        $this->assertNotContains('extra', $parentProps, 'Parent cache should not contain child property');
    }

    // ========================================
    // EMC-BACKEND PATTERNS: bsonSerialize
    // ========================================

    public function testEmcMongoBaseConcreteSerializeStructure(): void
    {
        $obj = new EmcMongoBaseConcrete();

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertObjectHasProperty('_id', $result);
        $this->assertObjectHasProperty('created_at', $result);
        $this->assertObjectHasProperty('updated_at', $result);
        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
        $this->assertInstanceOf(UTCDateTime::class, $result->updated_at);
    }

    public function testEmcMongoBaseConcreteSerializeWithObjectId(): void
    {
        $id = new ObjectId();
        $obj = new EmcMongoBaseConcrete();
        $obj->setId($id);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame((string) $id, (string) $result->_id);
    }

    public function testEmcAppointmentUserSerializeWithManyNullables(): void
    {
        $obj = new EmcAppointmentUser();
        $obj->setType(EmcUserType::Patient);
        $obj->setMongoId(new ObjectId());
        $obj->setName('John Doe');
        $obj->setCurrentState(EmcUserState::Registered);

        $result = $obj->bsonSerialize();

        $this->assertSame('patient', $result->type);
        $this->assertInstanceOf(ObjectId::class, $result->mongo_id);
        $this->assertSame('John Doe', $result->name);
        $this->assertSame('registered', $result->current_state);
        $this->assertObjectHasProperty('uuid', $result);
        $this->assertObjectHasProperty('email', $result);
        $this->assertObjectHasProperty('phone', $result);
        $this->assertObjectHasProperty('left_on', $result);
    }

    public function testEmcAppointmentUserSerializeWithNestedSurvey(): void
    {
        $survey = new EmcSurvey();
        $survey->setq1(3);
        $survey->setq2(4);
        $survey->setq3(5);
        $survey->setAnswered(new \DateTime('2024-06-15'));

        $obj = new EmcAppointmentUser();
        $obj->setType(EmcUserType::Professional);
        $obj->setAnsweredSurvey($survey);

        $result = $obj->bsonSerialize();

        $this->assertSame('profesional', $result->type);
        $this->assertInstanceOf(\stdClass::class, $result->answered_survey);
        $this->assertSame(3, $result->answered_survey->q1);
        $this->assertSame(4, $result->answered_survey->q2);
        $this->assertSame(5, $result->answered_survey->q3);
        $this->assertInstanceOf(UTCDateTime::class, $result->answered_survey->answered);
    }

    public function testEmcAppointmentUserSerializeWithStateChangesArray(): void
    {
        $change1 = new EmcUserStateChange();
        $change1->setDate(new \DateTime('2024-01-01 10:00:00'));
        $change1->setUserState(EmcUserState::Registered);

        $change2 = new EmcUserStateChange();
        $change2->setDate(new \DateTime('2024-01-01 10:05:00'));
        $change2->setUserState(EmcUserState::InWaitingRoom);

        $obj = new EmcAppointmentUser();
        $obj->setStateChanges([$change1, $change2]);

        $result = $obj->bsonSerialize();

        $this->assertIsArray($result->state_changes);
        $this->assertCount(2, $result->state_changes);
        $this->assertSame('registered', $result->state_changes[0]->user_state);
        $this->assertInstanceOf(UTCDateTime::class, $result->state_changes[0]->date);
        $this->assertSame('inWaitingRoom', $result->state_changes[1]->user_state);
    }

    public function testEmcAppointmentUserSerializeForPushPattern(): void
    {
        $obj = new EmcAppointmentUser();
        $obj->setType(EmcUserType::Patient);
        $obj->setMongoId(new ObjectId());
        $obj->setName('Jane');
        $obj->setGroupId('group-123');
        $obj->setCurrentState(EmcUserState::Registered);

        $serialized = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $serialized);
        $this->assertSame('patient', $serialized->type);
        $this->assertSame('group-123', $serialized->group_id);
    }

    public function testEmcStadisticSerializeStructure(): void
    {
        $userId = new ObjectId();
        $obj = new EmcLoginStatistic();
        $obj->setId(new ObjectId());
        $obj->setUserId($userId);
        $obj->setType(EmcStatType::Login);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame((string) $userId, (string) $result->user_id);
        $this->assertSame('LOGIN', $result->type);
        $this->assertSame(1, $result->count);
        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
        $this->assertInstanceOf(UTCDateTime::class, $result->updated_at);
    }

    public function testEmcStadisticSerializeAndUnsetCountPattern(): void
    {
        $obj = new EmcLoginStatistic();
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Login);
        $obj->setModule('hdom');

        $normalization = $obj->bsonSerialize();
        $this->assertObjectHasProperty('count', $normalization);
        $this->assertObjectHasProperty('updated_at', $normalization);

        unset($normalization->count);
        unset($normalization->updated_at);

        $this->assertObjectNotHasProperty('count', $normalization);
        $this->assertObjectNotHasProperty('updated_at', $normalization);
        $this->assertObjectHasProperty('user_id', $normalization);
        $this->assertObjectHasProperty('type', $normalization);
        $this->assertObjectHasProperty('module', $normalization);
        $this->assertObjectHasProperty('created_at', $normalization);
    }

    public function testEmcQuestionnaireStatisticSerializeWithExtraDateFields(): void
    {
        $obj = new EmcQuestionnaireStatistic();
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Questionnaires);
        $obj->setPatientId(new ObjectId());
        $obj->setFormId('form-abc');
        $obj->setAnsweredAt(new \DateTime('2024-06-15'));
        $obj->setAvailableAt(new \DateTime('2024-06-14'));
        $obj->setReminderAt(new \DateTime('2024-06-14 12:00:00'));

        $result = $obj->bsonSerialize();

        $this->assertSame('QUESTIONNAIRES', $result->type);
        $this->assertInstanceOf(ObjectId::class, $result->patient_id);
        $this->assertSame('form-abc', $result->form_id);
        $this->assertInstanceOf(UTCDateTime::class, $result->answered_at);
        $this->assertInstanceOf(UTCDateTime::class, $result->available_at);
        $this->assertInstanceOf(UTCDateTime::class, $result->reminder_at);
    }

    public function testEmcQuestionnaireStatisticUnsetConditionalPattern(): void
    {
        $obj = new EmcQuestionnaireStatistic();
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Questionnaires);
        $obj->setAnsweredAt(new \DateTime('2024-06-15'));

        $normalization = $obj->bsonSerialize();
        $this->assertObjectHasProperty('answered_at', $normalization);

        unset($normalization->count);
        unset($normalization->updated_at);

        $this->assertObjectNotHasProperty('count', $normalization);
        $this->assertObjectNotHasProperty('updated_at', $normalization);

        unset($normalization->answered_at);
        $this->assertObjectNotHasProperty('answered_at', $normalization);
    }

    public function testEmcRegistrationRequestSerializeComplexStructure(): void
    {
        $regData = new EmcRegistrationData();
        $regData->setFirstName('John');
        $regData->setLastName('Doe');
        $regData->setBirthdate(new \DateTime('1990-05-20'));
        $regData->setNhc('0000123456');

        $activity = new EmcRegistrationActivity();
        $activity->setCreatedAt(new \DateTime('2024-06-15'));
        $activity->setAction('VERIFY_EMAIL');

        $obj = new EmcRegistrationRequest();
        $obj->setId(new ObjectId());
        $obj->setCreatedAt(new \DateTime('2024-06-15'));
        $obj->setUpdatedAt(new \DateTime('2024-06-15'));
        $obj->setRegistrationData($regData);
        $obj->setDelegationType(EmcDelegationType::MinorSixteen);
        $obj->setStatus(EmcRegStatus::PendingEmail);
        $obj->setMigration(false);
        $obj->setFromSap(false);
        $obj->addActivity($activity);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
        $this->assertObjectHasProperty('presential', $result);
        $this->assertFalse($result->presential);
        $this->assertSame('MINOR_SIXTEEN', $result->delegation_type);
        $this->assertSame('pending_email', $result->status);
        $this->assertFalse($result->delegation);
        $this->assertFalse($result->migration);
        $this->assertFalse($result->from_sap);
    }

    public function testEmcRegistrationRequestNestedRegistrationData(): void
    {
        $regData = new EmcRegistrationData();
        $regData->setFirstName('Maria');
        $regData->setLastName('Garcia');
        $regData->setNhc('0000999999');

        $obj = new EmcRegistrationRequest();
        $obj->setRegistrationData($regData);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result->registration_data);
        $this->assertSame('Maria', $result->registration_data->first_name);
        $this->assertSame('Garcia', $result->registration_data->last_name);
        $this->assertSame('0000999999', $result->registration_data->nhc);
    }

    public function testEmcRegistrationRequestRecursiveUpdateBuilderPattern(): void
    {
        $regData = new EmcRegistrationData();
        $regData->setFirstName('Updated');
        $regData->setNhc('0000111111');

        $obj = new EmcRegistrationRequest();
        $obj->setRegistrationData($regData);
        $obj->setStatus(EmcRegStatus::PendingActivation);

        $normalization = $obj->bsonSerialize();

        $mongoSet = [];
        $basePath = 'registration_requests.$[petition]';
        $updateKeys = ['registration_data', 'status'];
        foreach ($updateKeys as $key) {
            $mongoValue = $normalization->{$key} ?? null;
            if (is_null($mongoValue)) continue;
            if ($mongoValue instanceof \stdClass) {
                foreach ($mongoValue as $subKey => $subMongoValue) {
                    if (!is_null($subMongoValue)) {
                        $mongoSet["$basePath.$key.$subKey"] = $subMongoValue;
                    }
                }
            } else {
                $mongoSet["$basePath.$key"] = $mongoValue;
            }
        }

        $this->assertArrayHasKey("$basePath.registration_data.first_name", $mongoSet);
        $this->assertSame('Updated', $mongoSet["$basePath.registration_data.first_name"]);
        $this->assertArrayHasKey("$basePath.registration_data.nhc", $mongoSet);
        $this->assertArrayHasKey("$basePath.status", $mongoSet);
        $this->assertSame('pending_activation', $mongoSet["$basePath.status"]);
    }

    public function testEmcRegistrationRequestNullValueSkippedPattern(): void
    {
        $obj = new EmcRegistrationRequest();
        $obj->setStatus(EmcRegStatus::Validated);

        $normalization = $obj->bsonSerialize();

        $mongoSet = [];
        foreach (['status' => 'validated', 'delegation_type' => null] as $key => $value) {
            $mongoValue = $normalization->{$key} ?? null;
            if (is_null($mongoValue)) continue;
            $mongoSet[$key] = $mongoValue;
        }

        $this->assertArrayHasKey('status', $mongoSet);
        $this->assertArrayNotHasKey('delegation_type', $mongoSet);
    }

    public function testEmcUserLegalDocumentsNestedObjectPattern(): void
    {
        $privacyItem = new EmcLegalDocumentsItem();
        $privacyItem->setId(new ObjectId());
        $privacyItem->setAcceptedAt(new \DateTime('2024-01-15'));

        $conditionsItem = new EmcLegalDocumentsItem();
        $conditionsItem->setId(new ObjectId());
        $conditionsItem->setAcceptedAt(new \DateTime('2024-02-20'));

        $obj = new EmcUserLegalDocuments();
        $obj->setPrivacyDoc($privacyItem);
        $obj->setConditionsDoc($conditionsItem);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result->privacy_doc);
        $this->assertInstanceOf(ObjectId::class, $result->privacy_doc->id);
        $this->assertInstanceOf(UTCDateTime::class, $result->privacy_doc->accepted_at);
        $this->assertInstanceOf(\stdClass::class, $result->conditions_doc);
        $this->assertInstanceOf(ObjectId::class, $result->conditions_doc->id);
    }

    public function testEmcUserLegalDocumentsBothNull(): void
    {
        $obj = new EmcUserLegalDocuments();

        $result = $obj->bsonSerialize();

        $this->assertNull($result->privacy_doc);
        $this->assertNull($result->conditions_doc);
    }

    public function testEmcStadisticCastToArrayPattern(): void
    {
        $obj = new EmcLoginStatistic();
        $obj->setId(new ObjectId());
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Login);

        $normalization = $obj->bsonSerialize();
        $setOnInsert = (array) $normalization;

        unset($setOnInsert['updated_at']);
        unset($setOnInsert['count']);

        $this->assertArrayNotHasKey('updated_at', $setOnInsert);
        $this->assertArrayNotHasKey('count', $setOnInsert);
        $this->assertArrayHasKey('_id', $setOnInsert);
        $this->assertArrayHasKey('user_id', $setOnInsert);
        $this->assertArrayHasKey('type', $setOnInsert);
        $this->assertArrayHasKey('created_at', $setOnInsert);
    }

    public function testEmcReportDownloadStatisticSerialize(): void
    {
        $obj = new EmcReportDownloadStatistic();
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::ReportDownload);
        $obj->setPatientId(new ObjectId());

        $result = $obj->bsonSerialize();

        $this->assertSame('REPORT_DOWNLOAD', $result->type);
        $this->assertInstanceOf(ObjectId::class, $result->patient_id);
    }

    // ========================================
    // EMC-BACKEND PATTERNS: bsonChanges
    // ========================================

    public function testEmcMongoBaseConcreteBsonChangesNoChange(): void
    {
        $old = new EmcMongoBaseConcrete();
        $old->setId(new ObjectId());
        $old->setCreatedAt(new \DateTime('2024-01-01'));
        $old->setUpdatedAt(new \DateTime('2024-01-01'));

        $new = new EmcMongoBaseConcrete();
        $new->setId($old->getId());
        $new->setCreatedAt(new \DateTime('2024-01-01'));
        $new->setUpdatedAt(new \DateTime('2024-01-01'));

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testEmcMongoBaseConcreteBsonChangesUpdatedAtChanged(): void
    {
        $now = new \DateTime('2024-01-01 12:00:00');
        $later = new \DateTime('2024-01-02 08:00:00');

        $old = new EmcMongoBaseConcrete();
        $old->setId(new ObjectId());
        $old->setCreatedAt($now);
        $old->setUpdatedAt($now);

        $new = new EmcMongoBaseConcrete();
        $new->setId($old->getId());
        $new->setCreatedAt($now);
        $new->setUpdatedAt($later);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('updated_at', $changes['$set']);
    }

    public function testEmcStadisticBsonChangesWithCountIncrement(): void
    {
        $userId = new ObjectId();

        $old = new EmcLoginStatistic();
        $old->setId(new ObjectId());
        $old->setUserId($userId);
        $old->setType(EmcStatType::Login);
        $old->setCount(1);

        $new = new EmcLoginStatistic();
        $new->setId($old->getId());
        $new->setUserId($userId);
        $new->setType(EmcStatType::Login);
        $new->setCount(5);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('count', $changes['$set']);
        $this->assertSame(5, $changes['$set']['count']);
    }

    public function testEmcStadisticBsonChangesWithModuleChange(): void
    {
        $userId = new ObjectId();

        $old = new EmcLoginStatistic();
        $old->setId(new ObjectId());
        $old->setUserId($userId);
        $old->setType(EmcStatType::Login);

        $new = new EmcLoginStatistic();
        $new->setId($old->getId());
        $new->setUserId($userId);
        $new->setType(EmcStatType::Login);
        $new->setModule('hdom');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('module', $changes['$set']);
        $this->assertSame('hdom', $changes['$set']['module']);
    }

    public function testEmcAppointmentUserBsonChangesStateChanged(): void
    {
        $old = new EmcAppointmentUser();
        $old->setCurrentState(EmcUserState::Registered);

        $new = new EmcAppointmentUser();
        $new->setCurrentState(EmcUserState::InMeeting);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('current_state', $changes['$set']);
        $this->assertSame('inMeeting', $changes['$set']['current_state']);
    }

    public function testEmcAppointmentUserBsonChangesFieldAdded(): void
    {
        $old = new EmcAppointmentUser();
        $old->setType(EmcUserType::Patient);

        $new = new EmcAppointmentUser();
        $new->setType(EmcUserType::Patient);
        $new->setJoinedOn(new \DateTime('2024-06-15 10:30:00'));

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('joined_on', $changes['$set']);
    }

    public function testEmcAppointmentUserBsonChangesFieldRemoved(): void
    {
        $old = new EmcAppointmentUser();
        $old->setType(EmcUserType::Patient);
        $old->setGroupId('group-1');

        $new = new EmcAppointmentUser();
        $new->setType(EmcUserType::Patient);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$unset', $changes);
        $this->assertArrayHasKey('group_id', $changes['$unset']);
    }

    public function testEmcRegistrationRequestBsonChangesStatusProgress(): void
    {
        $old = new EmcRegistrationRequest();
        $old->setStatus(EmcRegStatus::PendingEmail);

        $new = new EmcRegistrationRequest();
        $new->setStatus(EmcRegStatus::PendingActivation);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('status', $changes['$set']);
        $this->assertSame('pending_activation', $changes['$set']['status']);
    }

    public function testEmcRegistrationRequestBsonChangesValidatedAtAdded(): void
    {
        $old = new EmcRegistrationRequest();

        $new = new EmcRegistrationRequest();
        $new->setValidatedAt(new \DateTime('2024-06-15'));

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('validated_at', $changes['$set']);
    }

    public function testEmcRegistrationRequestBsonChangesDelegationTypeAdded(): void
    {
        $old = new EmcRegistrationRequest();

        $new = new EmcRegistrationRequest();
        $new->setDelegationType(EmcDelegationType::LegallyIncapacitated);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('delegation_type', $changes['$set']);
        $this->assertSame('LEGALLY_INCAPACITATED', $changes['$set']['delegation_type']);
    }

    public function testEmcLegalDocumentsItemBsonChangesObjectId(): void
    {
        $id1 = new ObjectId();
        $id2 = new ObjectId();

        $old = new EmcLegalDocumentsItem();
        $old->setId($id1);

        $new = new EmcLegalDocumentsItem();
        $new->setId($id2);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('id', $changes['$set']);
    }

    public function testEmcLegalDocumentsItemBsonChangesSameObjectId(): void
    {
        $id = new ObjectId();

        $old = new EmcLegalDocumentsItem();
        $old->setId($id);
        $old->setAcceptedAt(new \DateTime('2024-01-01'));

        $new = new EmcLegalDocumentsItem();
        $new->setId($id);
        $new->setAcceptedAt(new \DateTime('2024-01-01'));

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testEmcSurveyBsonChangesScoreChange(): void
    {
        $old = new EmcSurvey();
        $old->setq1(1);
        $old->setq2(1);
        $old->setq3(1);

        $new = new EmcSurvey();
        $new->setq1(5);
        $new->setq2(4);
        $new->setq3(3);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('q1', $changes['$set']);
        $this->assertSame(5, $changes['$set']['q1']);
        $this->assertArrayHasKey('q2', $changes['$set']);
        $this->assertArrayHasKey('q3', $changes['$set']);
    }

    public function testEmcSurveyBsonChangesNoChange(): void
    {
        $dt = new \DateTime('2024-06-15');

        $old = new EmcSurvey();
        $old->setq1(3);
        $old->setq2(3);
        $old->setq3(3);
        $old->setAnswered($dt);

        $new = new EmcSurvey();
        $new->setq1(3);
        $new->setq2(3);
        $new->setq3(3);
        $new->setAnswered($dt);

        $changes = $old->bsonChanges($new);

        $this->assertEmpty($changes);
    }

    public function testEmcQuestionnaireStatisticBsonChangesFormIdAdded(): void
    {
        $old = new EmcQuestionnaireStatistic();
        $old->setUserId(new ObjectId());
        $old->setType(EmcStatType::Questionnaires);

        $new = new EmcQuestionnaireStatistic();
        $new->setUserId($old->getUserId());
        $new->setType(EmcStatType::Questionnaires);
        $new->setFormId('form-xyz');

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('form_id', $changes['$set']);
        $this->assertSame('form-xyz', $changes['$set']['form_id']);
    }

    public function testEmcUserLegalDocumentsBsonChangesPrivacyDocAccepted(): void
    {
        $old = new EmcUserLegalDocuments();

        $new = new EmcUserLegalDocuments();
        $privacyItem = new EmcLegalDocumentsItem();
        $privacyItem->setId(new ObjectId());
        $privacyItem->setAcceptedAt(new \DateTime('2024-06-15'));
        $new->setPrivacyDoc($privacyItem);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('privacy_doc', $changes['$set']);
    }

    // ========================================
    // EMC-BACKEND PATTERNS: MigrateCommand docsEqual-like equality
    // ========================================

    public function testEmcDocsEqualPatternFilteringNulls(): void
    {
        $obj = new EmcAppointmentUser();
        $obj->setType(EmcUserType::Patient);
        $obj->setName('John');

        $a = $obj->bsonSerialize();

        $aArray = [];
        foreach ($a as $key => $value) {
            if (!is_null($value)) $aArray[$key] = $value;
        }

        $this->assertArrayHasKey('type', $aArray);
        $this->assertArrayHasKey('name', $aArray);
        $this->assertArrayNotHasKey('uuid', $aArray);
        $this->assertArrayNotHasKey('email', $aArray);
        $this->assertArrayNotHasKey('phone', $aArray);
        $this->assertArrayNotHasKey('mongo_id', $aArray);
    }

    public function testEmcDocsEqualPatternObjectIdComparison(): void
    {
        $id = new ObjectId();

        $obj = new EmcMongoBaseConcrete();
        $obj->setId($id);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $retrieved = $result->_id;
        $this->assertTrue($id->__toString() === $retrieved->__toString());
    }

    public function testEmcDocsEqualPatternUTCDateTimeSkipped(): void
    {
        $obj = new EmcMongoBaseConcrete();
        $obj->setCreatedAt(new \DateTime('2024-01-01'));

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
        $this->assertInstanceOf(UTCDateTime::class, $result->updated_at);

        $isUtc = $result->created_at instanceof UTCDateTime;
        $this->assertTrue($isUtc);
    }

    public function testEmcDocsEqualPatternNestedStdClass(): void
    {
        $regData = new EmcRegistrationData();
        $regData->setFirstName('Test');
        $regData->setNhc('0000000001');

        $obj = new EmcRegistrationRequest();
        $obj->setRegistrationData($regData);

        $a = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $a->registration_data);

        $nestedKeys = [];
        foreach ($a->registration_data as $key => $value) {
            if (!is_null($value)) $nestedKeys[] = $key;
        }

        $this->assertContains('first_name', $nestedKeys);
        $this->assertContains('nhc', $nestedKeys);
    }

    public function testEmcStadisticInheritanceSerializeMultipleLevels(): void
    {
        $obj = new EmcQuestionnaireStatistic();
        $obj->setId(new ObjectId());
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Questionnaires);
        $obj->setPatientId(new ObjectId());
        $obj->setFormId('f1');

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame('QUESTIONNAIRES', $result->type);
        $this->assertSame(1, $result->count);
        $this->assertSame('f1', $result->form_id);
        $this->assertInstanceOf(ObjectId::class, $result->patient_id);
        $this->assertInstanceOf(UTCDateTime::class, $result->created_at);
    }

    // ========================================
    // EMC-BACKEND PATTERNS: DiscriminatorMap serialization
    // ========================================

    public function testEmcStadisticDiscriminatorMapSubclassSerialize(): void
    {
        $obj = new EmcLoginStatistic();
        $obj->setId(new ObjectId());
        $obj->setUserId(new ObjectId());
        $obj->setType(EmcStatType::Login);

        $result = $obj->bsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('LOGIN', $result->type);
        $this->assertObjectHasProperty('count', $result);
        $this->assertSame(1, $result->count);
    }

    public function testEmcStadisticDiscriminatorMapDifferentSubclassesSerialize(): void
    {
        $login = new EmcLoginStatistic();
        $login->setUserId(new ObjectId());
        $login->setType(EmcStatType::Login);

        $questionnaire = new EmcQuestionnaireStatistic();
        $questionnaire->setUserId(new ObjectId());
        $questionnaire->setType(EmcStatType::Questionnaires);
        $questionnaire->setFormId('f1');

        $report = new EmcReportDownloadStatistic();
        $report->setUserId(new ObjectId());
        $report->setType(EmcStatType::ReportDownload);
        $report->setPatientId(new ObjectId());

        $loginResult = $login->bsonSerialize();
        $questionnaireResult = $questionnaire->bsonSerialize();
        $reportResult = $report->bsonSerialize();

        $this->assertSame('LOGIN', $loginResult->type);
        $this->assertObjectNotHasProperty('form_id', $loginResult);
        $this->assertObjectNotHasProperty('patient_id', $loginResult);

        $this->assertSame('QUESTIONNAIRES', $questionnaireResult->type);
        $this->assertObjectHasProperty('form_id', $questionnaireResult);
        $this->assertSame('f1', $questionnaireResult->form_id);
        $this->assertObjectHasProperty('patient_id', $questionnaireResult);

        $this->assertSame('REPORT_DOWNLOAD', $reportResult->type);
        $this->assertObjectHasProperty('patient_id', $reportResult);
        $this->assertObjectNotHasProperty('form_id', $reportResult);
    }

    public function testEmcAbstractStadisticClassPropertiesCachedSeparately(): void
    {
        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);

        $login = new EmcLoginStatistic();
        $login->bsonSerialize();

        $questionnaire = new EmcQuestionnaireStatistic();
        $questionnaire->bsonSerialize();

        $cached = $cache->getValue(null);

        $this->assertArrayHasKey(EmcLoginStatistic::class, $cached);
        $this->assertArrayHasKey(EmcQuestionnaireStatistic::class, $cached);
        $this->assertArrayHasKey(EmcStadistic::class, $cached);
        $this->assertArrayHasKey(EmcMongoBase::class, $cached);

        $loginProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[EmcLoginStatistic::class]);
        $questProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[EmcQuestionnaireStatistic::class]);
        $statProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[EmcStadistic::class]);

        $this->assertContains('id', $statProps);
        $this->assertContains('createdAt', $statProps);
        $this->assertContains('userId', $statProps);
        $this->assertContains('type', $statProps);

        $this->assertContains('formId', $questProps);
        $this->assertNotContains('formId', $loginProps);
    }

    public function testEmcAbstractStadisticSubclassDoesNotPolluteParentCache(): void
    {
        $login = new EmcLoginStatistic();
        $login->bsonSerialize();

        $ref = new ReflectionClass(Base::class);
        $cache = $ref->getProperty('classPropertiesCache');
        $cache->setAccessible(true);
        $cached = $cache->getValue(null);

        $statProps = array_map(fn(ReflectionProperty $p) => $p->getName(), $cached[EmcStadistic::class]);

        $this->assertNotContains('formId', $statProps, 'EmcStadistic cache should not contain EmcQuestionnaireStatistic-specific property');
        $this->assertNotContains('patientId', $statProps, 'EmcStadistic cache should not contain EmcQuestionnaireStatistic-specific property');
    }

    public function testEmcDiscriminatorMapBsonChangesBetweenSubclasses(): void
    {
        $old = new EmcLoginStatistic();
        $old->setId(new ObjectId());
        $old->setUserId(new ObjectId());
        $old->setType(EmcStatType::Login);
        $old->setCount(1);

        $new = new EmcLoginStatistic();
        $new->setId($old->getId());
        $new->setUserId($old->getUserId());
        $new->setType(EmcStatType::Login);
        $new->setCount(3);

        $changes = $old->bsonChanges($new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertArrayHasKey('count', $changes['$set']);
        $this->assertSame(3, $changes['$set']['count']);
    }

    private function canUnserialize(): bool
    {
        $ref = new ReflectionClass(Base::class);
        $piProp = $ref->getProperty('propertyInfoCache');
        $piProp->setAccessible(true);
        $pi = $piProp->getValue(null);
        if ($pi === null) {
            $obj = new SimpleModel();
            $obj->bsonSerialize();
            $pi = $piProp->getValue(null);
        }
        return $pi && method_exists($pi, 'getType');
    }

    // ========================================
    // bsonUnserialize: BASIC PATTERNS
    // ========================================

    public function testUnserializeSimpleStringProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new SimpleModel();
        $data = ['name' => 'hello', 'count' => 42, 'active' => true, 'ratio' => 3.14];
        $obj->bsonUnserialize($data);

        $this->assertSame('hello', $obj->getName());
        $this->assertSame(42, $obj->getCount());
        $this->assertTrue($obj->isActive());
        $this->assertSame(3.14, $obj->getRatio());
    }

    public function testUnserializeNullValuesSkippedForNonNullTypes(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new SimpleModel();
        $obj->setName('existing');
        $data = ['name' => 'updated', 'count' => null, 'active' => null, 'ratio' => null];
        $obj->bsonUnserialize($data);

        $this->assertSame('updated', $obj->getName());
    }

    public function testUnserializeCustomNameProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $obj = new CustomNameModel();
        $data = ['_id' => $id, 'custom_field_name' => 'test_value', 'created_at' => new UTCDateTime((new \DateTime('2024-01-01'))->getTimestamp() * 1000)];
        $obj->bsonUnserialize($data);

        $this->assertSame((string) $id, (string) $obj->getId());
        $this->assertSame('test_value', $obj->getField());
        $this->assertInstanceOf(\DateTime::class, $obj->getCreatedAt());
    }

    public function testUnserializeObjectIdProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $refId = new ObjectId();
        $obj = new ObjectIdModel();
        $data = ['_id' => $id, 'ref_id' => $refId];
        $obj->bsonUnserialize($data);

        $this->assertSame((string) $id, (string) $obj->getId());
        $this->assertSame((string) $refId, (string) $obj->getRefId());
    }

    public function testUnserializeDateTimeFromUTCDateTime(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $dt = new \DateTime('2024-06-15 12:30:00');
        $utc = new UTCDateTime($dt->getTimestamp() * 1000);

        $obj = new DateTimeModel();
        $data = ['timestamp' => $utc];
        $obj->bsonUnserialize($data);

        $result = $obj->getTimestamp();
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame($dt->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testUnserializeStringBackedEnum(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new EnumModel();
        $data = ['platform' => 'web', 'status' => 1];
        $obj->bsonUnserialize($data);

        $this->assertSame(TestPlatform::Web, $obj->getPlatform());
        $this->assertSame(TestStatus::Active, $obj->getStatus());
    }

    public function testUnserializeNestedSerializable(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $childData = ['name' => 'nested_child', 'count' => null, 'active' => null, 'ratio' => null];
        $obj = new NestedModel();
        $data = ['child' => (object) $childData];
        $obj->bsonUnserialize($data);

        $this->assertNotNull($obj->getChild());
        $this->assertSame('nested_child', $obj->getChild()->getName());
    }

    public function testUnserializeArrayProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $items = [
            (object) ['name' => 'item1', 'count' => null, 'active' => null, 'ratio' => null],
            (object) ['name' => 'item2', 'count' => null, 'active' => null, 'ratio' => null],
        ];
        $obj = new ArrayModel();
        $data = ['items' => $items, 'tags' => ['a', 'b', 'c']];
        $obj->bsonUnserialize($data);

        $this->assertCount(2, $obj->getItems());
        $this->assertSame('item1', $obj->getItems()[0]->getName());
        $this->assertSame('item2', $obj->getItems()[1]->getName());
        $this->assertSame(['a', 'b', 'c'], $obj->getTags());
    }

    public function testUnserializeEmptyArrayProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new ArrayModel();
        $data = ['items' => [], 'tags' => []];
        $obj->bsonUnserialize($data);

        $this->assertCount(0, $obj->getItems());
        $this->assertCount(0, $obj->getTags());
    }

    public function testUnserializeInheritedProperties(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new ChildModel();
        $data = ['name' => 'parent_val', 'count' => null, 'active' => null, 'ratio' => null, 'extra' => 'child_val'];
        $obj->bsonUnserialize($data);

        $this->assertSame('parent_val', $obj->getName());
        $this->assertSame('child_val', $obj->getExtra());
    }

    public function testUnserializeDiscriminatorMapConcreteClass(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new AbstractAnimal();
        $data = ['type' => 'cat', 'name' => 'whiskers', 'lives' => 9];
        $obj->bsonUnserialize($data);

        $this->assertInstanceOf(CatModel::class, $obj);
        $this->assertSame('whiskers', $obj->getName());
        $this->assertSame(9, $obj->getLives());
    }

    public function testUnserializeNullableStringProperty(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new NullableModel();
        $data = ['name' => 'set', 'required' => 'custom'];
        $obj->bsonUnserialize($data);

        $this->assertSame('set', $obj->getName());
        $this->assertSame('custom', $obj->getRequired());
    }

    public function testUnserializeBoolWithDefault(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new EmcRegistrationRequest();
        $data = [
            'delegation' => true,
            'activation' => true,
            'patient_activation' => true,
            'migration' => true,
            'from_sap' => true,
            'presential' => true,
        ];
        $obj->bsonUnserialize($data);

        $this->assertTrue($obj->isDelegation());
        $this->assertTrue($obj->isActivation());
        $this->assertTrue($obj->isPatientActivation());
        $this->assertTrue($obj->isMigration());
        $this->assertTrue($obj->isFromSap());
        $this->assertTrue($obj->getPresential());
    }

    // ========================================
    // bsonUnserialize: EMC-BACKEND PATTERNS
    // ========================================

    public function testEmcUnserializeMongoBaseFromFindOne(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $now = new UTCDateTime((new \DateTime())->getTimestamp() * 1000);

        $obj = new EmcMongoBaseConcrete();
        $data = ['_id' => $id, 'created_at' => $now, 'updated_at' => $now];
        $obj->bsonUnserialize($data);

        $this->assertSame((string) $id, (string) $obj->getId());
        $this->assertInstanceOf(\DateTime::class, $obj->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $obj->getUpdatedAt());
    }

    public function testEmcUnserializeAppointmentUserFromPush(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $mongoId = new ObjectId();
        $joinedOn = new UTCDateTime((new \DateTime('2024-06-15 10:30:00'))->getTimestamp() * 1000);

        $obj = new EmcAppointmentUser();
        $data = [
            'type' => 'patient',
            'mongo_id' => $mongoId,
            'name' => 'Jane',
            'current_state' => 'inMeeting',
            'joined_on' => $joinedOn,
            'state_changes' => [
                ['date' => new UTCDateTime((new \DateTime('2024-06-15 10:00:00'))->getTimestamp() * 1000), 'user_state' => 'registered'],
                ['date' => new UTCDateTime((new \DateTime('2024-06-15 10:30:00'))->getTimestamp() * 1000), 'user_state' => 'inMeeting'],
            ],
        ];
        $obj->bsonUnserialize($data);

        $this->assertSame(EmcUserType::Patient, $obj->getType());
        $this->assertSame((string) $mongoId, (string) $obj->getMongoId());
        $this->assertSame('Jane', $obj->getName());
        $this->assertSame(EmcUserState::InMeeting, $obj->getCurrentState());
        $this->assertInstanceOf(\DateTime::class, $obj->getJoinedOn());
        $this->assertCount(2, $obj->getStateChanges());
        $this->assertSame(EmcUserState::Registered, $obj->getStateChanges()[0]->getUserState());
        $this->assertSame(EmcUserState::InMeeting, $obj->getStateChanges()[1]->getUserState());
    }

    public function testEmcUnserializeAppointmentUserWithSurvey(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new EmcAppointmentUser();
        $data = [
            'type' => 'profesional',
            'answered_survey' => (object) [
                'q1' => 3,
                'q2' => 4,
                'q3' => 5,
                'answered' => new UTCDateTime((new \DateTime('2024-06-15'))->getTimestamp() * 1000),
            ],
        ];
        $obj->bsonUnserialize($data);

        $this->assertSame(EmcUserType::Professional, $obj->getType());
        $this->assertNotNull($obj->getAnsweredSurvey());
        $this->assertSame(3, $obj->getAnsweredSurvey()->getq1());
        $this->assertSame(4, $obj->getAnsweredSurvey()->getq2());
        $this->assertSame(5, $obj->getAnsweredSurvey()->getq3());
        $this->assertInstanceOf(\DateTime::class, $obj->getAnsweredSurvey()->getAnswered());
    }

    public function testEmcUnserializeStadisticFromAggregate(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $userId = new ObjectId();
        $now = new UTCDateTime((new \DateTime())->getTimestamp() * 1000);

        $obj = new EmcLoginStatistic();
        $data = [
            '_id' => $id,
            'user_id' => $userId,
            'type' => 'LOGIN',
            'count' => 5,
            'module' => 'hdom',
            'sub_module' => 'chat',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $obj->bsonUnserialize($data);

        $this->assertSame((string) $id, (string) $obj->getId());
        $this->assertSame((string) $userId, (string) $obj->getUserId());
        $this->assertSame(EmcStatType::Login, $obj->getType());
        $this->assertSame(5, $obj->getCount());
        $this->assertSame('hdom', $obj->getModule());
    }

    public function testEmcUnserializeQuestionnaireStatisticWithDates(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $patientId = new ObjectId();
        $answeredAt = new UTCDateTime((new \DateTime('2024-06-15'))->getTimestamp() * 1000);
        $availableAt = new UTCDateTime((new \DateTime('2024-06-14'))->getTimestamp() * 1000);

        $obj = new EmcQuestionnaireStatistic();
        $data = [
            'user_id' => new ObjectId(),
            'type' => 'QUESTIONNAIRES',
            'patient_id' => $patientId,
            'form_id' => 'f1',
            'answered_at' => $answeredAt,
            'available_at' => $availableAt,
        ];
        $obj->bsonUnserialize($data);

        $this->assertSame(EmcStatType::Questionnaires, $obj->getType());
        $this->assertSame((string) $patientId, (string) $obj->getPatientId());
        $this->assertSame('f1', $obj->getFormId());
        $this->assertInstanceOf(\DateTime::class, $obj->getAnsweredAt());
        $this->assertInstanceOf(\DateTime::class, $obj->getAvailableAt());
    }

    public function testEmcUnserializeRegistrationRequestWithNestedData(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $createdAt = new UTCDateTime((new \DateTime('2024-06-15'))->getTimestamp() * 1000);

        $obj = new EmcRegistrationRequest();
        $data = [
            '_id' => $id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'presential' => true,
            'registration_data' => (object) [
                'first_name' => 'Maria',
                'last_name' => 'Garcia',
                'nhc' => '0000999999',
            ],
            'delegation_type' => 'MINOR_SIXTEEN',
            'delegation' => true,
            'status' => 'pending_email',
            'activity' => [
                ['created_at' => $createdAt, 'action' => 'VERIFY_EMAIL'],
            ],
        ];
        $obj->bsonUnserialize($data);

        $this->assertSame((string) $id, (string) $obj->getId());
        $this->assertTrue($obj->getPresential());
        $this->assertNotNull($obj->getRegistrationData());
        $this->assertSame('Maria', $obj->getRegistrationData()->getFirstName());
        $this->assertSame('Garcia', $obj->getRegistrationData()->getLastName());
        $this->assertSame('0000999999', $obj->getRegistrationData()->getNhc());
        $this->assertSame(EmcDelegationType::MinorSixteen, $obj->getDelegationType());
        $this->assertTrue($obj->isDelegation());
        $this->assertSame(EmcRegStatus::PendingEmail, $obj->getStatus());
        $this->assertCount(1, $obj->getActivity());
    }

    public function testEmcUnserializeUserLegalDocuments(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $privacyId = new ObjectId();
        $conditionsId = new ObjectId();
        $acceptedAt = new UTCDateTime((new \DateTime('2024-01-15'))->getTimestamp() * 1000);

        $obj = new EmcUserLegalDocuments();
        $data = [
            'privacy_doc' => (object) ['id' => $privacyId, 'accepted_at' => $acceptedAt],
            'conditions_doc' => (object) ['id' => $conditionsId, 'accepted_at' => $acceptedAt],
        ];
        $obj->bsonUnserialize($data);

        $this->assertNotNull($obj->getPrivacyDoc());
        $this->assertSame((string) $privacyId, (string) $obj->getPrivacyDoc()->getId());
        $this->assertInstanceOf(\DateTime::class, $obj->getPrivacyDoc()->getAcceptedAt());
        $this->assertNotNull($obj->getConditionsDoc());
        $this->assertSame((string) $conditionsId, (string) $obj->getConditionsDoc()->getId());
    }

    // ========================================
    // bsonUnserialize: ROUNDTRIP (serialize then unserialize)
    // ========================================

    public function testRoundtripSimpleModel(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $original = new SimpleModel();
        $original->setName('hello');
        $original->setCount(42);
        $original->setActive(true);
        $original->setRatio(3.14);

        $serialized = $original->bsonSerialize();
        $restored = new SimpleModel();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame($original->getName(), $restored->getName());
        $this->assertSame($original->getCount(), $restored->getCount());
        $this->assertSame($original->isActive(), $restored->isActive());
        $this->assertSame($original->getRatio(), $restored->getRatio());
    }

    public function testRoundtripEnumModel(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $original = new EnumModel();
        $original->setPlatform(TestPlatform::Web);
        $original->setStatus(TestStatus::Active);

        $serialized = $original->bsonSerialize();
        $restored = new EnumModel();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame($original->getPlatform(), $restored->getPlatform());
        $this->assertSame($original->getStatus(), $restored->getStatus());
    }

    public function testRoundtripDateTimeModel(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $dt = new \DateTime('2024-06-15 12:30:00');
        $original = new DateTimeModel();
        $original->setTimestamp($dt);

        $serialized = $original->bsonSerialize();
        $restored = new DateTimeModel();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertInstanceOf(\DateTime::class, $restored->getTimestamp());
        $this->assertSame($dt->format('Y-m-d H:i'), $restored->getTimestamp()->format('Y-m-d H:i'));
    }

    public function testRoundtripObjectIdModel(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $refId = new ObjectId();
        $original = new ObjectIdModel();
        $original->setId($id);
        $original->setRefId($refId);

        $serialized = $original->bsonSerialize();
        $restored = new ObjectIdModel();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $id, (string) $restored->getId());
        $this->assertSame((string) $refId, (string) $restored->getRefId());
    }

    public function testRoundtripChildModel(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $original = new ChildModel();
        $original->setName('parent_val');
        $original->setExtra('child_val');
        $original->setCount(10);

        $serialized = $original->bsonSerialize();
        $restored = new ChildModel();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame('parent_val', $restored->getName());
        $this->assertSame('child_val', $restored->getExtra());
        $this->assertSame(10, $restored->getCount());
    }

    public function testRoundtripEmcMongoBase(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $original = new EmcMongoBaseConcrete();
        $original->setId($id);
        $original->setCreatedAt(new \DateTime('2024-01-01'));
        $original->setUpdatedAt(new \DateTime('2024-06-15'));

        $serialized = $original->bsonSerialize();
        $restored = new EmcMongoBaseConcrete();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $id, (string) $restored->getId());
        $this->assertInstanceOf(\DateTime::class, $restored->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $restored->getUpdatedAt());
    }

    public function testRoundtripEmcStadistic(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $id = new ObjectId();
        $userId = new ObjectId();
        $original = new EmcLoginStatistic();
        $original->setId($id);
        $original->setUserId($userId);
        $original->setType(EmcStatType::Login);
        $original->setModule('hdom');

        $serialized = $original->bsonSerialize();
        $restored = new EmcLoginStatistic();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $id, (string) $restored->getId());
        $this->assertSame((string) $userId, (string) $restored->getUserId());
        $this->assertSame(EmcStatType::Login, $restored->getType());
        $this->assertSame('hdom', $restored->getModule());
    }

    public function testRoundtripEmcAppointmentUser(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $mongoId = new ObjectId();
        $original = new EmcAppointmentUser();
        $original->setType(EmcUserType::Patient);
        $original->setMongoId($mongoId);
        $original->setName('John');
        $original->setCurrentState(EmcUserState::Registered);

        $serialized = $original->bsonSerialize();
        $restored = new EmcAppointmentUser();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame(EmcUserType::Patient, $restored->getType());
        $this->assertSame((string) $mongoId, (string) $restored->getMongoId());
        $this->assertSame('John', $restored->getName());
        $this->assertSame(EmcUserState::Registered, $restored->getCurrentState());
    }

    public function testRoundtripEmcAppointmentUserWithSurveyAndStateChanges(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $survey = new EmcSurvey();
        $survey->setq1(3);
        $survey->setq2(4);
        $survey->setq3(5);

        $change = new EmcUserStateChange();
        $change->setDate(new \DateTime('2024-06-15 10:00:00'));
        $change->setUserState(EmcUserState::Registered);

        $original = new EmcAppointmentUser();
        $original->setType(EmcUserType::Professional);
        $original->setAnsweredSurvey($survey);
        $original->setStateChanges([$change]);

        $serialized = $original->bsonSerialize();
        $restored = new EmcAppointmentUser();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame(EmcUserType::Professional, $restored->getType());
        $this->assertNotNull($restored->getAnsweredSurvey());
        $this->assertSame(3, $restored->getAnsweredSurvey()->getq1());
        $this->assertSame(4, $restored->getAnsweredSurvey()->getq2());
        $this->assertSame(5, $restored->getAnsweredSurvey()->getq3());
        $this->assertCount(1, $restored->getStateChanges());
        $this->assertSame(EmcUserState::Registered, $restored->getStateChanges()[0]->getUserState());
    }

    public function testRoundtripEmcRegistrationRequestWithNestedData(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $regData = new EmcRegistrationData();
        $regData->setFirstName('Maria');
        $regData->setLastName('Garcia');
        $regData->setNhc('0000999999');

        $id = new ObjectId();
        $original = new EmcRegistrationRequest();
        $original->setId($id);
        $original->setRegistrationData($regData);
        $original->setDelegationType(EmcDelegationType::MinorSixteen);
        $original->setStatus(EmcRegStatus::PendingEmail);

        $serialized = $original->bsonSerialize();
        $restored = new EmcRegistrationRequest();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $id, (string) $restored->getId());
        $this->assertNotNull($restored->getRegistrationData());
        $this->assertSame('Maria', $restored->getRegistrationData()->getFirstName());
        $this->assertSame('Garcia', $restored->getRegistrationData()->getLastName());
        $this->assertSame(EmcDelegationType::MinorSixteen, $restored->getDelegationType());
        $this->assertSame(EmcRegStatus::PendingEmail, $restored->getStatus());
    }

    // ========================================
    // bsonUnserialize: MIPA ALERTES PATTERNS
    // ========================================

    public function testUnserializeMipaAlertaUserFromArray(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $userId = new ObjectId();
        $obj = new MipaAlertaUser();
        $obj->bsonUnserialize([
            'id' => $userId,
            'uuid' => 'user-uuid-123',
            'subscription' => true,
            'subscriptionAdmin' => false,
            'seen' => new UTCDateTime((new \DateTime('2024-03-15 10:00:00'))->getTimestamp() * 1000),
            'devices' => [],
        ]);

        $this->assertSame((string) $userId, (string) $obj->getId());
        $this->assertSame('user-uuid-123', $obj->getUuid());
        $this->assertTrue($obj->isSubscription());
        $this->assertFalse($obj->isSubscriptionAdmin());
        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getSeen());
        $this->assertSame('2024-03-15', $obj->getSeen()->format('Y-m-d'));
    }

    public function testUnserializeMipaAlertaDeviceWithEnumResult(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $deviceId = new ObjectId();
        $uId = new ObjectId();
        $obj = new MipaAlertaDevice();
        $obj->bsonUnserialize([
            'id' => $deviceId,
            'userId' => $uId,
            'fcmToken' => 'token-abc',
            'result' => 'SUCCESS',
            'seen' => new UTCDateTime((new \DateTime('2024-03-15 12:00:00'))->getTimestamp() * 1000),
        ]);

        $this->assertSame((string) $deviceId, (string) $obj->getId());
        $this->assertSame((string) $uId, (string) $obj->getUserId());
        $this->assertSame('token-abc', $obj->getFcmToken());
        $this->assertSame(MipaNotificationStatus::Success, $obj->getResult());
        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getSeen());
    }

    public function testUnserializeMipaHistoricRowWithAllFields(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $assignedUserId = new ObjectId();
        $obj = new MipaHistoricRow();
        $obj->bsonUnserialize([
            'at' => new UTCDateTime((new \DateTime('2024-01-01 08:00:00'))->getTimestamp() * 1000),
            'alerta' => 'ALERT_TYPE_A',
            'alertaDes' => 'Description of alert',
            'aillat' => true,
            'aillatParams' => ['param1', 'param2'],
            'corregit' => false,
            'groupSol' => 'SOL_GROUP_1',
            'groupSeg' => 'SEG_GROUP_1',
            'users' => [],
            'devices' => [],
            'assignedUser' => [
                'userId' => $assignedUserId,
                'at' => new UTCDateTime((new \DateTime('2024-01-01 09:00:00'))->getTimestamp() * 1000),
            ],
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getAt());
        $this->assertSame('ALERT_TYPE_A', $obj->getAlerta());
        $this->assertSame('Description of alert', $obj->getAlertaDes());
        $this->assertTrue($obj->isAillat());
        $this->assertSame(['param1', 'param2'], $obj->getAillatParams());
        $this->assertFalse($obj->isCorregit());
        $this->assertSame('SOL_GROUP_1', $obj->getGroupSol());
        $this->assertSame('SEG_GROUP_1', $obj->getGroupSeg());
        $this->assertSame([], $obj->getUsers());
        $this->assertSame([], $obj->getDevices());
        $this->assertNotNull($obj->getAssignedUser());
        $this->assertSame((string) $assignedUserId, (string) $obj->getAssignedUser()->getUserId());
    }

    public function testUnserializeMipaAlertesZeroAlertaFromStdClassViaDiscriminatorMap(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $userId = new ObjectId();
        $data = (object) [
            '_id' => new ObjectId(),
            'user' => $userId,
            'origin' => 'projecte0',
            'alertType' => 'ALERT_X',
            'subscription' => 'sub-123',
            'users' => [],
            'devices' => [],
            'deleted' => false,
            'historicArray' => [],
            'aillat' => true,
            'corregit' => false,
            'alertaDes' => 'Zero alert desc',
            'groupSeg' => null,
            'groupSol' => 'G1',
            'assignedUser' => null,
        ];

        $obj = new MipaAlertesZeroAlerta();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame((string) $userId, (string) $obj->getUser());
        $this->assertSame('projecte0', $obj->getOrigin());
        $this->assertSame('ALERT_X', $obj->getAlertType());
        $this->assertTrue($obj->isAillat());
        $this->assertFalse($obj->isCorregit());
        $this->assertSame('Zero alert desc', $obj->getAlertaDes());
        $this->assertSame('G1', $obj->getGroupSol());
    }

    public function testUnserializeMipaHdomMessageAlertaFromStdClass(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $data = (object) [
            '_id' => new ObjectId(),
            'user' => new ObjectId(),
            'origin' => 'hdom_message',
            'alertType' => 'MSG_NEW',
            'subscription' => null,
            'users' => [],
            'devices' => [],
            'deleted' => false,
            'nhc' => '0000123456',
            'message' => 'Hello from HDOM',
            'name' => 'Dr. Smith',
        ];

        $obj = new MipaHdomMessageAlerta();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame('hdom_message', $obj->getOrigin());
        $this->assertSame('0000123456', $obj->getNhc());
        $this->assertSame('Hello from HDOM', $obj->getMessage());
        $this->assertSame('Dr. Smith', $obj->getName());
    }

    public function testRoundtripMipaAlertaUserWithNestedDevice(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $userId = new ObjectId();
        $deviceId = new ObjectId();

        $device = new MipaAlertaDevice();
        $device->setId($deviceId);
        $device->setUserId($userId);
        $device->setFcmToken('fcm-token-xyz');
        $device->setResult(MipaNotificationStatus::Pending);

        $original = new MipaAlertaUser();
        $original->setId(new ObjectId());
        $original->setUuid('uuid-456');
        $original->setSubscription(true);
        $original->setSubscriptionAdmin(true);
        $original->setDevices([$device]);

        $serialized = $original->bsonSerialize();
        $restored = new MipaAlertaUser();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame('uuid-456', $restored->getUuid());
        $this->assertTrue($restored->isSubscription());
        $this->assertTrue($restored->isSubscriptionAdmin());
        $this->assertCount(1, $restored->getDevices());
        $this->assertSame('fcm-token-xyz', $restored->getDevices()[0]->getFcmToken());
        $this->assertSame(MipaNotificationStatus::Pending, $restored->getDevices()[0]->getResult());
    }

    public function testRoundtripMipaHistoricRowWithAssignedUser(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $assignedUserId = new ObjectId();
        $assignedUser = new MipaAssignedUser();
        $assignedUser->setUserId($assignedUserId);
        $assignedUser->setAt(new \DateTime('2024-05-10 14:30:00'));

        $original = new MipaHistoricRow();
        $original->setAt(new \DateTime('2024-05-10 14:00:00'));
        $original->setAlerta('HYPO_ALERT');
        $original->setAlertaDes('Hypoglycemia detected');
        $original->setAillat(true);
        $original->setAillatParams(['value' => '45']);
        $original->setCorregit(false);
        $original->setGroupSol('SOL_A');
        $original->setGroupSeg('SEG_B');
        $original->setAssignedUser($assignedUser);

        $serialized = $original->bsonSerialize();
        $restored = new MipaHistoricRow();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame('HYPO_ALERT', $restored->getAlerta());
        $this->assertSame('Hypoglycemia detected', $restored->getAlertaDes());
        $this->assertTrue($restored->isAillat());
        $this->assertSame(['value' => '45'], $restored->getAillatParams());
        $this->assertNotNull($restored->getAssignedUser());
        $this->assertSame((string) $assignedUserId, (string) $restored->getAssignedUser()->getUserId());
    }

    public function testRoundtripMipaAlertesZeroAlertaFullStructure(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $userId = new ObjectId();
        $alertaUserId = new ObjectId();
        $deviceId = new ObjectId();

        $alertaUser = new MipaAlertaUser();
        $alertaUser->setId($alertaUserId);
        $alertaUser->setUuid('user-uuid-789');
        $alertaUser->setSubscription(true);

        $alertaDevice = new MipaAlertaDevice();
        $alertaDevice->setId($deviceId);
        $alertaDevice->setUserId($alertaUserId);
        $alertaDevice->setFcmToken('token-789');
        $alertaDevice->setResult(MipaNotificationStatus::Success);

        $assignedUser = new MipaAssignedUser();
        $assignedUser->setUserId(new ObjectId());
        $assignedUser->setAt(new \DateTime('2024-06-01 09:00:00'));

        $historicRow = new MipaHistoricRow();
        $historicRow->setAt(new \DateTime('2024-06-01 09:00:00'));
        $historicRow->setAlerta('ALERT_ZERO');
        $historicRow->setAlertaDes('Alert from zero project');
        $historicRow->setAillat(false);
        $historicRow->setCorregit(true);
        $historicRow->setUsers([$alertaUser]);
        $historicRow->setDevices([$alertaDevice]);
        $historicRow->setAssignedUser($assignedUser);

        $original = new MipaAlertesZeroAlerta();
        $original->setUser($userId);
        $original->setOrigin(MipaAlertaOrigin::AlertesZero->value);
        $original->setAlertType('ZERO_ALERT');
        $original->setSubscription('sub-001');
        $original->setUsers([$alertaUser]);
        $original->setDevices([$alertaDevice]);
        $original->setHistoricArray([$historicRow]);
        $original->setAillat(false);
        $original->setCorregit(false);
        $original->setAlertaDes('Main alert desc');
        $original->setGroupSol('GS1');

        $serialized = $original->bsonSerialize();
        $restored = new MipaAlertesZeroAlerta();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $userId, (string) $restored->getUser());
        $this->assertSame('projecte0', $restored->getOrigin());
        $this->assertSame('ZERO_ALERT', $restored->getAlertType());
        $this->assertSame('sub-001', $restored->getSubscription());
        $this->assertCount(1, $restored->getUsers());
        $this->assertSame('user-uuid-789', $restored->getUsers()[0]->getUuid());
        $this->assertCount(1, $restored->getDevices());
        $this->assertSame(MipaNotificationStatus::Success, $restored->getDevices()[0]->getResult());
        $this->assertCount(1, $restored->getHistoricArray());
        $this->assertSame('ALERT_ZERO', $restored->getHistoricArray()[0]->getAlerta());
        $this->assertSame('Main alert desc', $restored->getAlertaDes());
        $this->assertSame('GS1', $restored->getGroupSol());
    }

    // ========================================
    // bsonUnserialize: EMC REHYDRATE PATTERNS
    // ========================================

    public function testUnserializeEmcVerifyEmailRequestFromArray(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $uid = new ObjectId();
        $obj = new EmcVerifyEmailRequest();
        $obj->bsonUnserialize([
            '_id' => new ObjectId(),
            'userId' => $uid,
            'password' => 'hashed-password-123',
            'expirationDate' => new UTCDateTime((new \DateTime('2024-12-31 23:59:59'))->getTimestamp() * 1000),
            'verifyDate' => null,
            'used' => false,
            'email' => 'test@example.com',
        ]);

        $this->assertSame((string) $uid, (string) $obj->getUserId());
        $this->assertSame('hashed-password-123', $obj->getPassword());
        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getExpirationDate());
        $this->assertNull($obj->getVerifyDate());
        $this->assertFalse($obj->isUsed());
        $this->assertSame('test@example.com', $obj->getEmail());
    }

    public function testUnserializeEmcResetPasswordRequestFromArray(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $uid = new ObjectId();
        $obj = new EmcResetPasswordRequest();
        $obj->bsonUnserialize([
            '_id' => new ObjectId(),
            'userId' => $uid,
            'password' => 'reset-hash',
            'expirationDate' => new UTCDateTime((new \DateTime('2025-01-15 00:00:00'))->getTimestamp() * 1000),
            'resetDate' => new UTCDateTime((new \DateTime('2025-01-10 12:30:00'))->getTimestamp() * 1000),
            'used' => true,
            'newEmail' => 'new@example.com',
        ]);

        $this->assertSame((string) $uid, (string) $obj->getUserId());
        $this->assertTrue($obj->isUsed());
        $this->assertSame('new@example.com', $obj->getNewEmail());
        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getResetDate());
    }

    public function testRoundtripEmcSessionWithNestedFcIntervals(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $fc = new EmcFcIntervals(60, 80, 100, 120);

        $original = new EmcSession();
        $original->setUserId(new ObjectId());
        $original->setNhc('0000999');
        $original->setAppointmentId(new ObjectId());
        $original->setMax(150.5);
        $original->setMin(45.2);
        $original->setAvg(95.0);
        $original->setActive(true);
        $original->setDeviceConnected(true);
        $original->setFcIntervals($fc);

        $serialized = $original->bsonSerialize();
        $restored = new EmcSession();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame('0000999', $restored->getNhc());
        $this->assertSame(150.5, $restored->getMax());
        $this->assertSame(45.2, $restored->getMin());
        $this->assertTrue($restored->isActive());
        $this->assertNotNull($restored->getFcIntervals());
        $this->assertSame(60, $restored->getFcIntervals()->getZone1());
        $this->assertSame(80, $restored->getFcIntervals()->getZone2());
        $this->assertSame(100, $restored->getFcIntervals()->getZone3());
        $this->assertSame(120, $restored->getFcIntervals()->getZone4());
    }

    public function testRoundtripEmcSessionWithReports(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $report = new EmcSessionReport();
        $report->setMax(140.0);
        $report->setMin(60.0);
        $report->setCreatedAt(new \DateTime('2024-07-01 10:30:00'));

        $original = new EmcSession();
        $original->setUserId(new ObjectId());
        $original->setReports([$report]);

        $serialized = $original->bsonSerialize();
        $restored = new EmcSession();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertCount(1, $restored->getReports());
        $this->assertSame(140.0, $restored->getReports()[0]->getMax());
        $this->assertSame(60.0, $restored->getReports()[0]->getMin());
    }

    // ========================================
    // bsonUnserialize: ALL 5 DATA PATHS
    // ========================================

    public function testUnserializePathA_BsonDocumentSimulated(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $oid = new ObjectId();
        $utc = new UTCDateTime((new \DateTime('2024-01-01'))->getTimestamp() * 1000);

        // Path A: reHydrate+bson=true → BSONDocument
        // In production, MongoDB driver creates BSONDocument; we simulate with stdClass (cast to array)
        // because old BSONDocument class can't be instantiated in this PHP 8.5 env
        $data = (object) [
            '_id' => $oid,
            'name' => 'path-a-test',
            'count' => 10,
            'active' => true,
            'ratio' => 1.5,
            'date' => $utc,
            'nullableString' => null,
        ];

        $obj = new SimpleModel();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame('path-a-test', $obj->getName());
        $this->assertSame(10, $obj->getCount());
        $this->assertTrue($obj->isActive());
        $this->assertSame(1.5, $obj->getRatio());
    }

    public function testUnserializePathB_StdClassViaTypeMap(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $oid = new ObjectId();
        $utc = new UTCDateTime((new \DateTime('2024-02-15'))->getTimestamp() * 1000);

        // Path B: typeMap root='object' + bsonNormalizeGeneric → stdClass
        // MIPA AbstractRepository uses this pattern with DiscriminatorMap
        $data = (object) [
            '_id' => $oid,
            'user' => new ObjectId(),
            'origin' => 'projecte0',
            'alertType' => 'TEST',
            'subscription' => null,
            'users' => [],
            'devices' => [],
            'deleted' => false,
            'historicArray' => [],
            'aillat' => false,
            'corregit' => false,
            'alertaDes' => null,
            'groupSeg' => null,
            'groupSol' => null,
            'assignedUser' => null,
        ];

        $obj = new MipaAlertesZeroAlerta();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame('projecte0', $obj->getOrigin());
        $this->assertSame('TEST', $obj->getAlertType());
        $this->assertFalse($obj->isAillat());
        $this->assertFalse($obj->isDeleted());
    }

    public function testUnserializePathC_TypeMapAutoWithConcreteClass(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        // Path C: typeMap root=className → driver auto-calls bsonUnserialize with array
        // This is what the driver passes directly — a plain PHP array
        $data = [
            '_id' => new ObjectId(),
            'name' => 'path-c-auto',
            'count' => 99,
            'active' => true,
            'ratio' => 2.71,
            'date' => new UTCDateTime((new \DateTime('2024-03-20'))->getTimestamp() * 1000),
            'nullableString' => 'not-null',
        ];

        $obj = new SimpleModel();
        $obj->bsonUnserialize($data);

        $this->assertSame('path-c-auto', $obj->getName());
        $this->assertSame(99, $obj->getCount());
        $this->assertSame('not-null', $obj->getNullableString());
    }

    public function testUnserializePathD_RawAggregateCursor(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        // Path D: aggregate+raw → BSONDocument-like, but we simulate as stdClass
        // EMC AbstractRepository aggregate pattern: bsonUnserialize on raw result
        $oid = new ObjectId();
        $utc = new UTCDateTime((new \DateTime('2024-04-10'))->getTimestamp() * 1000);

        $data = (object) [
            '_id' => $oid,
            'userId' => new ObjectId(),
            'password' => 'x',
            'expirationDate' => $utc,
            'verifyDate' => null,
            'used' => false,
            'email' => 'aggregate@example.com',
        ];

        $obj = new EmcVerifyEmailRequest();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame('aggregate@example.com', $obj->getEmail());
        $this->assertFalse($obj->isUsed());
    }

    public function testUnserializePathE_AggregateNormalizedPHPArray(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        // Path E: aggregate+normalize → PHP array
        // After bsonNormalizeGeneric, all data is a plain PHP array
        $uid = new ObjectId();
        $data = [
            '_id' => new ObjectId(),
            'user' => $uid,
            'origin' => 'hdom_message',
            'alertType' => 'MSG_READ',
            'subscription' => null,
            'users' => [],
            'devices' => [],
            'deleted' => false,
            'nhc' => '0000987654',
            'message' => 'Message from aggregate',
            'name' => 'Dr. Aggregate',
        ];

        $obj = new MipaHdomMessageAlerta();
        $obj->bsonUnserialize($data);

        $this->assertSame('hdom_message', $obj->getOrigin());
        $this->assertSame('0000987654', $obj->getNhc());
        $this->assertSame('Message from aggregate', $obj->getMessage());
        $this->assertSame('Dr. Aggregate', $obj->getName());
    }

    public function testUnserializePathB_MipaAlertaWithNestedObjectsAsStdClass(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        // Simulates real MIPA path: bsonNormalizeGeneric turns nested docs to stdClass
        $assignedUserId = new ObjectId();
        $data = (object) [
            '_id' => new ObjectId(),
            'user' => new ObjectId(),
            'origin' => 'projecte0',
            'alertType' => 'CRITICAL',
            'subscription' => 'sub-999',
            'users' => [
                (object) ['id' => new ObjectId(), 'uuid' => 'u1', 'subscription' => true, 'subscriptionAdmin' => false, 'seen' => null, 'devices' => []],
            ],
            'devices' => [
                (object) ['id' => new ObjectId(), 'userId' => $assignedUserId, 'fcmToken' => 'tok1', 'result' => 'PENDING', 'seen' => null],
            ],
            'deleted' => false,
            'historicArray' => [
                (object) [
                    'at' => new UTCDateTime((new \DateTime('2024-05-01'))->getTimestamp() * 1000),
                    'alerta' => 'HIST_A',
                    'alertaDes' => 'Historic alert A',
                    'aillat' => true,
                    'aillatParams' => ['p1'],
                    'corregit' => false,
                    'groupSol' => null,
                    'groupSeg' => null,
                    'users' => [],
                    'devices' => [],
                    'assignedUser' => (object) [
                        'userId' => $assignedUserId,
                        'at' => new UTCDateTime((new \DateTime('2024-05-01 10:00:00'))->getTimestamp() * 1000),
                    ],
                ],
            ],
            'aillat' => false,
            'corregit' => true,
            'alertaDes' => 'Zero alert desc via path B',
            'groupSeg' => 'SEG2',
            'groupSol' => 'SOL1',
            'assignedUser' => (object) [
                'userId' => new ObjectId(),
                'at' => new UTCDateTime((new \DateTime('2024-05-02'))->getTimestamp() * 1000),
            ],
        ];

        $obj = new MipaAlertesZeroAlerta();
        $obj->bsonUnserialize((array) $data);

        $this->assertSame('projecte0', $obj->getOrigin());
        $this->assertSame('CRITICAL', $obj->getAlertType());
        $this->assertCount(1, $obj->getUsers());
        $this->assertSame('u1', $obj->getUsers()[0]->getUuid());
        $this->assertCount(1, $obj->getDevices());
        $this->assertSame(MipaNotificationStatus::Pending, $obj->getDevices()[0]->getResult());
        $this->assertCount(1, $obj->getHistoricArray());
        $this->assertSame('HIST_A', $obj->getHistoricArray()[0]->getAlerta());
        $this->assertTrue($obj->getHistoricArray()[0]->isAillat());
        $this->assertNotNull($obj->getHistoricArray()[0]->getAssignedUser());
        $this->assertTrue($obj->isCorregit());
        $this->assertSame('Zero alert desc via path B', $obj->getAlertaDes());
        $this->assertNotNull($obj->getAssignedUser());
    }

    public function testUnserializeMipaAlertaDeviceResultNull(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new MipaAlertaDevice();
        $obj->bsonUnserialize([
            'id' => new ObjectId(),
            'userId' => new ObjectId(),
            'fcmToken' => null,
            'result' => null,
            'seen' => null,
        ]);

        $this->assertNull($obj->getResult());
        $this->assertNull($obj->getFcmToken());
    }

    public function testUnserializeMipaNotificationStatusFailure(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $obj = new MipaAlertaDevice();
        $obj->bsonUnserialize([
            'id' => new ObjectId(),
            'userId' => new ObjectId(),
            'fcmToken' => 'token',
            'result' => 'FAILURE',
            'seen' => new UTCDateTime((new \DateTime('2024-01-01'))->getTimestamp() * 1000),
        ]);

        $this->assertSame(MipaNotificationStatus::Failure, $obj->getResult());
    }

    public function testRoundtripEmcVerifyEmailRequest(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $uid = new ObjectId();
        $original = new EmcVerifyEmailRequest();
        $original->setId(new ObjectId());
        $original->setUserId($uid);
        $original->setPassword('verify-hash');
        $original->setExpirationDate(new \DateTime('2024-12-31 23:59:59'));
        $original->setUsed(false);
        $original->setEmail('verify@example.com');

        $serialized = $original->bsonSerialize();
        $restored = new EmcVerifyEmailRequest();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $uid, (string) $restored->getUserId());
        $this->assertSame('verify-hash', $restored->getPassword());
        $this->assertFalse($restored->isUsed());
        $this->assertSame('verify@example.com', $restored->getEmail());
    }

    public function testRoundtripEmcResetPasswordRequest(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $uid = new ObjectId();
        $original = new EmcResetPasswordRequest();
        $original->setId(new ObjectId());
        $original->setUserId($uid);
        $original->setPassword('reset-hash');
        $original->setExpirationDate(new \DateTime('2025-06-30'));
        $original->setUsed(true);
        $original->setNewEmail('newemail@example.com');

        $serialized = $original->bsonSerialize();
        $restored = new EmcResetPasswordRequest();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame((string) $uid, (string) $restored->getUserId());
        $this->assertTrue($restored->isUsed());
        $this->assertSame('newemail@example.com', $restored->getNewEmail());
    }

    public function testRoundtripEmcSessionFullStructure(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $fc = new EmcFcIntervals(55, 75, 95, 115);
        $report = new EmcSessionReport();
        $report->setMax(145.0);
        $report->setMin(55.0);
        $report->setCreatedAt(new \DateTime('2024-08-15 11:00:00'));

        $original = new EmcSession();
        $original->setUserId(new ObjectId());
        $original->setNhc('0000555');
        $original->setAppointmentId(new ObjectId());
        $original->setMax(145.0);
        $original->setMin(55.0);
        $original->setAvg(100.0);
        $original->setIni(70.0);
        $original->setReports([$report]);
        $original->setFcIntervals($fc);
        $original->setActive(true);
        $original->setDeviceConnected(false);
        $original->setContactedRequestAt(new \DateTime('2024-08-15 10:55:00'));

        $serialized = $original->bsonSerialize();
        $restored = new EmcSession();
        $restored->bsonUnserialize((array) $serialized);

        $this->assertSame('0000555', $restored->getNhc());
        $this->assertSame(100.0, $restored->getAvg());
        $this->assertSame(70.0, $restored->getIni());
        $this->assertCount(1, $restored->getReports());
        $this->assertSame(145.0, $restored->getReports()[0]->getMax());
        $this->assertNotNull($restored->getFcIntervals());
        $this->assertSame(55, $restored->getFcIntervals()->getZone1());
        $this->assertFalse($restored->isDeviceConnected());
        $this->assertInstanceOf(\DateTimeInterface::class, $restored->getContactedRequestAt());
    }

    public function testUnserializeMipaAlertaWithMultipleUsersAndDevices(): void
    {
        if (!$this->canUnserialize()) $this->markTestSkipped('PropertyInfoExtractor::getType() not available');

        $data = (object) [
            '_id' => new ObjectId(),
            'user' => new ObjectId(),
            'origin' => 'projecte0',
            'alertType' => 'MULTI_USER',
            'subscription' => 'sub-multi',
            'users' => [
                (object) ['id' => new ObjectId(), 'uuid' => 'uuid-1', 'subscription' => true, 'subscriptionAdmin' => false, 'seen' => null, 'devices' => []],
                (object) ['id' => new ObjectId(), 'uuid' => 'uuid-2', 'subscription' => false, 'subscriptionAdmin' => true, 'seen' => new UTCDateTime((new \DateTime('2024-01-01'))->getTimestamp() * 1000), 'devices' => []],
            ],
            'devices' => [
                (object) ['id' => new ObjectId(), 'userId' => new ObjectId(), 'fcmToken' => 'token-1', 'result' => 'SUCCESS', 'seen' => null],
                (object) ['id' => new ObjectId(), 'userId' => new ObjectId(), 'fcmToken' => 'token-2', 'result' => 'FAILURE', 'seen' => new UTCDateTime((new \DateTime('2024-02-01'))->getTimestamp() * 1000)],
                (object) ['id' => new ObjectId(), 'userId' => new ObjectId(), 'fcmToken' => 'token-3', 'result' => 'PENDING', 'seen' => null],
            ],
            'deleted' => false,
            'historicArray' => [],
            'aillat' => false,
            'corregit' => false,
            'alertaDes' => null,
            'groupSeg' => null,
            'groupSol' => null,
            'assignedUser' => null,
        ];

        $obj = new MipaAlertesZeroAlerta();
        $obj->bsonUnserialize((array) $data);

        $this->assertCount(2, $obj->getUsers());
        $this->assertSame('uuid-1', $obj->getUsers()[0]->getUuid());
        $this->assertTrue($obj->getUsers()[0]->isSubscription());
        $this->assertSame('uuid-2', $obj->getUsers()[1]->getUuid());
        $this->assertTrue($obj->getUsers()[1]->isSubscriptionAdmin());
        $this->assertInstanceOf(\DateTimeInterface::class, $obj->getUsers()[1]->getSeen());

        $this->assertCount(3, $obj->getDevices());
        $this->assertSame(MipaNotificationStatus::Success, $obj->getDevices()[0]->getResult());
        $this->assertSame(MipaNotificationStatus::Failure, $obj->getDevices()[1]->getResult());
        $this->assertSame(MipaNotificationStatus::Pending, $obj->getDevices()[2]->getResult());
    }
}