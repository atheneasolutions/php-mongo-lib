<?php

namespace Athenea\MongoLib\Metadata;

/**
 * Contract for resolving and caching BSON serialization metadata.
 *
 * Implementations scan a class hierarchy at runtime, read
 * `#[BsonSerialize]` attributes, and produce a {@see BsonMetadata}
 * value object that describes which properties should be serialized /
 * deserialized and what their BSON field names are.
 *
 * The contract guarantees that repeated calls to `resolve()` with the
 * same class name return the *same metadata instance* (internal
 * caching).  The `reset()` method allows clearing the internal cache,
 * which is useful in tests where the same class may be re-analysed
 * with different expectations.
 *
 * ## Usage in tests
 *
 * ```php
 * final class MyResolverTest extends TestCase
 * {
 *     public function testCaching(): void
 *     {
 *         $resolver = new BsonMetadataResolver();
 *
 *         $a = $resolver->resolve(MyModel::class);
 *         $b = $resolver->resolve(MyModel::class);
 *
 *         $this->assertSame($a, $b, 'resolve() must return the same instance');
 *     }
 *
 *     public function testReset(): void
 *     {
 *         $resolver = new BsonMetadataResolver();
 *         $a = $resolver->resolve(MyModel::class);
 *         $resolver->reset();
 *         $b = $resolver->resolve(MyModel::class);
 *
 *         $this->assertNotSame($a, $b, 'reset() must produce a fresh instance');
 *     }
 * }
 * ```
 *
 * @see BsonMetadataResolver Default implementation shipped with the library.
 * @see BsonMetadata        The value object returned by `resolve()`.
 * @see BsonSerializer      Consumer of this interface.
 *
 * @author Lluc Bové <lluc.bove@atheneasolutions.com>
 */
interface BsonMetadataResolverInterface
{
    /**
     * Resolve metadata for the given class, caching internally.
     *
     * The first call for a given class triggers scanning of the class
     * hierarchy, reading `#[BsonSerialize]` attributes, and building
     * both the serializable and deserializable property maps.
     * Subsequent calls for the same class return the cached value.
     *
     * @param  string $className Fully qualified class name.
     * @return BsonMetadata Never null.
     */
    public function resolve(string $className): BsonMetadata;

    /**
     * Clear the internal cache.
     *
     * After calling this method, the next `resolve()` for any class
     * will re-scan the class hierarchy and produce a fresh
     * `BsonMetadata` instance.
     *
     * This is primarily useful in tests to ensure isolation between
     * test cases.
     */
    public function reset(): void;
}
