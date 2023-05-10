<?php

namespace Athenea\MongoLib\Aggregation;

/**
 * Classe per substituir Aggregation. Enlloc de estàtic, dinàmic i crear l'aggregació amb els camps que toquin
 */
interface MongoAggregation
{
    public function getAggregation(): array;
}
