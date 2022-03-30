# Athenea MongoLib

Hola! Aquesta llibreria agrupa diferents utilitats per facilitar l'ús del driver oficial de mongo [mongo-php-library](https://github.com/mongodb/mongo-php-library) utilitzats per Athenea Solutions. De moment conté:

 - Utilitats per la serialització/deserialització
 - Un àlias per ObjectId
 

> La llibreria es troba en fase beta. Qualsevol bug reportar-lo a lluc.bove@atheneasolutions.com

[Documentació referència](./docs/index.html)

## Instalació

    composer install {nom a definir}
    
## Com fer servir
### Serialització / Deserialització
Es posa a disposició la classe `Athenea\Mongolib\Model\Base` que implementa les interfícies `MongoDB\BSON\Serializable` i `MongoDB\BSON\Unserializable`. Si una classe hereda d'aquesta es normalitzaran/denormalitzaran a BSON automàticament els seus camps que implementin l'atribut `Athenea\Mongolib\Attribute\BsonSerialize`.

Exemple:

    <?php
    use Athenea\MongoLib\Attribute\BsonSerialize;
    use Athenea\MongoLib\Model\Base;
    use DateTime;
    use MongoDB\BSON\ObjectId;
    
    class Persona extends Base {
    
        # Es pot especificar un nom concret amb el camp name
        #[BsonSerialize(name: "_id")]
        private ObjectId $id;
    
        #[BsonSerialize]
        private string $name;
    
        # Els camps amb DateTime es serialitzen a MongoDB/BSON/UTCDateTime i viceversa
        #[BsonSerialize]
        private DateTime $createdAt;
    
        # Les arrays es serialitzen/deserialitzen recursivament. Cal especificar el tipus de l'array en els comentaris.
        #[BsonSerialize]
        /**
         * @var array<User>
         */
        private array $friends;
    
    
        public function __construct()
        {
            $this->createdAt = new DateTime();
        }
    
        #Cal que els camps tinguin getters i setters o que siguin públic per serialitzar-los
        public function getId(): ObjectId
        {
            return $this->id;
        }
    
        public function setId(ObjectId $id): self
        {
            $this->id = $id;
    
            return $this;
        }
    
    
        public function getCreatedAt(){
            return $this->createdAt;
        }
    
        public function setCreatedAt(DateTime $createdAt){
            $this->createdAt = $createdAt;
        }
    
        public function getName(){
            return $this->name;
        }
    
        public function setName(string $name){
            $this->name = $name;
        }
    
        public function getFriends(){
            return $this->friends;
        }
    
        public function setFriends(array $friends){
            $this->friends = $friends;
            return $this;
        }
        
    }

Per a deserialitzar automàticament cal fer servir els `typeMap` de la llibreria de mongo ([typemaps](https://www.php.net/manual/en/mongodb.persistence.deserialization.php#mongodb.persistence.typemaps)):

    $client = new Client("mongodb://localhost");
    $db = $client->selectDatabase("bbdd");
    $col = $db->selectCollection("persones");
    $doc = $col->findOne( [ '_id' => ObjectId("6240de600000000000000000") ], [ 'typeMap' => [ 'root' => Persona::class ] ] );

#### A tenir en compte

 - Tots els camps que es volen serialitzar/deserialitzar han d'implementar `Athenea\MongoLib\Attribute\BsonSerialize`
 - Només es poden serialitzar/deserialitzar elements estandards de mongoDB, primitius, arrays, stdClass i classes que implementen  `MongoDB\BSON\Serializable` o  `MongoDB\BSON\Unserializable`
 - Per deserialitzar correctament cal posar els tiups de tots els camps o bé a PHP o bé a la documentació. Si no es fa es deserialitzaran tal i com venen de la base de dades.
 - Cal que els camps que siguin serialitzables implementin getters i els deserializables setters o bé que siguin publics.
 - Els camps de tipus `MongoDB/BSON/UTCDateTime` es deserialitzen a tipus `MongoDB/BSON/UTCDateTime` i viceversa
 - Per deserialitzar els camps només poden tenir un tipus. Si en tenen més s'inferirà el primer que es trobi.
 - Les arrays i els stdClass es deserialitzen/serialitzen recursivament fins a profunditat arbitrària

### Àlias ObjectId

    use function Athenea\MongoLib\BSON\oid;
	$x = $collection->findOne(['_id' => oid("6240de600000000000000000")]);

