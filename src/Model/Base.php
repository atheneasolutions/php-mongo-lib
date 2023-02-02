<?php

namespace Athenea\MongoLib\Model;

use Athenea\MongoLib\Attribute\BsonSerialize;
use BackedEnum;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Type;
use MongoDB\BSON\Unserializable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\UTCDateTimeInterface;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Type as PropertyInfoType;
use function Symfony\Component\String\u;

use ReflectionClass;
use stdClass;
use DateTime;
use Exception;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use ReflectionProperty;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * Classe per modelar documents de mongo i que siguin serialitazbles. 
 *
 * Si una classe hereda d'aquesta, es pot serialitzar i deserialitzar a mongoDB sempre i quant es compleixi el següent:
 * * Tots els camps que es volen serialitzar/deserialitzar implementen {@see Athenea\MongoLib\Attribute\BsonSerialize}
 * * Només es poden serialitzar/deserialitzar elements estandards de mongoDB, primitius, arrays, stdClass i classes que implementen {@see Serializable} o {@see Unserializable}
 * * Per deserialitzar correctament cal posar els tiups de tots els camps o bé a PHP o bé a la documentació
 * * Cal que els camps que siguin serialitzables implementin getters i els deserializables setters o bé que siguin públics
 * * Els camps de tipus {@see UTCDateTimeInterface} es deserialitzen a tipus {@see DateTime} i viceversa
 * * Per deserialitzar els camps només poden tenir un tipus. Si en tenen més s'inferirà el primer que es trobi
 * * Les arrays i els stdClass es deserialitzen/serialitzen recursivament
 *  @author  Lluc Bové <lluc.bove@atheneasolutions.com>
 */
abstract class Base implements Serializable, Unserializable {
    

    public function __construct()
    {
        
    }

    /**
     * Retorna una array o stdClass que es poden serialitzar a BSON
     * 
     * Troba tots els camps que té la classe que tenen l'atribut {@see BsonSerialize} i si són accesibles via getters i els posa en stdClass amb el seu nom en
     * camel case. 
     * Si els camps són classes que implementen {@see Serializable} es cridarà al mètode bsonSerialize per serialitzar-los.
     * Els objectes de tipus {@see DateTime} es serialitzen a {@see UTCDateTimeInterface}.
     * Els arrays es serialitzen recursivament.
     * Si el camp té un nom especificat amb el camp "name" de {@see BsonSerialize} té prioritat al nom de la variable.
     * 
     * @return array|stdClass Una array o stdClass que es poden serialitzar a BSON
     */
    public function bsonSerialize(): array|stdClass
    {
        $reflection = new ReflectionClass($this::class);
        $normalization = new stdClass;
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $fields = $this->classProperties($this::class);
        $fieldsById = array_reduce($fields, fn(array $acc, ReflectionProperty $x) => array_merge($acc,  [$x->getName() => $x]), []);
        $propertyInfo = $this->propertyInfo();
        $props = $propertyInfo->getProperties($this::class);
        foreach($props as $prop){
            $attribute = null;
            $field = $fieldsById[$prop] ?? null;
            if($field){
                $attribute = $field->getAttributes(BsonSerialize::class)[0] ?? null;
            }
            else{
                foreach(['get', 'is', 'has', 'can'] as $x){
                    $getter = $x.$prop;
                    if(!$reflection->hasMethod($getter)) continue;
                    $method = $reflection->getMethod($getter);
                    $attribute = $method->getAttributes(BsonSerialize::class)[0] ?? null;
                }
            }
            if(!$attribute) continue;
            $attribute = $attribute->newInstance();

            $fieldName = u($prop)->snake();
            $name = $attribute->name ?? $fieldName;

            $isReadable = $propertyAccessor->isReadable($this, $prop);
            if(!$isReadable) continue;
            
            $value = $propertyAccessor->getValue($this, $prop);
            $normalization->{$name} = $this->serializeProperty($value);
            
            
        }
        return $normalization;
    }


