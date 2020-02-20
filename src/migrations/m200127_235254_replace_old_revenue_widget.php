<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m200127_235254_replace_old_revenue_widget migration.
 */
class m200127_235254_replace_old_revenue_widget extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $type = 'craft\commerce\widgets\TotalRevenue';
        $widgetSettings = [
            'startDate' => null,
            'endDate' => null
        ];

        $oldRevenueWidgets = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%widgets}}')
            ->where(['type' => 'craft\commerce\widgets\Revenue'])
            ->all();

        if (empty($oldRevenueWidgets)) {
            return;
        }

        foreach ($oldRevenueWidgets as $oldRevenueWidget) {
            $oldSettings = Json::decode($oldRevenueWidget['settings']);
            $dateRange = 'past7Days';

            if (isset($oldSettings['dateRange']) && in_array($oldSettings['dateRange'], ['lastmonth', 'd30'])) {
                $dateRange = 'past30Days';
            }

            $settings = Json::encode(array_merge($widgetSettings, ['dateRange' => $dateRange]));

            $this->update('{{%widgets}}', compact('settings', 'type'), ['id' => $oldRevenueWidget['id']]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200127_235254_replace_old_revenue_widget cannot be reverted.\n";
        return false;
    }
}
