<?php

namespace craft\commerce\test\fixtures\elements;

use Craft;
use yii\base\ErrorException;
use craft\base\Element;
use craft\test\fixtures\elements\ElementFixture;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;

/**
 * Class ProductFixture.
 *
 * Credit to: https://github.com/robuust/craft-fixtures
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Robuust digital | Bob Olde Hampsink <bob@robuust.digital>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since  3.2
 */
class ProductFixture extends ElementFixture
{
    /**
     * {@inheritdoc}
     */
    public $modelClass = Product::class;

    /**
     * @var array
     */
    protected $productTypeIds = [];

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        /** @var \craft\commerce\Plugin */
        $commerce = Craft::$app->getPlugins()->getPlugin('commerce');
        /** @var \craft\commerce\services\ProductTypes */
        $productTypesService = $commerce->getProductTypes();

        // Get all product type id's
        $productTypes = $productTypesService->getAllProductTypes();
        foreach ($productTypes as $productType) {
            $this->productTypeIds[$productType->handle] = $productType->id;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function isPrimaryKey(string $key): bool
    {
        return parent::isPrimaryKey($key) || in_array($key, ['typeId', 'title']);
    }

    /**
     * Get element errors.
     *
     * @param Element $element
     *
     * @throws ErrorException
     */
    protected function getErrors(Element $element): void
    {
        $errors = $element->getErrorSummary(true);

        foreach ($element->getVariants() as $variant) {
            $errors = array_merge($errors, $variant->getErrorSummary(true));
        }

        throw new ErrorException(join(' ', array_filter($errors)));
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteElement(Element $element): void
    {
        $variants = Variant::find()->productId($element->id)->all();

        foreach ($variants as $variant) {
            parent::deleteElement($variant, true);
        }

        parent::deleteElement($element, true);
    }
}
