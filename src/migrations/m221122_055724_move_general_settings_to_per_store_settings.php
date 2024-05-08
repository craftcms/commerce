<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m221122_055724_move_general_settings_to_per_store_settings migration
 *
 * This originally appeared as: m230324_080923_move_general_settings_to_per_store_settings migration, but had to be moved (renamed) to run before the multi-store migration.
 */
class m221122_055724_move_general_settings_to_per_store_settings extends Migration
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

        $projectConfig = Craft::$app->getProjectConfig();
        $commerceConfig = $projectConfig->get('commerce.settings');

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

        // set on all rows is safe since we only have one store
        $this->update(Table::STORES, $data);

        // No need to update the project config as we only have one store at this stage and the multi-store migration
        // will handle this.

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
