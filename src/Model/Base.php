<?php

namespace Athenea\MongoLib\Model;

use Athenea\MongoLib\Attribute\BsonSerialize;

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
        $normalization = new stdClass;
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $reflectionClass = new ReflectionClass($this);
        $props = $reflectionClass->getProperties();
        $propsParent = $reflectionClass->getParentClass()->getProperties();
        $props = [...$propsParent, ...$props];
        foreach($props as $prop){
            $attrribute = $prop->getAttributes(BsonSerialize::class)[0] ?? null;
            if(!$attrribute) continue;
            $attrribute = $attrribute->newInstance();

            $propName = $prop->getName();
            $propName = u($propName)->snake();
            $name = $attrribute->name ?? $propName;

            $isReadable = $propertyAccessor->isReadable($this, $propName);
            if(!$isReadable) continue;
            
            $value = $propertyAccessor->getValue($this, $propName);

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
    public function bsonUnserialize($data)
    {
        $propertyInfo = $this->propertyInfo();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $reflectionClass = new ReflectionClass($this);
        $props = $reflectionClass->getProperties();
        $propsParent = $reflectionClass->getParentClass()->getProperties();
        $props = [...$propsParent, ...$props];
        foreach($props as $prop){
            $attrribute = $prop->getAttributes(BsonSerialize::class)[0] ?? null;
            if(!$attrribute) continue;
            $attrribute = $attrribute->newInstance();

            $propName = $prop->getName();
            $types = $propertyInfo->getTypes($this::class, $propName);
            $propNameSnake = u($propName)->snake()->toString();
            $name = $attrribute->name ?? $propNameSnake;

            if(isset($data[$name])) {
                $value = $data[$name];
                $unserializedValue = $this->unserializeProperty($value, $types);
                if(! is_null($unserializedValue) || ( $types && count($types) && $types[0]->isNullable())) $propertyAccessor->setValue($this, $propName, $unserializedValue);
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
        $builtinType = gettype($value);
        if($builtinType === 'object') {
            $wantedTypeClass = $wantedType?->getClassName();
            $className = get_class($value);
            if( $value instanceof UTCDateTimeInterface) return $value->toDateTime();
            if( $value instanceof Unserializable) {
                $x = new $className();
                $x->bsonUnserialize( (array) $value);
                return $x;
            }
            if( $value instanceof stdClass && $wantedTypeClass && is_subclass_of($wantedTypeClass, Unserializable::class)){
                $x = new $wantedTypeClass();
                $x->bsonUnserialize( (array) $value);
                return $x;
            }
            if($value instanceof StdClass){
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
            $newArray = [];
            foreach($value as $key => $value){
                $arrayTypes = $wantedType?->isCollection() ? $wantedType->getCollectionValueTypes() : null;
                $value = $this->unserializeProperty($value, $arrayTypes);
                $newArray[$key] = $value;
            }
            return $newArray;
        }
        else return $value;
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