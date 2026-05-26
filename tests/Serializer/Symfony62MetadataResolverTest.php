<?php

namespace Athenea\MongoLib\Tests\Serializer;

use Athenea\MongoLib\Metadata\AbstractBsonMetadataResolver;
use Athenea\MongoLib\Metadata\Symfony\Symfony62MetadataResolver;

class Symfony62MetadataResolverTest extends AbstractMetadataResolverTest
{
    protected function setUp(): void
    {
        if (
            !method_exists(\Symfony\Component\PropertyInfo\PropertyInfoExtractor::class, 'getTypes')
            || class_exists(\Symfony\Component\TypeInfo\Type::class)
        ) {
            $this->markTestSkipped('Symfony 6.x PropertyInfoExtractor::getTypes() not available');
        }
        parent::setUp();
    }

    protected function createResolver(): AbstractBsonMetadataResolver
    {
        return new Symfony62MetadataResolver();
    }
}
