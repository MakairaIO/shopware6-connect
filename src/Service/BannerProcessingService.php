<?php

namespace Ixomo\MakairaConnect\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;

class BannerProcessingService
{
    public function __construct()
    {
    }

    public function processBannersFromMakairaResponse(EntitySearchResult $shopwareResult, $makairaResponse): EntitySearchResult
    {
        $banner = array();

        if (!isset($makairaResponse->banners)) {
            return $shopwareResult;
        }

        foreach ($makairaResponse->banners as $item) {
            if (isset($item->position)) {
                $banner[$item->position] = $item;
            }
        }

        $banner = new ArrayEntity($banner);
        $shopwareResult->addExtension('makairaBanner', $banner);

        return $shopwareResult;
    }
}
