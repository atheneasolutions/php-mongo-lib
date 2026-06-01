<?php

declare(strict_types=1);

/**
 * Tests for the Symfony DiscriminatorMap fallback path in resolveDiscriminatorMap.
 *
 * When a class carries Symfony's own @DiscriminatorMap annotation instead of
 * (or in addition to) our #[BsonDiscriminator] attribute, the resolver must
 * still build a BsonDiscriminatorMap from it.
 *
 * A stub for \Symfony\Component\Serializer\Attribute\DiscriminatorMap is
 * defined below so the fallback code path is exercised even when the real
 * symfony/serializer package is not installed.
 */

// ---------------------------------------------------------------------------
// Stub: \Symfony\Component\Serializer\Attribute\DiscriminatorMap
// Only defined when symfony/serializer is not installed.
// ---------------------------------------------------------------------------
namespace Symfony\Component\Serializer\Attribute {
    if (!class_exists(DiscriminatorMap::class)) {
        #[\Attribute(\Attribute::TARGET_CLASS)]
        final class DiscriminatorMap
        {
            public function __construct(
                public readonly string $typeProperty,
                public readonly array $mapping,
            ) {}

            public function getTypeProperty(): string { return $this->typeProperty; }
            public function getMapping(): array { return $this->mapping; }
        }
    }
}

// ---------------------------------------------------------------------------
// Test models and test class
// ---------------------------------------------------------------------------
namespace Athenea\MongoLib\Tests\Serializer {
    use Athenea\MongoLib\Attribute\BsonSerialize;
    use Athenea\MongoLib\Metadata\BsonDiscriminatorMap;
    use Athenea\MongoLib\Metadata\Symfony\Symfony62MetadataResolver;
    use Athenea\MongoLib\Metadata\Symfony\Symfony7MetadataResolver;
    use Athenea\MongoLib\Model\Base;
    use PHPUnit\Framework\TestCase;

    #[\Symfony\Component\Serializer\Attribute\DiscriminatorMap('kind', [
        'a' => SymfonyDiscChildA::class,
        'b' => SymfonyDiscChildB::class,
    ])]
    abstract class SymfonyDiscParent extends Base
    {
        #[BsonSerialize]
        protected ?string $kind = null;

        public function getKind(): ?string { return $this->kind; }
        public function setKind(?string $kind): void { $this->kind = $kind; }
    }

    class SymfonyDiscChildA extends SymfonyDiscParent
    {
        #[BsonSerialize]
        private ?string $valueA = null;

        public function getValueA(): ?string { return $this->valueA; }
        public function setValueA(?string $v): void { $this->valueA = $v; }
    }

    class SymfonyDiscChildB extends SymfonyDiscParent
    {
        #[BsonSerialize]
        private ?string $valueB = null;

        public function getValueB(): ?string { return $this->valueB; }
        public function setValueB(?string $v): void { $this->valueB = $v; }
    }

    class DiscriminatorMapFallbackTest extends TestCase
    {
        private function createResolver(): Symfony62MetadataResolver|Symfony7MetadataResolver
        {
            if (class_exists(\Symfony\Component\TypeInfo\Type::class)) {
                return new Symfony7MetadataResolver();
            }
            return new Symfony62MetadataResolver();
        }

        /**
         * Regression test for the uninitialized-$attributes spread bug.
         *
         * When the Symfony DiscriminatorMap class exists (either the real one or
         * our stub), resolveDiscriminatorMap enters the first if-block and does
         * `$attributes = [...$attributes, ...]`.  Before the fix, $attributes was
         * never initialised, so PHP emitted E_WARNING and then threw a TypeError
         * ("Cannot unpack null") when it tried to spread a null value.
         */
        public function testSymfonyDiscriminatorMapFallbackIsResolved(): void
        {
            $resolver = $this->createResolver();
            $metadata = $resolver->resolve(SymfonyDiscParent::class);

            $this->assertInstanceOf(BsonDiscriminatorMap::class, $metadata->discriminatorMap);
            $this->assertSame('kind', $metadata->discriminatorMap->typeProperty);
            $this->assertSame(
                ['a' => SymfonyDiscChildA::class, 'b' => SymfonyDiscChildB::class],
                $metadata->discriminatorMap->mapping
            );
        }

        public function testSymfonyDiscriminatorMapFallbackSubclassHasNullDiscriminatorMap(): void
        {
            $resolver = $this->createResolver();
            $metadata = $resolver->resolve(SymfonyDiscChildA::class);

            $this->assertNull($metadata->discriminatorMap);
        }
    }
}
