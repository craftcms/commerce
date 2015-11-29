<?php
namespace Craft;

/**
 * Class Commerce_ProductsFieldType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 */
class Commerce_ProductsFieldType extends BaseElementFieldType
{
    // Properties
    // =========================================================================

    /**
     * The element type this field deals with.
     *
     * @var string $elementType
     */
    protected $elementType = 'Commerce_Product';

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Commerce Products');
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
        return Craft::t('Add a product');
    }
}
