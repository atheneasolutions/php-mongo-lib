<?php

namespace Athenea\MongoLib\Serializer;

use Athenea\MongoLib\Metadata\BsonMetadataResolverInterface;
use Athenea\MongoLib\Model\Base;
use stdClass;

/**
 * Contract for serializing and deserializing {@see Base} model objects
 * to and from BSON-compatible structures.
 *
 * Implementations handle the full lifecycle of model-to-BSON conversion:
 *
 * - **serialize** an object to `stdClass` for the MongoDB driver
 *   (recursively follows nested models, handles DateTime/UTCDateTime
 *   conversion, BackedEnum unwrapping, etc.).
 * - **unserialize** a BSON document (raw array or `stdClass`) back into
 *   a model object, using type information from
 *   {@see BsonMetadataResolverInterface}.
 * - **diff** two serialised objects to produce MongoDB update operators
 *   (`$set`, `$unset`, `$push`, `$pull`).
 *
 * A serializer obtains property metadata from an injected
 * {@see BsonMetadataResolverInterface}.  The resolver can be swapped
 * at runtime via `setMetadataResolver()`.
 *
 * ## Usage
 *
 * ```php
 * $resolver   = new BsonMetadataResolver();
 * $serializer = new BsonSerializer();
 * $serializer->setMetadataResolver($resolver);
 *
 * // Serialize
 * $bson = $serializer->serialize($model);
 *
 * // Deserialize
 * $serializer->unserialize($model, $rawBsonData);
 *
 * // Diff
 * $changes = $serializer->diff($oldModel, $newModel);
 * ```
 *
 * ## Testing
 *
 * The interface is designed to be mockable.  In unit tests, inject a
 * mock implementation and assert that the expected methods are called:
 *
 * ```php
 * $mockSerializer = $this->createMock(BsonSerializerInterface::class);
 * $mockSerializer->expects($this->once())->method('serialize');
 * ```
 *
 * @see BsonSerializer              Default implementation.
 * @see BsonMetadataResolverInterface  Injected dependency for metadata.
 * @see Base                        The model base class that delegates here.
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
interface BsonSerializerInterface
{
    /**
     * Serialize a model object to a BSON-compatible representation.
     *
     * Walks the model's properties marked with `#[BsonSerialize]`,
     * converts values recursively (DateTime → UTCDateTime,
     * BackedEnum → scalar, nested Base → nested stdClass, …),
     * and returns a plain `stdClass` or array ready to be passed to
     * the MongoDB driver.
     *
     * @param  Base $object The model to serialize.
     * @return array|stdClass
     */
    public function serialize(Base $object): array|stdClass;

    /**
     * Populate a model object from raw BSON data.
     *
     * Reads the model's `#[BsonSerialize]` properties, looks up the
     * corresponding BSON field names in `$data`, and writes the
     * converted values back onto the object.  Type information from
     * the metadata resolver drives recursive deserialization (e.g.
     * instantiating nested model classes).
     *
     * The object's constructor is re-called before deserialization to
     * reset any previous state.
     *
     * @param  Base  $object The model to populate (modified in-place).
     * @param  mixed $data   Raw BSON data, typically an array or stdClass.
     */
    public function unserialize(Base $object, mixed $data): void;

    /**
     * Compute the difference between two serialized views of two
     * model objects.
     *
     * Both objects are serialized, then compared recursively.  The
     * returned array contains MongoDB update operators:
     *
     * - `$set`   — fields whose value changed.
     * - `$unset` — fields present in the old object but absent in the new.
     * - `$push`  — new items appended to an associative array.
     * - `$pull`  — items removed from an associative array.
     *
     * This is used by `Base::bsonChanges()` and ultimately by
     * `AbstractRepository::update()` to issue efficient partial updates.
     *
     * @param  Base $old The original model state.
     * @param  Base $new The updated model state.
     * @return array{0?: array<string, mixed>, 1?: array<string, mixed>, ...}
     */
    public function diff(Base $old, Base $new): array;

    /**
     * Inject the metadata resolver used to obtain serialization
     * metadata for each class.
     *
     * Callers can swap the resolver at any time (e.g. during tests to
     * provide a mock resolver with pre-canned metadata).
     *
     * @param BsonMetadataResolverInterface $resolver
     */
    public function setMetadataResolver(BsonMetadataResolverInterface $resolver): void;
}
