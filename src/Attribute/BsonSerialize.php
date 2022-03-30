<?php

namespace Athenea\MongoLib\Attribute;

use Attribute;

#[Attribute]
/**
 * Atribut que indica a {@see Athenea\MongoLib\Model\Base} que el camp que el té s'ha de serialitzar/deserialitzar
 *
 * Si un camp d'una classe conté aquest atribut aleshores es serialitzarà/deserialitzarà. Es poden indicar les següents opcions:
 * * name: El nom que tindrà el camp serialitzat/deserialitzat
 * 
 *  @author  Lluc Bové <lluc.bove@atheneasolutions.com>
 */
class BsonSerialize {

    public function __construct(
        /**
         * @var string Nom que tindrà el camp a serialitzar/deserialitzar
         */
        public ?string $name = null
    )
    {
        
    }
}