    /**
     * Deserialitza una array o objecte
     * 
     * Troba tots els camps que té la classe que tenen l'atribut {@see BsonSerialize} i si són accesibles via setters i els busca a $data amb seu nom en
     * camel case. 
     * Si els camps són classes que implementen {@see Unserializable} es cridarà al mètode bsonUnserialize per deserialitzar-los.
     * Els objectes de tipus {@see UTCDateTimeInterface} es deserialitzen a {@see DateTime}.
     * Els arrays i stdClass es deserialitzen recursivament.
     * Si el camp té un nom especificat amb el camp "name" de {@see BsonSerialize} té prioritat al nom de la variable.
     * Per fer la deserialització s'utilitzen els tipus inferits dels camps de la classe. Si no es troba cap tipus, es deserialitzarà tal com vingui de base de dades.
     * Ara mateix només es té en compte un tipus i prou. Si n'hi ha més d'un s'agafa el primer.
     * 
     * @return array|stdClass Una array o stdClass que es poden serialitzar a BSON
     */
    public function bsonUnserialize($data): void
    {
        $this->__construct();
        $fields = $this->classProperties($this::class);
        $fieldsById = array_reduce($fields, fn(array $acc, ReflectionProperty $x) => array_merge($acc,  [$x->getName() => $x]), []);
        $propertyInfo = $this->propertyInfo();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $props = $propertyInfo->getProperties($this::class);
        $reflection = new ReflectionClass($this::class);
        foreach($props as $prop){
            $field = $fieldsById[$prop] ?? null;
            $attribute = null;
            if($field){
                $attribute = $field->getAttributes(BsonSerialize::class)[0] ?? null;
            }
            else {
                $setter = "set".$prop;
                if(!$reflection->hasMethod($setter)) continue;
                $method = $reflection->getMethod($setter);
                $attribute = $method->getAttributes(BsonSerialize::class)[0] ?? null;
            }
            
            if(!$attribute) continue;
            $attribute = $attribute->newInstance();

            $types = $propertyInfo->getTypes($this::class, $prop);
            $propNameSnake = u($prop)->snake()->toString();
            $name = $attribute->name ?? $propNameSnake;

            if(isset($data[$name])) {
                $value = $data[$name];
                $unserializedValue = $this->unserializeProperty($value, $types);
                if(! is_null($unserializedValue) || ( $types && count($types) && $types[0]->isNullable())) $propertyAccessor->setValue($this, $prop, $unserializedValue);
            }
        }
    }
        
    /**
     * Serialitza una variable
     * 
     * Serialitza una variable tal com es descriu a {@see Base::bsonSerialize()}.
     * 
     * @todo Implementar conversió de tipus amb l'atribut {@see BsonSerialize}
     * @param mixed $value Valor a serialitzar
     * @return mixed Representació del valor a serialitzar
     */
    private function serializeProperty($value){
        $builtinType = gettype($value);
        if($builtinType === 'object') {
            $className =get_class($value);
            if( $className === DateTime::class) return  new UTCDateTime($value->getTimestamp() * 1000);
            if(is_subclass_of($className, Serializable::class)) return  $value->bsonSerialize();
            if(is_subclass_of($className, Type::class)) return $value;
            if(is_subclass_of($className, BackedEnum::class)) return $value->value;
            if($value instanceof stdClass) {
                $newObj = new stdClass;
                foreach($value as $key => $value){
                    $key = $this->serializeProperty($key);
                    $value = $this->serializeProperty($value);
                    $newObj->{$key} = $value;
                }
                return $newObj;
            }
            else throw new Exception("Class $className can't be bsonNormalized");
        }
        if($builtinType === 'array'){
            $newArray = [];
            foreach($value as $key => $value){
                $key = $this->serializeProperty($key);
                $value = $this->serializeProperty($value);
                $newArray[$key] = $value;
            }
            return $newArray;
        }
        else return $value;
    }

