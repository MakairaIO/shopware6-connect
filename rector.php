<?php

use Frosh\Rector\Set\ShopwareSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::EARLY_RETURN,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::GMAGICK_TO_IMAGICK,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        ShopwareSetList::SHOPWARE_6_5_0,
    ]);
    $rectorConfig->paths([__DIR__ . '/src']);
};
