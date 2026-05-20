<?php

namespace Athenea\MongoLib\Tests\Model;

use Athenea\MongoLib\Attribute\BsonSerialize;
use Athenea\MongoLib\Model\Base;
use MongoDB\BSON\ObjectId;

class TestModel extends Base
{
    #[BsonSerialize]
    private ?string $name = null;

    #[BsonSerialize]
    private ?int $value = null;

    #[BsonSerialize(name: 'custom_field')]
    private ?string $field = null;

    #[BsonSerialize]
    private ?ObjectId $refId = null;

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getValue(): ?int { return $this->value; }
    public function setValue(?int $value): void { $this->value = $value; }

    public function getField(): ?string { return $this->field; }
    public function setField(?string $field): void { $this->field = $field; }

    public function getRefId(): ?ObjectId { return $this->refId; }
    public function setRefId(?ObjectId $refId): void { $this->refId = $refId; }
}

class ChildTestModel extends TestModel
{
    #[BsonSerialize]
    private ?string $extra = null;

    public function getExtra(): ?string { return $this->extra; }
    public function setExtra(?string $extra): void { $this->extra = $extra; }
}