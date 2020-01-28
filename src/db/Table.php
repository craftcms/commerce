<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\db;

/**
 * This class provides constants for defining Craft Commerce’s database table names. Do not use these in migrations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
abstract class Table
{
    const ADDRESSES = '{{%commerce_addresses}}';
    const CHARGES = '{{%commerce_charges}}';
    const COUNTRIES = '{{%commerce_countries}}';
    const CUSTOMER_DISCOUNTUSES = '{{%commerce_customer_discountuses}}';
    const CUSTOMERS = '{{%commerce_customers}}';
    const CUSTOMERS_ADDRESSES = '{{%commerce_customers_addresses}}';
    const DISCOUNT_CATEGORIES = '{{%commerce_discount_categories}}';
    const DISCOUNT_PURCHASABLES = '{{%commerce_discount_purchasables}}';
    const DISCOUNT_USERGROUPS = '{{%commerce_discount_usergroups}}';
    const DISCOUNTS = '{{%commerce_discounts}}';
    const DONATIONS = '{{%commerce_donations}}';
    const EMAIL_DISCOUNTUSES = '{{%commerce_email_discountuses}}';
    const EMAILS = '{{%commerce_emails}}';
    const GATEWAYS = '{{%commerce_gateways}}';
    const LINEITEMS = '{{%commerce_lineitems}}';
    const LINEITEMSTATUSES = '{{%commerce_lineitemstatuses}}';
    const ORDERADJUSTMENTS = '{{%commerce_orderadjustments}}';
    const ORDERHISTORIES = '{{%commerce_orderhistories}}';
    const ORDERS = '{{%commerce_orders}}';
    const ORDERSTATUS_EMAILS = '{{%commerce_orderstatus_emails}}';
    const ORDERSTATUSES = '{{%commerce_orderstatuses}}';
    const PAYMENTCURRENCIES = '{{%commerce_paymentcurrencies}}';
    const PAYMENTSOURCES = '{{%commerce_paymentsources}}';
    const PLANS = '{{%commerce_plans}}';
    const PRODUCTS = '{{%commerce_products}}';
    const PRODUCTTYPES = '{{%commerce_producttypes}}';
    const PRODUCTTYPES_SHIPPINGCATEGORIES = '{{%commerce_producttypes_shippingcategories}}';
    const PRODUCTTYPES_SITES = '{{%commerce_producttypes_sites}}';
    const PRODUCTTYPES_TAXCATEGORIES = '{{%commerce_producttypes_taxcategories}}';
    const PURCHASABLES = '{{%commerce_purchasables}}';
    const SALE_CATEGORIES = '{{%commerce_sale_categories}}';
    const SALE_PURCHASABLES = '{{%commerce_sale_purchasables}}';
    const SALE_USERGROUPS = '{{%commerce_sale_usergroups}}';
    const SALES = '{{%commerce_sales}}';
    const SHIPPINGCATEGORIES = '{{%commerce_shippingcategories}}';
    const SHIPPINGMETHODS = '{{%commerce_shippingmethods}}';
    const SHIPPINGRULE_CATEGORIES = '{{%commerce_shippingrule_categories}}';
    const SHIPPINGRULES = '{{%commerce_shippingrules}}';
    const SHIPPINGZONE_COUNTRIES = '{{%commerce_shippingzone_countries}}';
    const SHIPPINGZONE_STATES = '{{%commerce_shippingzone_states}}';
    const SHIPPINGZONES = '{{%commerce_shippingzones}}';
    const STATES = '{{%commerce_states}}';
    const SUBSCRIPTIONS = '{{%commerce_subscriptions}}';
    const TAXCATEGORIES = '{{%commerce_taxcategories}}';
    const TAXRATES = '{{%commerce_taxrates}}';
    const TAXZONE_COUNTRIES = '{{%commerce_taxzone_countries}}';
    const TAXZONE_STATES = '{{%commerce_taxzone_states}}';
    const TAXZONES = '{{%commerce_taxzones}}';
    const TRANSACTIONS = '{{%commerce_transactions}}';
    const VARIANTS = '{{%commerce_variants}}';
}