    /**
     * Retorna el valor a punt de ser deserialitzat
     * 
     * Retorna un valor a punt per ser deserialitzat tal i com es descriu a {@see Base::bsonUnserialize()}.
     * 
     * @todo implementar múltiples tipus. Ara mateix només agafa el primer
     * @param PropertyInfoType[] $types Array amb els tipus del camp a deserialitzar
     * @return mixed Representació del valor a punt per ser deserialitzada
     */
    private function unserializeProperty($value, ?array $types){
        $wantedType = $types ? (count($types) > 0 ? $types[0] : null) : null;
        $wantedTypeClass = $wantedType?->getClassName();
        $builtinType = gettype($value);
        if($builtinType === 'object') {
            $className = get_class($value);
            if( $value instanceof UTCDateTimeInterface) return $value->toDateTime();
            if($value instanceof BSONDocument){
                return $this->unserializeProperty($value->bsonSerialize(), $types);
            }
            if($value instanceof BSONArray){
                return $this->unserializeProperty($value->bsonSerialize(), $types);
            }
            if( $value instanceof Unserializable) {
                //TODO: revisar, sembla que no hauria de ser així. potser no cal la norma.
                $x = new $className();
                $x->bsonUnserialize( (array) $value);
                return $x;
            }
            if( $value instanceof stdClass && $wantedTypeClass && is_subclass_of($wantedTypeClass, Unserializable::class)){
                $concreteClass = $this->findConcreteClass($value, $wantedTypeClass);
                $x = new $concreteClass();
                $x->bsonUnserialize( (array) $value);
                return $x;
            }
            if($value instanceof StdClass && $wantedType?->getBuiltinType() === 'array'){
                $newObj = [];
                foreach($value as $key => $value){
                    $value = $this->unserializeProperty($value, null);
                    $newObj[$key] = $value;
                }
                return $newObj;
            }
            if($value instanceof StdClass){
                $className = get_class($value);
                $newObj = new stdClass;
                foreach($value as $key => $value){
                    //TODO: pensar si es poden definir tipus amb objectes
                    //$objTypes = $wantedType?->isCollection() ? $wantedType->getCollectionValueTypes() : null;
                    $value = $this->unserializeProperty($value, null);
                    $newObj->{$key} = $value;
                }
                return $newObj;
            }
            return $value;
        }
        if($builtinType === 'array'){
            if( $wantedTypeClass && is_subclass_of($wantedTypeClass, Unserializable::class)){
                $concreteClass = $this->findConcreteClass($value, $wantedTypeClass);
                $x = new $concreteClass();
                $x->bsonUnserialize($value);
                return $x;
            }
            $newArray = [];
            foreach($value as $key => $value){
                $arrayTypes = $wantedType?->isCollection() ? $wantedType->getCollectionValueTypes() : null;
                $value = $this->unserializeProperty($value, $arrayTypes);
                $newArray[$key] = $value;
            }
            return $newArray;
        }
        else{
            if(in_array($builtinType, ['string', 'int'])){
                if(is_subclass_of($wantedTypeClass, BackedEnum::class)){
                    return $wantedTypeClass::from($value);
                }
            }
            return $value;
        } 
    }

    /**
     * Donat un objecte i la classe que ha de representar, si la classe és abstracta comprova
     * si a l'objecte li correspon una classe concreta i en retorna el nom.
     * 
     * S'utilitza l'atribut de discriminator map de symfony {@see https://symfony.com/doc/current/components/serializer.html#serializing-interfaces-and-abstract-classes}
     * 
     * @param mixed $value El valor a deserialitzar de la classe
     * @param string $className nom de la classe
     * @return string nom de la classe concreta si n'hi ha
     */
    private function findConcreteClass(mixed $value, string $className){
        
        $classInfo = new ReflectionClass($className);
        $isAbstract = $classInfo->isAbstract();
        if(!$isAbstract) return $className;
        $attributes = $classInfo->getAttributes(DiscriminatorMap::class);
        foreach($attributes as $refAttribute){
            /**
             * @var DiscriminatorMap
             */
            $discMap = $refAttribute->newInstance();
            $type = $discMap->getTypeProperty();
            $valueType = is_array($value) ? ($value[$type] ?? null) : ($value->{$type} ?? null);
            $mapping = $discMap->getMapping();
            $newClass = $mapping[$valueType] ?? null;
            if(is_null($newClass)) continue;
            return $this->findConcreteClass($value, $newClass);
        }
        throw new Exception("Abstract class $className has no valid discriminator map, can't be unserialized");
    }

    /**
     * Retorna les propietats d'una classe tenint en compte les classes pare
     * que pugui tenir
     * 
     * @param string $className nom de la classe
     * @return ReflectionProperty[] propietats de la classe i el seus pares
     */
    private function classProperties(string $className){
        $reflectionClass = new ReflectionClass($className);
        $props = $reflectionClass->getProperties();
        if($parentClass = $reflectionClass->getParentClass()){
            $parentProperties = $this->classProperties($parentClass->getName());
            $props = [...$parentProperties, ...$props];
        }
        return $props;
    }

    /**
     * Retorna un extractor de informació de propietats
     * 
     * Inicialitza un extractor de propietats de Symfony capaç d'extreure informació
     * mitjançant reflexió i també mitjançant la documentació de les classes
     */
    private function propertyInfo(): PropertyInfoExtractor
    {
        // a full list of extractors is shown further below
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        // list of PropertyListExtractorInterface (any iterable)
        $listExtractors = [$reflectionExtractor];

        // list of PropertyTypeExtractorInterface (any iterable)
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];

        // list of PropertyDescriptionExtractorInterface (any iterable)
        $descriptionExtractors = [$phpDocExtractor];

        // list of PropertyAccessExtractorInterface (any iterable)
        $accessExtractors = [$reflectionExtractor];

        // list of PropertyInitializableExtractorInterface (any iterable)
        $propertyInitializableExtractors = [$reflectionExtractor];

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );
        return $propertyInfo;
    }

}