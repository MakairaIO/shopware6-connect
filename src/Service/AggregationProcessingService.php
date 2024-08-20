<?php

declare(strict_types=1);

namespace Makaira\Connect\Service;

use Makaira\Connect\Utils\ColorLogic;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

readonly class AggregationProcessingService
{
    public function __construct(
        private ColorLogic $colorLogic,
    ) {
    }

    public function processAggregationsFromMakairaResponse(
        EntitySearchResult $shopwareResult,
        $makairaResponse,
    ): EntitySearchResult {
        foreach ($makairaResponse->product->aggregations as $aggregation) {
            $makFilter = $this->createAggregationFilter($aggregation);
            if ($makFilter instanceof AggregationResult) {
                $shopwareResult->getAggregations()->add($makFilter);
            }
        }

        return $shopwareResult;
    }

    private function createAggregationFilter($aggregation): ?AggregationResult
    {
        if ($aggregation->key === 'color') {
            return $this->colorLogic->MakairaColorFilter($aggregation);
        }

        // Generic aggregation die für alle gleich sind.
        return match ($aggregation->type) {
            'range_slider_price' => new StatsResult('filter_' . $aggregation->key, $aggregation->min, $aggregation->max, ($aggregation->min + $aggregation->max) / 2, $aggregation->max),
            'list_multiselect', 'list_multiselect_custom_1' => $this->createCustomAggregationFilter($aggregation),
            default => null,
        };
    }

    private function createCustomAggregationFilter($aggregation): ?EntityResult
    {
        // we use this currently for Nachhaltigkeit and Sale
        // they have 0 or 1 as their values
        // we only want to show if there is a 1
        $showFilter = false;
        foreach ($aggregation->values as $value) {
            if (1 == $value->key) {
                $showFilter = true;
            }
        }

        if ($showFilter) {
            $options = [];
            $option = new PropertyGroupOptionEntity();
            $option->setName($aggregation->key);
            $option->setId($aggregation->key);
            $option->setTranslated(['name' => $aggregation->title]);
            $options[] = $option;

            return new EntityResult('filter_' . $aggregation->key, new EntityCollection(
                $options
            ));
        }

        return null;
    }
}
