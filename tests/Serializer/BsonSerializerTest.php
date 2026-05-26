<?php

namespace Athenea\MongoLib\Tests\Serializer;

use Athenea\MongoLib\Serializer\BsonSerializer;
use Athenea\MongoLib\Serializer\BsonSerializerInterface;
use Athenea\MongoLib\Tests\Model\ArrayModel;
use Athenea\MongoLib\Tests\Model\ChildModel;
use Athenea\MongoLib\Tests\Model\CustomNameModel;
use Athenea\MongoLib\Tests\Model\DateTimeModel;
use Athenea\MongoLib\Tests\Model\EnumModel;
use Athenea\MongoLib\Tests\Model\ExtendedSimpleModel;
use Athenea\MongoLib\Tests\Model\MethodAttributeModel;
use Athenea\MongoLib\Tests\Model\NestedModel;
use Athenea\MongoLib\Tests\Model\SimpleNestedModel;
use Athenea\MongoLib\Tests\Model\ObjectIdModel;
use Athenea\MongoLib\Tests\Model\PublicPropertyModel;
use Athenea\MongoLib\Tests\Model\SimpleModel;
use Athenea\MongoLib\Tests\Model\TestPlatform;
use Athenea\MongoLib\Tests\Model\TestStatus;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use stdClass;

