<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\test\Fixture;
use yii\base\InvalidArgumentException;

/**
 * Sales Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SalesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/sales.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Sale::class;

    /**
     * @var string[]
     */
    public $depends = [ProductFixture::class, CategoriesFixture::class];

    /**
     * @inheritDoc
     */
    public function load()
    {
        $this->data = [];

        foreach ($this->getData() as $key => $data) {
            $purchasableIds = $data['_purchasableIds'] ?? null;
            if ($purchasableIds !== null) {
                unset($data['_purchasableIds']);
            }

            $categoryIds = $data['_categoryIds'] ?? null;
            if ($categoryIds !== null) {
                unset($data['_categoryIds']);
            }

            $userGroupIds = $data['_userGroupIds'] ?? null;
            if ($userGroupIds !== null) {
                unset($data['_userGroupIds']);
            }

            /**
             * @var $model Sale
             */
            $model = new $this->modelClass($data);

            if ($purchasableIds !== null) {
                $model->setPurchasableIds($purchasableIds);
            }

            if ($categoryIds !== null) {
                $model->setCategoryIds($categoryIds);
            }

            if ($userGroupIds !== null) {
                $model->setUserGroupIds($userGroupIds);
            }

            if (!Plugin::getInstance()->getSales()->saveSale($model)) {
                throw new InvalidArgumentException('Unable to save sale.');
            }

            $this->data[$key] = array_merge($data, ['id' => $model->id]);
            $this->ids[] = $model->id;
        }
    }

    /**
     * @inheritDoc
     */
    public function unload()
    {
        foreach ($this->getData() as $key => $data) {
            if (isset($data['id'])) {
                Plugin::getInstance()->getSales()->deleteSaleById($data['id']);
            }
        }
    }
}