<?php

declare(strict_types=1);

namespace Makaira\Connect\Core\Content\Product\SalesChannel\Search;

use Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Makaira\Connect\Exception\NoDataException;
use Makaira\Connect\Service\AggregationProcessingService;
use Makaira\Connect\Service\BannerProcessingService;
use Makaira\Connect\Service\FilterExtractionService;
use Makaira\Connect\Service\MakairaProductFetchingService;
use Makaira\Connect\Service\ShopwareProductFetchingService;
use Makaira\Connect\Service\SortingMappingService;
use League\Pipeline\Pipeline;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use stdClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;

class ProductSearchRoute extends AbstractProductSearchRoute
{
    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterExtractionService $filterExtractionService,
        private readonly SortingMappingService $sortingMappingService,
        private readonly ShopwareProductFetchingService $shopwareProductFetchingService,
        private readonly MakairaProductFetchingService $makairaProductFetchingService,
        private readonly AggregationProcessingService $aggregationProcessingService,
        private readonly BannerProcessingService $bannerProcessingService,
        private readonly LoggerInterface $httpClientLogger,
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $this->validateSearchRequest($request);
        $query = $request->query->get('search');
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $makairaFilter = $this->filterExtractionService->extractMakairaFiltersFromRequest($request);

        try {
            $makairaSorting = $this->sortingMappingService->mapSortingCriteria($criteria);

            $makairaResponse = $this->makairaProductFetchingService->fetchProductsFromMakaira($context, $query, $criteria, $makairaSorting, $makairaFilter);

            if (!$makairaResponse instanceof stdClass) {
                throw new NoDataException('Keine Daten oder fehlerhaft vom Makaira Server.');
            }
        } catch (Exception $exception) {
            $this->httpClientLogger->error('[Makaira] ' . $exception->getMessage(), ['type' => self::class]);

            return $this->decorated->load($request, $context, $criteria);
        }

        $redirectUrl = $this->checkForSearchRedirect($makairaResponse);

        if ($redirectUrl) {
            $redirectResponse = new RedirectResponse($redirectUrl, 302);
            $redirectResponse->send();
        }

        $shopwareResult = $this->shopwareProductFetchingService->fetchProductsFromShopware($makairaResponse, $request, $criteria, $context);

        $result = (new Pipeline())
            ->pipe(fn ($payload): EntitySearchResult => $this->aggregationProcessingService->processAggregationsFromMakairaResponse($payload, $makairaResponse))
            ->pipe(fn ($payload): EntitySearchResult => $this->bannerProcessingService->processBannersFromMakairaResponse($payload, $makairaResponse, $context))
            ->process($shopwareResult);

        $this->eventDispatcher->dispatch(new ProductSearchCriteriaEvent($request, $criteria, $context), ProductEvents::PRODUCT_SEARCH_CRITERIA);

        $finalResult = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(new ProductSearchResultEvent($request, $finalResult, $context), ProductEvents::PRODUCT_SEARCH_RESULT);

        return new ProductSearchRouteResponse($finalResult);
    }

    private function validateSearchRequest(Request $request): void
    {
        if (!$request->get('search')) {
            throw RoutingException::missingRequestParameter('search');
        }
    }

    private function checkForSearchRedirect(stdClass $makairaResponse): ?string
    {
        $redirects = isset($makairaResponse->searchredirect) ? $makairaResponse->searchredirect->items : [];

        if (count($redirects) > 0) {
            $targetUrl = $redirects[0]->fields->targetUrl;

            if ($targetUrl) {
                return $targetUrl;
            }
        }

        return null;
    }
}
