<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\db\Table;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;

/**
 * Class ShippingCategoryFixture
 * @package craftcommercetests\fixtures
 */
class ShippingCategoryFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/shipping-category.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ShippingCategory::class;

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveShippingCategory';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteShippingCategoryById';

    /**
     * @inheritdoc
     */
    public $service = 'shippingCategories';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    public function load(): void
    {
        parent::load();
        Plugin::getInstance()->getShippingCategories()->clearCaches();
    }

    public function unload(): void
    {
        parent::unload();

        // Hard delete
        \Craft::$app->getDb()->createCommand()->delete(Table::SHIPPINGCATEGORIES, ['not', ['dateDeleted' => null]])->execute();
    }
}
