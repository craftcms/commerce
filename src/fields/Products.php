<?php

namespace craft\commerce\fields;

use Craft;
use craft\commerce\elements\Product;
use craft\fields\BaseRelationField;

/**
 * Class Product Field
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 *
 */
class Products extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return Craft::t('commerce', 'Commerce Products');
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Product::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('commerce', 'Add a product');
    }
}
