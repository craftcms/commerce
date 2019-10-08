<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\test\fixtures\elements;

use Craft;
use craft\commerce\Plugin;
use yii\base\ErrorException;
use craft\base\Element;
use craft\test\fixtures\elements\ElementFixture;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
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
class ProductFixture extends ElementFixture
{
    // Properties
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public $modelClass = Product::class;

    /**
     * @var array
     */
    protected $productTypeIds = [];

    // Public Methods
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        // Ensure loaded
        $commerce = Plugin::getInstance();
        if (!$commerce) {
            throw new InvalidArgumentException('Commerce plugin needs to be loaded before using the ProductFixture'.);
        }

        // Get all product type id's
        $productTypes = $commerce->getProductTypes()->getAllProductTypes();
        foreach ($productTypes as $productType) {
            $this->productTypeIds[$productType->handle] = $productType->id;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    protected function isPrimaryKey(string $key): bool
    {
        return parent::isPrimaryKey($key) || in_array($key, ['typeId', 'title']);
    }
}
