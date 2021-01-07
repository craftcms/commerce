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
use yii\base\InvalidArgumentException;

/**
 * OrderStatuses Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
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
}