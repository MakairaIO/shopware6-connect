<?php

namespace Ixomo\MakairaConnect\Utils;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;

class ColorLogic
{
    public function MakairaColorFilter($aggregation): EntityResult
    {
        $options = [];

        foreach ($aggregation->values as $value) {
            $color = new PropertyGroupOptionEntity();
            $color->setName($value->key);
            $color->setId($value->key);
            $color->setColorHexCode($this->getColorName($value->key));
            $color->setTranslated(['name' => $this->getColorLocalizedName($value->key), 'position' => $value->position]);

            $options[] = $color;
        }

        return new EntityResult('filter_' . $aggregation->key, new EntityCollection(
            $options
        ));
    }

    private function getColorName($key)
    {
        $colorIds = [
            1 => 'black',
            2 => 'brown',
            3 => 'beige',
            4 => 'gray',
            5 => 'white',
            6 => 'blue',
            7 => 'petrol',
            8 => 'green',
            9 => 'yellow',
            10 => 'orange',
            11 => 'red',
            12 => 'pink',
            13 => 'purple',
            14 => 'gold',
            15 => 'silver',
            16 => 'bronze',
            17 => 'champagner',
            18 => 'brass',
            19 => 'clear',
            20 => 'colorful',
            21 => 'colorful',
            22 => 'creme',
            23 => 'creme',
            24 => 'taupe',
            25 => 'copper',
            26 => 'linen',
            30 => 'red',
            31 => 'green',
            32 => 'pink',
            33 => 'green',
        ];

        return $colorIds[min($key, \count($colorIds) - 1)];
    }

    private function getColorLocalizedName($key)
    {
        $localizedColorNames = [
            1 => 'Schwarz',
            2 => 'Braun',
            3 => 'Beige',
            4 => 'Grau',
            5 => 'Weiß',
            6 => 'Blau',
            7 => 'Türkis',
            8 => 'Grün',
            9 => 'Gelb',
            10 => 'Orange',
            11 => 'Rot',
            12 => 'Pink',
            13 => 'Lila',
            14 => 'Gold',
            15 => 'Silber',
            16 => 'Bronze',
            17 => 'Champagner',
            18 => 'Messing',
            19 => 'Klar',
            20 => 'Bunt',
            21 => 'Gemustert',
            22 => 'Creme',
            23 => 'Natur',
            24 => 'Taupe',
            25 => 'Kupfer',
            26 => 'Leinen',
            30 => 'Dunkelrot',
            31 => 'Hellgrün',
            32 => 'Dunkelrosa',
            33 => 'Dunkelgrün',
        ];

        return $localizedColorNames[min($key, \count($localizedColorNames) - 1)];
    }
}
