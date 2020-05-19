<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\elements\Product;
use craft\errors\InvalidElementException;
use craft\test\fixtures\elements\ElementFixture;
use yii\db\Exception;

/**
 * Class ProductFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductFixture extends ElementFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/products.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Product::class;

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeFixture::class];

    /**
     * @inheritdoc
     */
    public function load()
    {
        $this->data = [];

        foreach ($this->getData() as $alias => $data) {
            /* @var Product $element */
            $element = $this->getElement($data) ?: new $this->modelClass;

            // If they want to add a date deleted. Store it but dont set that as an element property
            $dateDeleted = null;

            if (isset($data['dateDeleted'])) {
                $dateDeleted = $data['dateDeleted'];
                unset($data['dateDeleted']);
            }

            // Set the field layout
            if (isset($data['fieldLayoutType'])) {
                $fieldLayoutType = $data['fieldLayoutType'];
                unset($data['fieldLayoutType']);

                $fieldLayout = Craft::$app->getFields()->getLayoutByType($fieldLayoutType);
                if ($fieldLayout) {
                    $element->fieldLayoutId = $fieldLayout->id;
                } else {
                    codecept_debug("Field layout with type: $fieldLayoutType could not be found");
                }
            }

            if (isset($data['_variants'])) {
                $element->setVariants($data['_variants']);
                unset($data['_variants']);
            }

            foreach ($data as $handle => $value) {
                $element->$handle = $value;
            }

            if (!Craft::$app->getElements()->saveElement($element)) {
                throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
            }

            // Add it here
            if ($dateDeleted) {
                $elementRecord = \craft\records\Element::find()
                    ->where(['id' => $element->id])
                    ->one();

                $elementRecord->dateDeleted = $dateDeleted;

                if (!$elementRecord->save()) {
                    throw new Exception('Unable to set element as deleted');
                }
            } else {
                Craft::$app->getSearch()->indexElementAttributes($element);
            }

            $this->data[$alias] = array_merge($data, ['id' => $element->id]);
        }
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidElementException
     * @throws Throwable
     */
    public function unload()
    {
        if ($this->unload) {
            foreach ($this->getData() as $data) {
                $element = $this->getElement($data);

                $variants = [];
                if ($element) {
                    foreach ($element->getVariants() as $variant) {
                        $variants[] = $variant;
                    }
                }

                foreach ($variants as $variant) {
                    if (!Craft::$app->getElements()->deleteElement($variant, true)) {
                        throw new InvalidElementException($variant, 'Unable to delete variant ' . $variant->title . '.');
                    }
                }

                if ($element && !Craft::$app->getElements()->deleteElement($element, true)) {
                    throw new InvalidElementException($element, 'Unable to delete element.');
                }

            }

            $this->data = [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function isPrimaryKey(string $key): bool
    {
        return parent::isPrimaryKey($key) || in_array($key, ['title']);
    }
}
