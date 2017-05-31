<?php
namespace craft\commerce\fieldtypes;

use craft\base\Field;
use craft\commerce\elements\Product;

/**
 * Class Product Field
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 */
class Products extends Field
{
    // Properties
    // =========================================================================

    /**
     * The element type this field deals with.
     *
     * @var string $elementType
     */
    protected $elementType = Product::class;

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('commerce', 'Commerce Products');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseElementFieldType::getAddButtonLabel()
     *
     * @return string
     */
    protected function getAddButtonLabel()
    {
        return Craft::t('commerce', 'Add a product');
    }
}
