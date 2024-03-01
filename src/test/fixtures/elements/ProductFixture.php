<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\test\fixtures\elements;

use craft\base\ElementInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\test\fixtures\elements\BaseElementFixture;
use yii\base\InvalidArgumentException;

/**
 * Class ProductFixture.
 *
 * Credit to: https://github.com/robuust/craft-fixtures
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Robuust digital | Bob Olde Hampsink <bob@robuust.digital>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since  2.1
 */
class ProductFixture extends BaseElementFixture
{
    /**
     * @var array
     */
    protected array $productTypeIds = [];

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        // Ensure loaded
        $commerce = Plugin::getInstance();
        if (!$commerce) {
            throw new InvalidArgumentException('Commerce plugin needs to be loaded before using the ProductFixture');
        }

        // Get all product type id's
        $this->productTypeIds = $this->_getProductTypeIds();
    }

    /**
     * @inheritdoc
     */
    public function afterLoad(): void
    {
        $this->productTypeIds = $this->_getProductTypeIds();

        // Generate catalog pricing
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
    }

    protected function createElement(): ElementInterface
    {
        return new Product();
    }

    /**
     * Get array of product type IDs indexed by handle.
     * This uses a raw query to avoid service level caching/memoization.
     *
     * @TODO review the necessity of this at the next breakpoint version. #COM-54
     */
    private function _getProductTypeIds(): array
    {
        return (new Query())
            ->select([
                'productTypes.id',
                'productTypes.handle',
            ])
            ->from([Table::PRODUCTTYPES . ' productTypes'])
            ->indexBy('handle')
            ->column();
    }
}
