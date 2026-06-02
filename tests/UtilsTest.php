<?php

namespace Athenea\MongoLib\Tests;

use Athenea\MongoLib\Utils;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    // -- normalize() --

    public function testNormalizeScalarValues(): void
    {
        $doc = new BSONDocument(['name' => 'test', 'age' => 42]);
        $result = Utils::normalize($doc);

        $this->assertSame('test', $result['name']);
        $this->assertSame(42, $result['age']);
    }

    public function testNormalizeConvertsUTCDateTimeToDateTime(): void
    {
        $utc = new UTCDateTime(1_700_000_000_000);
        $doc = new BSONDocument(['ts' => $utc]);
        $result = Utils::normalize($doc);

        $this->assertInstanceOf(DateTime::class, $result['ts']);
        $this->assertSame(1_700_000_000, $result['ts']->getTimestamp());
    }

    public function testNormalizeConvertsNestedBSONDocument(): void
    {
        $nested = new BSONDocument(['inner' => 'value']);
        $doc = new BSONDocument(['nested' => $nested]);
        $result = Utils::normalize($doc);

        $this->assertIsArray($result['nested']);
        $this->assertSame('value', $result['nested']['inner']);
    }

    public function testNormalizeConvertsNestedBSONArray(): void
    {
        $arr = new BSONArray(['a', 'b']);
        $doc = new BSONDocument(['items' => $arr]);
        $result = Utils::normalize($doc);

        $this->assertIsArray($result['items']);
        $this->assertSame(['a', 'b'], $result['items']);
    }

    // -- normalizeArray() --

    public function testNormalizeArrayScalars(): void
    {
        $arr = new BSONArray(['x', 'y', 'z']);
        $result = Utils::normalizeArray($arr);

        $this->assertSame(['x', 'y', 'z'], $result);
    }

    public function testNormalizeArrayConvertsUTCDateTime(): void
    {
        $utc = new UTCDateTime(1_000_000_000_000);
        $arr = new BSONArray([$utc]);
        $result = Utils::normalizeArray($arr);

        $this->assertInstanceOf(DateTime::class, $result[0]);
    }

    public function testNormalizeArrayConvertsNestedBSONDocument(): void
    {
        $nested = new BSONDocument(['k' => 'v']);
        $arr = new BSONArray([$nested]);
        $result = Utils::normalizeArray($arr);

        $this->assertIsArray($result[0]);
        $this->assertSame('v', $result[0]['k']);
    }

    public function testNormalizeArrayConvertsNestedBSONArray(): void
    {
        $inner = new BSONArray([1, 2]);
        $arr = new BSONArray([$inner]);
        $result = Utils::normalizeArray($arr);

        $this->assertIsArray($result[0]);
        $this->assertSame([1, 2], $result[0]);
    }

    // -- normalizeIterable() --

    public function testNormalizeIterableScalars(): void
    {
        $result = Utils::normalizeIterable(['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function testNormalizeIterableConvertsUTCDateTime(): void
    {
        $utc = new UTCDateTime(500_000_000_000);
        $result = Utils::normalizeIterable([$utc]);

        $this->assertInstanceOf(DateTime::class, $result[0]);
    }

    public function testNormalizeIterableConvertsNestedBSONDocument(): void
    {
        $doc = new BSONDocument(['x' => 1]);
        $result = Utils::normalizeIterable([$doc]);

        $this->assertIsArray($result[0]);
        $this->assertSame(1, $result[0]['x']);
    }

    public function testNormalizeIterableConvertsNestedBSONArray(): void
    {
        $arr = new BSONArray([7, 8]);
        $result = Utils::normalizeIterable([$arr]);

        $this->assertSame([7, 8], $result[0]);
    }

    // -- normalizeGeneric() --

    public function testNormalizeGenericNullReturnsFalsy(): void
    {
        $this->assertNull(Utils::normalizeGeneric(null));
    }

    public function testNormalizeGenericBSONDocumentReturnsArray(): void
    {
        $doc = new BSONDocument(['key' => 'value']);
        $result = Utils::normalizeGeneric($doc);

        $this->assertIsArray($result);
        $this->assertSame('value', $result['key']);
    }

    public function testNormalizeGenericBSONArrayReturnsArray(): void
    {
        $arr = new BSONArray([1, 2, 3]);
        $result = Utils::normalizeGeneric($arr);

        $this->assertIsArray($result);
        $this->assertSame([1, 2, 3], $result);
    }

    public function testNormalizeGenericUTCDateTimeReturnsDateTime(): void
    {
        $utc = new UTCDateTime(1_600_000_000_000);
        $result = Utils::normalizeGeneric($utc);

        $this->assertInstanceOf(DateTime::class, $result);
    }

    public function testNormalizeGenericUnknownTypeReturnsNull(): void
    {
        $this->assertNull(Utils::normalizeGeneric(new \stdClass()));
    }

    // -- date() --

    public function testDateConvertsDateTimeToUTCDateTime(): void
    {
        $dt = new DateTime('2024-01-15 10:00:00');
        $result = Utils::date($dt);

        $this->assertInstanceOf(UTCDateTime::class, $result);
        $this->assertSame($dt->getTimestamp(), $result->toDateTime()->getTimestamp());
    }

    public function testDatePreservesTimestamp(): void
    {
        $ts = 1_700_000_000;
        $dt = new DateTime('@' . $ts);
        $result = Utils::date($dt);

        $this->assertSame($ts, $result->toDateTime()->getTimestamp());
    }

    // -- now() --

    public function testNowReturnsUTCDateTimeWithinCurrentSecond(): void
    {
        $before = (new DateTime())->getTimestamp();
        $result = Utils::now();
        $after = (new DateTime())->getTimestamp();

        $this->assertInstanceOf(UTCDateTime::class, $result);
        $ts = $result->toDateTime()->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    // -- insertOne() with array document --

    public function testInsertOneAddsTimestampsToArrayDocument(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->once())
            ->method('insertOne')
            ->with($this->callback(function (array $doc) {
                return $doc['updated_at'] instanceof UTCDateTime
                    && $doc['created_at'] instanceof UTCDateTime;
            }));

        $document = ['name' => 'test'];
        Utils::insertOne($collection, $document);

        $this->assertInstanceOf(UTCDateTime::class, $document['updated_at']);
        $this->assertInstanceOf(UTCDateTime::class, $document['created_at']);
    }

    // -- insertMany() with array documents --

    public function testInsertManyCallsCollectionInsertMany(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->once())->method('insertMany');

        $documents = [['a' => 1], ['b' => 2]];
        Utils::insertMany($collection, $documents);
    }

    // -- insertByChunks() --

    public function testInsertByChunksCallsInsertManyInBatches(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->exactly(2))->method('insertMany');

        $documents = array_fill(0, 3, ['x' => 1]);
        Utils::insertByChunks($collection, $documents, 2);
    }

    public function testInsertByChunksWithExactChunkSizeCallsOnce(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->exactly(1))->method('insertMany');

        $documents = [['a' => 1], ['b' => 2]];
        Utils::insertByChunks($collection, $documents, 2);
    }

    // -- updateOne() --

    public function testUpdateOneAddsUpdatedAt(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->once())
            ->method('updateOne')
            ->with(
                ['_id' => 'x'],
                $this->callback(function (array $update) {
                    return $update['$set']['updated_at'] instanceof UTCDateTime
                        && $update['$setOnInsert']['created_at'] instanceof UTCDateTime;
                })
            );

        Utils::updateOne($collection, ['_id' => 'x'], ['$set' => []]);
    }

    // -- updateMany() --

    public function testUpdateManyAddsUpdatedAt(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->once())
            ->method('updateMany')
            ->with(
                [],
                $this->callback(fn($u) => $u['$set']['updated_at'] instanceof UTCDateTime)
            );

        Utils::updateMany($collection, [], ['$set' => []]);
    }

    // -- replaceOne() --

    public function testReplaceOneAddsUpdatedAtToArrayDocument(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->method('replaceOne')
            ->willReturn($this->createConfiguredMock(\MongoDB\UpdateResult::class, [
                'getUpsertedCount' => 0,
            ]));

        $document = ['name' => 'test'];
        Utils::replaceOne($collection, ['_id' => 'x'], $document);

        $this->assertInstanceOf(UTCDateTime::class, $document['updated_at']);
    }

    // -- findOneAndUpdate() --

    public function testFindOneAndUpdateAddsUpdatedAt(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->expects($this->once())
            ->method('findOneAndUpdate')
            ->with(
                [],
                $this->callback(fn($u) => $u['$set']['updated_at'] instanceof UTCDateTime)
            );

        Utils::findOneAndUpdate($collection, [], ['$set' => []]);
    }

    // -- replaceOne() upsert path --

    public function testReplaceOneCallsUpdateOnUpsert(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $upsertedId = new \MongoDB\BSON\ObjectId();

        $collection->method('replaceOne')
            ->willReturn($this->createConfiguredMock(\MongoDB\UpdateResult::class, [
                'getUpsertedCount' => 1,
                'getUpsertedId'    => $upsertedId,
            ]));

        $collection->expects($this->once())
            ->method('updateOne')
            ->with(['_id' => $upsertedId]);

        $document = ['name' => 'test'];
        Utils::replaceOne($collection, ['_id' => 'x'], $document);
    }

    // -- findOneAndReplace() --

    public function testFindOneAndReplaceAddsUpdatedAt(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $collection->method('findOneAndReplace')
            ->willReturn(null);

        $document = ['name' => 'test'];
        Utils::findOneAndReplace($collection, [], $document);

        $this->assertInstanceOf(UTCDateTime::class, $document['updated_at']);
    }

    public function testFindOneAndReplaceCallsUpdateOnUpsert(): void
    {
        $collection = $this->createMock(\MongoDB\Collection::class);
        $upsertedId = new \MongoDB\BSON\ObjectId();

        $fakeResult = $this->createMock(\MongoDB\Model\BSONDocument::class);
        $fakeResult->method('offsetGet')->willReturn(1);

        $updateResult = $this->createConfiguredMock(\MongoDB\UpdateResult::class, [
            'getUpsertedCount' => 1,
            'getUpsertedId'    => $upsertedId,
        ]);

        $collection->method('findOneAndReplace')
            ->willReturn($updateResult);

        $collection->expects($this->once())
            ->method('updateOne')
            ->with(['_id' => $upsertedId]);

        $document = ['name' => 'test'];
        Utils::findOneAndReplace($collection, [], $document);
    }
}
