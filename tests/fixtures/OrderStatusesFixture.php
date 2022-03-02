<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\models\OrderStatus;
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
    public string $saveMethod = 'saveOrderStatus';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteOrderStatusById';

    /**
     * @inheritdoc
     */
    public $service = 'orderStatuses';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        // TODO remove this when we figure out why things are being unlaoded twice #COM-54
        $_muteEvents = Craft::$app->getProjectConfig()->muteEvents;
        Craft::$app->getProjectConfig()->muteEvents = false;

        if (!empty($this->ids)) {
            foreach ($this->ids as $id) {
                if ($id == 1) {
                    // keep the new default status
                    continue;
                }

                $this->service->{$this->deleteMethod}($id);
            }
        }

        Craft::$app->getProjectConfig()->muteEvents = $_muteEvents;
    }
}