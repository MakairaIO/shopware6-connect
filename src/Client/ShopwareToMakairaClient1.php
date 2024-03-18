<?php

namespace Ixomo\MakairaConnect\Client;

use AllowDynamicProperties;
use Makaira\HttpClient\Curl;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[AllowDynamicProperties]
class ShopwareToMakairaClient1 extends Curl
{
    public function __construct(
        SystemConfigService $systemConfigService
    ) {
        parent::__construct();
    $this->systemConfigService = $systemConfigService;
    }
    /**
     * @inheritDoc
     */
    public function request($method, $url, $body = null, array $headers = array())
    {
        // Dynamically fetch base URL and instance for the API request
        $baseUrl = $this->systemConfigService->get('IxomoMakairaConnect.config.apiBaseUrl');
        $instance = $this->systemConfigService->get('IxomoMakairaConnect.config.apiInstance');

        $url = $baseUrl . $url; // Append the endpoint to the base URL

        // Inject the X-Makaira-Instance header
        $headers['X-Makaira-Instance'] = $instance;

        return parent::request($method, $url, $body, $headers); // Call the parent method
    }
}
