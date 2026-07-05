<?php
/**
 * Miyabara_FeaturedProduct
 *
 * @vendor    Miyabara
 * @package   FeaturedProduct
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@gmail.com>
 */

declare(strict_types=1);

namespace Miyabara\FeaturedProduct\Plugin;

use Closure;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\Rest\SynchronousRequestProcessor;
use Miyabara\FeaturedProduct\Api\GetStockUpdateInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;

/**
 * Answers the stock poll with 304 when the client ETag still matches, skipping the service
 * entirely. Runs on every synchronous REST call (cheap method+path early exit) — the only
 * layer where an HTTP status can short-circuit a webapi route.
 */
class HttpCacheStockUpdate
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param StockVersion $stockVersion
     * @param Response $response
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockVersion $stockVersion,
        private readonly Response $response,
    ) {
    }

    /**
     * Returns 304 when the client's If-None-Match validator already matches the current stock token.
     *
     * The ETag is captured before proceed() on purpose: if the token is bumped mid-request,
     * the stale header only costs one extra full response on the next poll — the safe direction.
     *
     * @param SynchronousRequestProcessor $subject
     * @param Closure $proceed
     * @param Request $request
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(
        SynchronousRequestProcessor $subject,
        Closure $proceed,
        Request $request,
    ): void {
        if (!$this->isStockEndpoint($request)) {
            $proceed($request);

            return;
        }

        $version = $this->getCurrentVersion();

        if ($version === null) {
            $proceed($request);

            return;
        }

        $etag = $this->toEtag($version);

        if ($this->matchesIfNoneMatch((string) $request->getHeader('If-None-Match'), $etag)) {
            $this->setCacheHeaders($etag);
            $this->response->setHttpResponseCode(304);
            $this->response->clearBody();

            return;
        }

        $proceed($request);
        $this->setCacheHeaders($etag);
    }

    /**
     * Cheap guard so the plugin is a no-op for every other REST route.
     *
     * @param Request $request
     * @return bool
     */
    private function isStockEndpoint(Request $request): bool
    {
        return $request->getMethod() === Request::HTTP_METHOD_GET
            && rtrim($request->getPathInfo(), '/') === GetStockUpdateInterface::ROUTE;
    }

    /**
     * Mirrors the service guards: no token means the endpoint would 404 anyway.
     *
     * @return string|null
     */
    private function getCurrentVersion(): ?string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $sku = $this->config->getSku($storeId);

        if (!$this->config->isEnabled($storeId) || $sku === null) {
            return null;
        }

        return $this->stockVersion->get($sku);
    }

    /**
     * Cache-Control may be overwritten downstream (PHP session limiter emits no-store);
     * the ETag is what the storefront relies on, sent back manually as If-None-Match.
     *
     * @param string $etag
     * @return void
     */
    private function setCacheHeaders(string $etag): void
    {
        $this->response->setHeader('ETag', $etag, true);
        $this->response->setHeader('Cache-Control', 'private, no-cache', true);
    }

    /**
     * Quotes the token as a strong validator (RFC 9110 entity-tag).
     *
     * @param string $version
     * @return string
     */
    private function toEtag(string $version): string
    {
        return '"' . addcslashes($version, '"\\') . '"';
    }

    /**
     * Compares against every candidate in the header, accepting weak validators and "*".
     *
     * @param string $header
     * @param string $etag
     * @return bool
     */
    private function matchesIfNoneMatch(string $header, string $etag): bool
    {
        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                return true;
            }
        }

        return false;
    }
}