class BsonSerializerTest extends TestCase
{
    private BsonSerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BsonSerializer();
    }

    // -- Serialization --

    public function testSerializeSimpleModel(): void
    {
        $model = new SimpleModel();
        $model->setName('test');
        $model->setCount(42);

        $result = $this->serializer->serialize($model);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('test', $result->name);
        $this->assertSame(42, $result->count);
    }

    public function testSerializeCustomBsonFieldNames(): void
    {
        $model = new CustomNameModel();
        $id = new ObjectId('6475ba14aa280d1db80656f2');
        $model->setId($id);
        $model->setField('my-value');

        $result = $this->serializer->serialize($model);

        $this->assertObjectHasProperty('_id', $result);
        $this->assertObjectHasProperty('custom_field_name', $result);
        $this->assertSame('my-value', $result->custom_field_name);
    }

    public function testSerializeDateTimeToUTCDateTime(): void
    {
        $date = new \DateTime('2024-01-15 10:30:00');
        $model = new DateTimeModel();
        $model->setTimestamp($date);

        $result = $this->serializer->serialize($model);

        $this->assertInstanceOf(UTCDateTime::class, $result->timestamp);
        $this->assertSame($date->getTimestamp() * 1000, $result->timestamp->toDateTime()->getTimestamp() * 1000);
    }

    public function testSerializeBackedEnumToScalar(): void
    {
        $model = new EnumModel();
        $model->setPlatform(TestPlatform::Mobile);
        $model->setStatus(TestStatus::Active);

        $result = $this->serializer->serialize($model);

        $this->assertSame('mobile', $result->platform);
        $this->assertSame(1, $result->status);
    }

    public function testSerializeNestedModel(): void
    {
        $child = new SimpleModel();
        $child->setName('inner');

        $model = new NestedModel();
        $model->setChild($child);

        $result = $this->serializer->serialize($model);

        $this->assertInstanceOf(stdClass::class, $result->child);
        $this->assertSame('inner', $result->child->name);
    }

    public function testSerializeArrayOfModels(): void
    {
        $item1 = new SimpleModel();
        $item1->setName('first');
        $item2 = new SimpleModel();
        $item2->setName('second');

        $model = new ArrayModel();
        $model->setItems([$item1, $item2]);

        $result = $this->serializer->serialize($model);

        $this->assertIsArray($result->items);
        $this->assertCount(2, $result->items);
        $this->assertSame('first', $result->items[0]->name);
        $this->assertSame('second', $result->items[1]->name);
    }

    public function testSerializeArrayOfScalars(): void
    {
        $model = new ArrayModel();
        $model->setTags(['urgent', 'review']);

        $result = $this->serializer->serialize($model);

        $this->assertIsArray($result->tags);
        $this->assertSame(['urgent', 'review'], $result->tags);
    }

    public function testSerializeObjectId(): void
    {
        $id = new ObjectId('6475ba14aa280d1db80656f2');
        $model = new ObjectIdModel();
        $model->setId($id);

        $result = $this->serializer->serialize($model);

        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame((string) $id, (string) $result->_id);
    }

    public function testSerializeMethodAttributeProperty(): void
    {
        $model = new MethodAttributeModel();
        $model->setBy('method-value');

        $result = $this->serializer->serialize($model);

        $this->assertSame('method-value', $result->by);
    }

    public function testSerializePublicProperties(): void
    {
        $model = new PublicPropertyModel();
        $model->name = 'public-name';
        $model->value = 99;

        $result = $this->serializer->serialize($model);

        $this->assertSame('public-name', $result->name);
        $this->assertSame(99, $result->value);
    }

    public function testSerializeInheritedProperties(): void
    {
        $model = new ChildModel();
        $model->setName('parent-prop');
        $model->setExtra('child-prop');

        $result = $this->serializer->serialize($model);

        $this->assertSame('parent-prop', $result->name);
        $this->assertSame('child-prop', $result->extra);
    }

    // -- Deserialization --

    public function testUnserializeSimpleModel(): void
    {
        $model = new SimpleModel();
        $this->serializer->unserialize($model, ['name' => 'loaded', 'count' => 7]);

        $this->assertSame('loaded', $model->getName());
        $this->assertSame(7, $model->getCount());
    }

    public function testUnserializeDateTimeFromUTCDateTime(): void
    {
        $ts = (new \DateTime('2024-06-01 12:00:00'))->getTimestamp() * 1000;
        $utc = new UTCDateTime($ts);

        $model = new DateTimeModel();
        $this->serializer->unserialize($model, ['timestamp' => $utc]);

        $this->assertInstanceOf(\DateTime::class, $model->getTimestamp());
        $this->assertSame(1717243200, $model->getTimestamp()->getTimestamp());
    }

    public function testUnserializeBackedEnumFromScalar(): void
    {
        $model = new EnumModel();
        $this->serializer->unserialize($model, ['platform' => 'web', 'status' => 1]);

        $this->assertSame(TestPlatform::Web, $model->getPlatform());
        $this->assertSame(TestStatus::Active, $model->getStatus());
    }

    public function testUnserializeNestedModel(): void
    {
        $model = new SimpleNestedModel();
        $this->serializer->unserialize($model, ['child' => ['name' => 'inner']]);

        $this->assertNotNull($model->getChild());
        $this->assertSame('inner', $model->getChild()->getName());
    }

    public function testUnserializeArrayOfModels(): void
    {
        $model = new ArrayModel();
        $this->serializer->unserialize($model, [
            'items' => [
                ['name' => 'first'],
                ['name' => 'second'],
            ],
        ]);

        $items = $model->getItems();
        $this->assertCount(2, $items);
        $this->assertSame('first', $items[0]->getName());
        $this->assertSame('second', $items[1]->getName());
    }

    public function testUnserializeCustomBsonFieldNames(): void
    {
        $id = new ObjectId('6475ba14aa280d1db80656f2');
        $model = new CustomNameModel();
        $this->serializer->unserialize($model, [
            '_id' => $id,
            'custom_field_name' => 'loaded',
        ]);

        $this->assertEquals($id, $model->getId());
        $this->assertSame('loaded', $model->getField());
    }

    public function testUnserializePublicProperties(): void
    {
        $model = new PublicPropertyModel();
        $this->serializer->unserialize($model, ['name' => 'pub', 'value' => 55]);

        $this->assertSame('pub', $model->name);
        $this->assertSame(55, $model->value);
    }

    public function testUnserializeMethodAttributeProperty(): void
    {
        $model = new MethodAttributeModel();
        $this->serializer->unserialize($model, ['by' => 'setter-value']);

        $this->assertSame('setter-value', $model->getOrigin());
    }

    // -- Diff --

    public function testDiffDetectsChangedValue(): void
    {
        $old = new SimpleModel();
        $old->setName('old');
        $new = new SimpleModel();
        $new->setName('new');

        $changes = $this->serializer->diff($old, $new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame('new', $changes['$set']['name']);
    }

    public function testDiffDetectsRemovedField(): void
    {
        $old = new SimpleModel();
        $old->setName('exists');
        $new = new SimpleModel();

        $changes = $this->serializer->diff($old, $new);

        $this->assertArrayHasKey('$unset', $changes);
        $this->assertArrayHasKey('name', $changes['$unset']);
    }

    public function testDiffDetectsAddedField(): void
    {
        $old = new SimpleModel();
        $new = new SimpleModel();
        $new->setName('added');

        $changes = $this->serializer->diff($old, $new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame('added', $changes['$set']['name']);
    }

    public function testDiffReturnsEmptyWhenNoChanges(): void
    {
        $old = new SimpleModel();
        $old->setName('same');
        $new = new SimpleModel();
        $new->setName('same');

        $changes = $this->serializer->diff($old, $new);

        $this->assertEmpty($changes);
    }

    public function testDiffObjectIdChanges(): void
    {
        $id1 = new ObjectId('6475ba14aa280d1db80656f1');
        $id2 = new ObjectId('6475ba14aa280d1db80656f2');

        $old = new ObjectIdModel();
        $old->setId($id1);
        $new = new ObjectIdModel();
        $new->setId($id2);

        $changes = $this->serializer->diff($old, $new);

        $this->assertArrayHasKey('$set', $changes);
        $this->assertSame((string) $id2, (string) $changes['$set']['_id']);
    }

    // -- Integration --

    public function testRoundtripSerializeThenUnserialize(): void
    {
        $original = new SimpleModel();
        $original->setName('roundtrip');
        $original->setCount(99);
        $original->setActive(true);

        $bson = $this->serializer->serialize($original);
        $restored = new SimpleModel();
        $this->serializer->unserialize($restored, (array) $bson);

        $this->assertSame('roundtrip', $restored->getName());
        $this->assertSame(99, $restored->getCount());
        $this->assertTrue($restored->isActive());
    }
}
