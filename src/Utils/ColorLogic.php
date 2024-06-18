<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Utils;

use Ixomo\MakairaConnect\Exception\AggregationException;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;

class ColorLogic
{
    private const colorIds = [
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

    private const localizedColorNames = [
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

    public function MakairaColorFilter($aggregation): EntityResult
    {
        $options = [];

        foreach ($aggregation->values as $value) {
            $color = new PropertyGroupOptionEntity();
            $color->setName($value->key);
            $color->setId($value->key);
            $color->setColorHexCode($this->getColorName((int) $value->key));
            $color->setTranslated(['name' => $this->getColorLocalizedName((int) $value->key), 'position' => $value->position]);

            $options[] = $color;
        }

        return new EntityResult('filter_' . $aggregation->key, new EntityCollection(
            $options
        ));
    }

    private function getColorName(int $key)
    {
        if (!isset(self::colorIds[$key])) {
            throw new AggregationException('Farbenamen nicht gefunden in Map \Ixomo\MakairaConnect\Utils\ColorLogic::colorIds für Key: ' . $key);
        }

        return self::colorIds[$key];
    }

    private function getColorLocalizedName(int $key)
    {
        if (!isset(self::localizedColorNames[$key])) {
            throw new AggregationException('Farbenamen nicht gefunden in Map \Ixomo\MakairaConnect\Utils\ColorLogic::localizedColorNames für Key: ' . $key);
        }

        return self::localizedColorNames[$key];
    }
}
