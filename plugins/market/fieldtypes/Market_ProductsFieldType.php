<?php
namespace Craft;

class Market_ProductsFieldType extends BaseElementFieldType
{
    // Properties
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Commerce Product');
    }

    /**
     * The element type this field deals with.
     *
     * @var string $elementType
     */
    protected $elementType = 'Market_Product';

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseElementFieldType::getAddButtonLabel()
     *
     * @return string
     */
    protected function getAddButtonLabel()
    {
        return Craft::t('Add a Product');
    }
}
