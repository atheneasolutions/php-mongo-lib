# Athenea MongoLib

PHP library for BSON serialization/deserialization with MongoDB. Provides a `Base` model class that implements `MongoDB\BSON\Serializable` and `MongoDB\BSON\Unserializable`, automatically converting typed PHP objects to and from BSON documents.

## Installation

```bash
composer require athenea/mongo-lib
```

### Requirements

| Dependency | Version | Purpose |
|---|---|---|
| PHP | ≥ 8.1 | Runtime |
| `mongodb/mongodb` | ^1.0 \|\| ^2.0 | MongoDB driver |
| `phpdocumentor/reflection-docblock` | ^5.3 | `@var` docblock parsing for collection types |
| `symfony/property-info` | ^5.0 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0 | Property type introspection |
| `symfony/property-access` | ^5.0 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0 | Private property read/write |

> **Note:** `symfony/serializer` is **not** a direct dependency. Discriminator maps use our own `#[BsonDiscriminator]` attribute (Symfony's `#[DiscriminatorMap]` is also supported as a fallback).

## Quick Start

### Define a model

```php
use Athenea\MongoLib\Attribute\BsonSerialize;
use Athenea\MongoLib\Model\Base;
use MongoDB\BSON\ObjectId;
use DateTime;

class Person extends Base
{
    #[BsonSerialize(name: '_id')]
    private ?ObjectId $id = null;

    #[BsonSerialize]
    private ?string $name = null;

    #[BsonSerialize]
    private ?DateTime $createdAt = null;

    // Getters and setters required for private properties
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function setCreatedAt(?DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
}
```

### Serialize to BSON

```php
$person = new Person();
$person->setName('Alice');
$person->setCreatedAt(new DateTime());

$bson = $person->bsonSerialize();
// { "_id": null, "name": "Alice", "created_at": UTCDateTime(...) }
```

### Deserialize from BSON

```php
$person = new Person();
$person->bsonUnserialize([
    '_id' => new ObjectId(),
    'name' => 'Bob',
    'created_at' => new UTCDateTime(),
]);
```

### Compute diffs for partial updates

```php
$changes = $oldPerson->bsonChanges($newPerson);
// ['$set' => ['name' => 'Bob'], '$unset' => ['deleted_at' => '']]
```

### Use with MongoDB collection

```php
$doc = $collection->findOne(
    ['_id' => new ObjectId('6240de600000000000000000')],
    ['typeMap' => ['root' => Person::class]]
);
// $doc is a hydrated Person instance
```

---

## Architecture

```
src/
├── Attribute/
│   ├── BsonSerialize.php           # Marks properties for BSON serialization
│   └── BsonDiscriminator.php       # Polymorphic discriminator (replaces Symfony's)
├── Metadata/
│   ├── BsonMetadata.php            # Value object: serializable + deserializable props
│   ├── BsonSerializableProperty.php
│   ├── BsonDeserializableProperty.php
│   ├── BsonPropertyType.php       # Value object: className, isCollection, isNullable
│   ├── BsonDiscriminatorMap.php    # Value object: typeProperty + mapping
│   ├── BsonMetadataResolverInterface.php
│   ├── AbstractBsonMetadataResolver.php  # Base resolver (uses PropertyInfoExtractor)
│   └── Symfony/
│       ├── Symfony62MetadataResolver.php  # SF 5.x / 6.x type resolution
│       └── Symfony7MetadataResolver.php   # SF 7.x / 8.x type resolution
├── PropertyAccess/
│   ├── BsonPropertyAccessorInterface.php
│   └── SymfonyPropertyAccessor.php        # Delegates to Symfony PropertyAccess
├── Serializer/
│   ├── BsonSerializer.php          # Core serializer (zero Symfony imports)
│   └── BsonSerializerInterface.php # Public contract
├── Model/
│   └── Base.php                    # Abstract base class
├── Aggregation/
│   ├── AbstractAggregation.php
│   └── MongoAggregation.php
├── Utils.php
└── bson.php                        # oid() helper function
```

### Framework-agnostic core

The **`Serializer/BsonSerializer.php`** has **zero Symfony imports**. All framework coupling is isolated in adapter files:

| Concern | Interface | Symfony Adapter |
|---|---|---|
| Property access | `BsonPropertyAccessorInterface` | `SymfonyPropertyAccessor` |
| Metadata resolution | `BsonMetadataResolverInterface` | `Symfony62MetadataResolver` / `Symfony7MetadataResolver` |
| Type introspection | — | `AbstractBsonMetadataResolver` (uses `PropertyInfoExtractor`) |

The serializer auto-detects the Symfony version at runtime and instantiates the correct resolver.

---

## Reference

### `#[BsonSerialize]` attribute

Marks a property for BSON serialization/deserialization.

```php
#[BsonSerialize]                    // uses snake_case of property name as BSON field
#[BsonSerialize(name: '_id')]      // explicit BSON field name
```

**Rules:**
- Private/protected properties require a getter (for serialization) and a setter (for deserialization), or be public.
- The `name` parameter is the BSON field name. If omitted, the property name is converted to `snake_case`.
- `DateTime` properties are automatically converted to/from `UTCDateTime`.
- `BackedEnum` properties are automatically converted to/from their scalar value.

### `#[BsonDiscriminator]` attribute

Declares a discriminator map on an abstract class for polymorphic deserialization.

```php
use Athenea\MongoLib\Attribute\BsonDiscriminator;

#[BsonDiscriminator('type', [
    'cat' => CatModel::class,
    'dog' => DogModel::class,
])]
abstract class AbstractAnimal extends Base
{
    #[BsonSerialize]
    private ?string $type = null;
}

class CatModel extends AbstractAnimal
{
    #[BsonSerialize]
    private ?int $lives = null;
}
```

When deserializing, the serializer reads `$data['type']` and instantiates the matching concrete class.

**Symfony `#[DiscriminatorMap]` fallback:** If your project already uses Symfony's serializer with `#[DiscriminatorMap]`, it works too — no changes needed.

### Supported types

| PHP type | BSON representation | Example |
|---|---|---|
| `string`, `int`, `float`, `bool` | Direct | `name: 'Alice'` |
| `?string` (nullable) | Direct or skip | `name: null` → property skipped |
| `DateTime`, `DateTimeInterface` | `UTCDateTime` | Automatic conversion |
| `ObjectId` | `ObjectId` | Preserved as-is |
| `BackedEnum` | scalar value | `Status::Active` → `1` |
| `array`, `stdClass` | Recursive | Nested serialization |
| `Base` subclass | Recursive `bsonSerialize()` | Nested models |
| `array<SubModel>` | Recursive with `@var` | See collection types below |

### Collection types with `@var`

PHP type hints don't express generic arrays (`array<SubModel>`). Use `@var` docblocks:

```php
#[BsonSerialize]
/** @var SimpleModel[] */
private array $items = [];
```

The metadata resolver reads `@var` annotations via `phpdocumentor/reflection-docblock` (through `symfony/property-info`) and deserializes each element as `SimpleModel`.

### ObjectId helper

```php
use function Athenea\MongoLib\BSON\oid;

$document = $collection->findOne(['_id' => oid('6240de600000000000000000')]);
```

---

## Testing

```bash
# Run tests (requires Symfony 8.0 by default)
vendor/bin/phpunit

# Test across all supported Symfony versions
php bin/test-all-symfony-versions.php
```

The cross-version script temporarily installs each Symfony version, runs the test suite, and restores the original `composer.json`.

---

## License

MIT License. See [LICENSE](./LICENSE) for details.