<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230324_080923_move_general_settings_to_per_store_settings migration.
 */
class m230324_080923_move_general_settings_to_per_store_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::STORES, 'autoSetNewCartAddresses', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'autoSetCartShippingMethodOption', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'autoSetPaymentSource', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'allowEmptyCartOnCheckout', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'allowCheckoutWithoutPayment', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'allowPartialPaymentOnCheckout', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'requireShippingAddressAtCheckout', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'requireBillingAddressAtCheckout', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'requireShippingMethodSelectionAtCheckout', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'useBillingAddressForTax', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'validateBusinessTaxIdAsVatId', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::STORES, 'orderReferenceFormat', $this->string());

        $commerceConfig = Craft::$app->getConfig()->getConfigFromFile('commerce');

        if (empty($commerceConfig)) {
            return true;
        }

        $data = [
            'autoSetNewCartAddresses' => $commerceConfig['autoSetNewCartAddresses'] ?? false,
            'autoSetCartShippingMethodOption' => $commerceConfig['autoSetCartShippingMethodOption'] ?? false,
            'autoSetPaymentSource' => $commerceConfig['autoSetPaymentSource'] ?? false,
            'allowEmptyCartOnCheckout' => $commerceConfig['allowEmptyCartOnCheckout'] ?? false,
            'allowCheckoutWithoutPayment' => $commerceConfig['allowCheckoutWithoutPayment'] ?? false,
            'allowPartialPaymentOnCheckout' => $commerceConfig['allowPartialPaymentOnCheckout'] ?? false,
            'requireShippingAddressAtCheckout' => $commerceConfig['requireShippingAddressAtCheckout'] ?? false,
            'requireBillingAddressAtCheckout' => $commerceConfig['requireBillingAddressAtCheckout'] ?? false,
            'requireShippingMethodSelectionAtCheckout' => $commerceConfig['requireShippingMethodSelectionAtCheckout'] ?? false,
            'useBillingAddressForTax' => $commerceConfig['useBillingAddressForTax'] ?? false,
            'validateBusinessTaxIdAsVatId' => $commerceConfig['validateBusinessTaxIdAsVatId'] ?? false,
            'orderReferenceFormat' => $commerceConfig['orderReferenceFormat'] ?? '{{number[:7]}}',
        ];
        $this->update(Table::STORES, $data);

        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.34', '<')) {
            $stores = $projectConfig->get('commerce.stores') ?? [];
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            foreach ($stores as $uid => $store) {
                $projectConfig->set("commerce.stores.$uid", array_merge($store, $data));
            }

            $projectConfig->muteEvents = $muteEvents;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230324_080923_move_general_settings_to_per_store_settings cannot be reverted.\n";
        return false;
    }
}
