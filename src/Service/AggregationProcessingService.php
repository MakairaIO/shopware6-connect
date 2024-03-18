<?php

namespace Ixomo\MakairaConnect\Service;

use Ixomo\MakairaConnect\Utils\ColorLogic;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class AggregationProcessingService
{
    public function __construct(
        private readonly ColorLogic $colorLogic
    ) {
    }

    public function processAggregationsFromMakairaResponse(EntitySearchResult $shopwareResult, $makairaResponse)
    {
        foreach ($makairaResponse->product->aggregations as $aggregation) {
            $makFilter = $this->createAggregationFilter($aggregation);
            if ($makFilter) {
                $shopwareResult->getAggregations()->add($makFilter);
            }
        }
        return $shopwareResult;
    }

    private function createAggregationFilter($aggregation)
    {
        switch ($aggregation->type) {
            case 'list_multiselect':
                return $this->colorLogic->MakairaColorFilter($aggregation);
            case 'range_slider_price':
                return new StatsResult('filter_' . $aggregation->key, $aggregation->min, $aggregation->max, ($aggregation->min + $aggregation->max) / 2, $aggregation->max);
            case 'list_multiselect_custom_1':
                return $this->createCustomAggregationFilter($aggregation);
            default:
                return null; // In case none of the types match
        }
    }

    private function createCustomAggregationFilter($aggregation)
    {
        $options = [];
        $color = new PropertyGroupOptionEntity();
        $color->setName($aggregation->key);
        $color->setId($aggregation->key);
        $color->setTranslated(['name' => $aggregation->title]);
        $options[] = $color;
        return new EntityResult('filter_' . $aggregation->key, new EntityCollection($options));
    }
}
