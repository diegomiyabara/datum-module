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

namespace Miyabara\FeaturedProduct\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Api\GetStockUpdateInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Resolves the product server-side so the template stays free of lookups and the block
 * output remains cacheable by full page cache — only the quantity is fetched via JS.
 */
class FeaturedProduct implements ArgumentInterface
{
    private const IMAGE_ID = 'miyabara_featured_product';


    /** @var Product|null */
    private ?Product $product = null;

    /** @var bool */
    private bool $productResolved = false;

    /**
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param ImageFactory $imageFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageFactory $imageFactory,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Lets the block skip rendering entirely when there is nothing valid to show.
     *
     * @return bool
     */
    public function hasProduct(): bool
    {
        return $this->getProduct() !== null;
    }

    /**
     * Returns the product name, or an empty string so the template never needs a null check.
     *
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getProduct()?->getName();
    }

    /**
     * Final price (catalog rules and special price included), formatted for the current store.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        $product = $this->getProduct();

        if ($product === null) {
            return '';
        }

        $amount = $product->getPriceInfo()
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount()
            ->getValue();

        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Base image rendered through the catalog image pipeline (view.xml id).
     *
     * Resizing and placeholder fallback behave exactly like the rest of the storefront.
     *
     * @return string
     */
    public function getImageHtml(): string
    {
        $product = $this->getProduct();

        if ($product === null) {
            return '';
        }

        return $this->imageFactory->create($product, self::IMAGE_ID)->toHtml();
    }

    /**
     * Returns the product page URL for the current store.
     *
     * @return string
     */
    public function getProductUrl(): string
    {
        return (string) $this->getProduct()?->getProductUrl();
    }

    /**
     * Exposed here so the template can pass it to the JS component without knowing config paths.
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->config->getRefreshInterval($this->getStoreId());
    }

    /**
     * Includes the store code in the REST path.
     *
     * This way the call resolves config and stock in the caller's store scope
     * instead of falling back to the default store view.
     *
     * @return string
     */
    public function getStockRefreshUrl(): string
    {
        $storeCode = (string) $this->storeManager->getStore()->getCode();

        return $this->urlBuilder->getDirectUrl('rest/' . $storeCode . GetStockUpdateInterface::ROUTE);
    }

    /**
     * Lets the block expose the product cache tags, so saving the product purges cached pages.
     *
     * @return string[]
     */
    public function getProductIdentities(): array
    {
        $product = $this->getProduct();

        return $product !== null ? $product->getIdentities() : [];
    }

    /**
     * Loaded once per request.
     *
     * A missing SKU is logged and treated as "no product", so the homepage never
     * breaks because of a typo in the admin panel.
     *
     * @return Product|null
     */
    private function getProduct(): ?Product
    {
        if ($this->productResolved) {
            return $this->product;
        }

        $this->productResolved = true;
        $storeId = $this->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            return null;
        }

        $sku = $this->config->getSku($storeId);

        if ($sku === null) {
            return null;
        }

        try {
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException $e) {
            $this->logger->warning(
                'Featured product box skipped: configured SKU was not found.',
                ['sku' => $sku, 'store_id' => $storeId, 'exception' => $e],
            );

            return null;
        }

        if ($product instanceof Product) {
            $this->product = $product;
        }

        return $this->product;
    }

    /**
     * Returns the current store view id for scope-aware config and product loading.
     *
     * @return int
     */
    private function getStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
