<?php

namespace craft\commerce\migrations;

use craft\commerce\widgets\Orders;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ArrayHelper;

/**
 * m240223_101158_update_recent_orders_widget_settings migration.
 */
class m240223_101158_update_recent_orders_widget_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Find any instances of the recent orders widget
        $widgets = (new Query())
            ->select(['id', 'settings'])
            ->from(Table::WIDGETS)
            ->where(['type' => Orders::class])
            ->all();

        // Get all order statuses
        $orderStatuses = (new Query())
            ->select(['id', 'uid'])
            ->from(\craft\commerce\db\Table::ORDERSTATUSES)
            ->all();

        // Update the widget settings to move from `orderStatusId` to `orderStatuses`
        foreach ($widgets as $widget) {
            $settings = json_decode($widget['settings'], true);
            $orderStatusId = $settings['orderStatusId'] ?? null;
            $settings['orderStatuses'] = [];
            unset($settings['orderStatusId']);

            if ($orderStatusId !== null) {
                $orderStatus = ArrayHelper::firstWhere($orderStatuses, 'id', $orderStatusId);
                if ($orderStatus !== null) {
                    $settings['orderStatuses'][] = $orderStatus['uid'];
                }
            }

            $this->update(Table::WIDGETS, ['settings' => json_encode($settings)], ['id' => $widget['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240223_101158_update_recent_orders_widget_settings cannot be reverted.\n";
        return false;
    }
}
