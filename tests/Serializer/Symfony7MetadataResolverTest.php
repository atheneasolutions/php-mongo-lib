<?php

namespace Athenea\MongoLib\Tests\Serializer;

use Athenea\MongoLib\Metadata\AbstractBsonMetadataResolver;
use Athenea\MongoLib\Metadata\Symfony\Symfony7MetadataResolver;

class Symfony7MetadataResolverTest extends AbstractMetadataResolverTest
{
    protected function setUp(): void
    {
        if (!class_exists(\Symfony\Component\TypeInfo\Type::class)) {
            $this->markTestSkipped('Symfony 7+ TypeInfo not available');
        }
        parent::setUp();
    }

    protected function createResolver(): AbstractBsonMetadataResolver
    {
        return new Symfony7MetadataResolver();
    }
}