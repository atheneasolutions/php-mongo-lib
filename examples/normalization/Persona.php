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