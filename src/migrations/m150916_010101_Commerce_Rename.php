<?php
namespace Craft;

class m150916_010101_Commerce_Rename extends BaseMigration
{
    public function safeUp()
    {
        MigrationHelper::renameTable('market_addresses', 'commerce_addresses');
        MigrationHelper::renameTable('market_charges', 'commerce_charges');
        MigrationHelper::renameTable('market_countries', 'commerce_countries');
        MigrationHelper::renameTable('market_customer_discountuses', 'commerce_customer_discountuses');
        MigrationHelper::renameTable('market_customers', 'commerce_customers');
        MigrationHelper::renameTable('market_discount_products', 'commerce_discount_products');
        MigrationHelper::renameTable('market_discount_producttypes', 'commerce_discount_producttypes');
        MigrationHelper::renameTable('market_discount_usergroups', 'commerce_discount_usergroups');
        MigrationHelper::renameTable('market_discounts', 'commerce_discounts');
        MigrationHelper::renameTable('market_emails', 'commerce_emails');
        MigrationHelper::renameTable('market_lineitems', 'commerce_lineitems');
        MigrationHelper::renameTable('market_orderadjustments', 'commerce_orderadjustments');
        MigrationHelper::renameTable('market_orderhistories', 'commerce_orderhistories');
        MigrationHelper::renameTable('market_orders', 'commerce_orders');
        MigrationHelper::renameTable('market_ordersettings', 'commerce_ordersettings');
        MigrationHelper::renameTable('market_orderstatus_emails', 'commerce_orderstatus_emails');
        MigrationHelper::renameTable('market_orderstatuses', 'commerce_orderstatuses');
        MigrationHelper::renameTable('market_paymentmethods', 'commerce_paymentmethods');
        MigrationHelper::renameTable('market_products', 'commerce_products');
        MigrationHelper::renameTable('market_producttypes', 'commerce_producttypes');
        MigrationHelper::renameTable('market_purchasables', 'commerce_purchasables');
        MigrationHelper::renameTable('market_sale_products', 'commerce_sale_products');
        MigrationHelper::renameTable('market_sale_producttypes', 'commerce_sale_producttypes');
        MigrationHelper::renameTable('market_sale_usergroups', 'commerce_sale_usergroups');
        MigrationHelper::renameTable('market_sales', 'commerce_sales');
        MigrationHelper::renameTable('market_shippingmethods', 'commerce_shippingmethods');
        MigrationHelper::renameTable('market_shippingrules', 'commerce_shippingrules');
        MigrationHelper::renameTable('market_states', 'commerce_states');
        MigrationHelper::renameTable('market_taxcategories', 'commerce_taxcategories');
        MigrationHelper::renameTable('market_taxrates', 'commerce_taxrates');
        MigrationHelper::renameTable('market_taxzone_countries', 'commerce_taxzone_countries');
        MigrationHelper::renameTable('market_taxzone_states', 'commerce_taxzone_states');
        MigrationHelper::renameTable('market_taxzones', 'commerce_taxzones');
        MigrationHelper::renameTable('market_transactions', 'commerce_transactions');
        MigrationHelper::renameTable('market_variants', 'commerce_variants');

        craft()->db->createCommand()->update('elements', ['type' => 'Commerce_Product'], 'type=:elementType', [':elementType' => 'Market_Product']);
        craft()->db->createCommand()->update('elements', ['type' => 'Commerce_Order'], 'type=:elementType', [':elementType' => 'Market_Order']);
        craft()->db->createCommand()->update('elements', ['type' => 'Commerce_Variant'], 'type=:elementType', [':elementType' => 'Market_Variant']);

        craft()->db->createCommand()->update('fields', ['type' => 'Commerce_Products'], 'type=:fieldType', [':fieldType' => 'Market_Products']);
        craft()->db->createCommand()->update('fields', ['type' => 'Commerce_Customer'], 'type=:fieldType', [':fieldType' => 'Market_Customer']);

        craft()->db->createCommand()->delete('plugins', "class = 'Market'");

        return true;
    }
}
