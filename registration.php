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

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Miyabara_FeaturedProduct',
    __DIR__,
);
