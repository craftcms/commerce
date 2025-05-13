<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Stores;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230920_051125_move_primary_currency_to_store_settings migration.
 */
class m230920_051125_move_primary_currency_to_store_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // add if column doesnt exist
        if (!$this->db->columnExists('{{%commerce_stores}}', 'currency')) {
            $this->addColumn('{{%commerce_stores}}', 'currency', $this->string()->notNull()->defaultValue('USD'));
        }

        $primaryCurrencyIso = (new Query())
            ->select('iso')
            ->from('{{%commerce_paymentcurrencies}}')
            ->where(['primary' => true])
            ->scalar();

        $storeId = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_stores}}'])
            ->scalar();

        // update all stores record with currency
        $this->update('{{%commerce_stores}}', ['currency' => $primaryCurrencyIso], ['id' => $storeId]);

        // Make project config updates
        $projectConfig = \Craft::$app->getProjectConfig();

        $storeUid = (new Query())
            ->select(['uid'])
            ->from(['{{%commerce_stores}}'])
            ->scalar();

        // delete the primary payment currency and drop primary column from payment currencies
        $this->dropColumn('{{%commerce_paymentcurrencies}}', 'primary');

        $this->dropIndexIfExists(Table::PAYMENTCURRENCIES, 'iso', true);
        $this->createIndex(null, Table::PAYMENTCURRENCIES, 'iso', false);

        // get store config
        $config = $projectConfig->get(Stores::CONFIG_STORES_KEY . '.' . $storeUid);

        $config['currency'] = $primaryCurrencyIso;
        $projectConfig->set(Stores::CONFIG_STORES_KEY . '.' . $storeUid,
            $config,
            'Moving the primary currency to the store in the project config');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230920_051125_move_primary_currency_to_store_settings cannot be reverted.\n";
        return false;
    }
}
