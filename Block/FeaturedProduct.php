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

namespace Miyabara\FeaturedProduct\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Miyabara\FeaturedProduct\ViewModel\FeaturedProduct as FeaturedProductViewModel;

/**
 * Renders the featured box only when there is something valid to show; all product data and
 * JS component parameters come from the view model and are wired in the template.
 */
class FeaturedProduct extends Template implements IdentityInterface
{
    /**
     * Ties the cached homepage to the product cache tags, so saving the featured product
     * (price, name, image) purges full page cache without manual flushes.
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $viewModel = $this->getViewModel();

        return $viewModel !== null ? $viewModel->getProductIdentities() : [];
    }

    /**
     * Renders nothing when the module is disabled or the SKU cannot be resolved.
     *
     * The homepage must never show a broken box.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $viewModel = $this->getViewModel();

        if ($viewModel === null || !$viewModel->hasProduct()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * The view model arrives as a layout XML argument, so its presence cannot be enforced by the constructor.
     *
     * @return FeaturedProductViewModel|null
     */
    private function getViewModel(): ?FeaturedProductViewModel
    {
        $viewModel = $this->getData('view_model');

        return $viewModel instanceof FeaturedProductViewModel ? $viewModel : null;
    }
}
