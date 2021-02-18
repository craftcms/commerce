<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\OrderStatus;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use yii\base\InvalidArgumentException;

/**
 * OrderStatuses Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
class OrderStatusesFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/order-statuses.php';

    /**
     * @inheritdoc
     */
    public $modelClass = OrderStatus::class;

    /**
     * @inheritDoc
     */
    public $saveMethod = 'saveOrderStatus';

    /**
     * @inheritDoc
     */
    public $deleteMethod = 'deleteOrderStatusById';

    /**
     * @inheritDoc
     */
    public $service = 'orderStatuses';

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function unload()
    {
        if (!empty($this->ids)) {
            foreach ($this->ids as $id) {
                if ($id == 1) {
                    // keep the new default status
                    continue;
                }

                $arInstance = OrderStatusRecord::find()
                    ->where(['id' => $id])
                    ->one();

                if ($arInstance && !$arInstance->delete()) {
                    throw new InvalidArgumentException('Unable to delete Order Status instance');
                }
            }
        }
    }
}