<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\db;

/**
 * This class provides public constants for defining Craft Commerce’s database table names. Do not use these in migrations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
abstract class Table
{
    public const COUPONS = '{{%commerce_coupons}}';
    public const CHARGES = '{{%commerce_charges}}';
    public const CUSTOMER_DISCOUNTUSES = '{{%commerce_customer_discountuses}}';
    public const CUSTOMERS = '{{%commerce_customers}}';
    public const DISCOUNT_CATEGORIES = '{{%commerce_discount_categories}}';
    public const DISCOUNT_PURCHASABLES = '{{%commerce_discount_purchasables}}';
    public const DISCOUNTS = '{{%commerce_discounts}}';
    public const DONATIONS = '{{%commerce_donations}}';
    public const EMAIL_DISCOUNTUSES = '{{%commerce_email_discountuses}}';
    public const EMAILS = '{{%commerce_emails}}';
    public const GATEWAYS = '{{%commerce_gateways}}';
    public const LINEITEMS = '{{%commerce_lineitems}}';
    public const LINEITEMSTATUSES = '{{%commerce_lineitemstatuses}}';
    public const ORDERADJUSTMENTS = '{{%commerce_orderadjustments}}';
    public const ORDERHISTORIES = '{{%commerce_orderhistories}}';
    public const ORDERS = '{{%commerce_orders}}';
    public const ORDERNOTICES = '{{%commerce_ordernotices}}';
    public const ORDERSTATUS_EMAILS = '{{%commerce_orderstatus_emails}}';
    public const ORDERSTATUSES = '{{%commerce_orderstatuses}}';
    public const PAYMENTCURRENCIES = '{{%commerce_paymentcurrencies}}';
    public const PAYMENTSOURCES = '{{%commerce_paymentsources}}';
    public const PDFS = '{{%commerce_pdfs}}';
    public const PLANS = '{{%commerce_plans}}';
    public const PRODUCTS = '{{%commerce_products}}';
    public const PRODUCTTYPES = '{{%commerce_producttypes}}';
    public const PRODUCTTYPES_SHIPPINGCATEGORIES = '{{%commerce_producttypes_shippingcategories}}';
    public const PRODUCTTYPES_SITES = '{{%commerce_producttypes_sites}}';
    public const PRODUCTTYPES_TAXCATEGORIES = '{{%commerce_producttypes_taxcategories}}';
    public const PURCHASABLES = '{{%commerce_purchasables}}';
    public const SALE_CATEGORIES = '{{%commerce_sale_categories}}';
    public const SALE_PURCHASABLES = '{{%commerce_sale_purchasables}}';
    public const SALE_USERGROUPS = '{{%commerce_sale_usergroups}}';
    public const SALES = '{{%commerce_sales}}';
    public const SHIPPINGCATEGORIES = '{{%commerce_shippingcategories}}';
    public const SHIPPINGMETHODS = '{{%commerce_shippingmethods}}';
    public const SHIPPINGRULE_CATEGORIES = '{{%commerce_shippingrule_categories}}';
    public const SHIPPINGRULES = '{{%commerce_shippingrules}}';
    public const SHIPPINGZONES = '{{%commerce_shippingzones}}';
    public const SUBSCRIPTIONS = '{{%commerce_subscriptions}}';
    public const TAXCATEGORIES = '{{%commerce_taxcategories}}';
    public const TAXRATES = '{{%commerce_taxrates}}';
    public const TAXZONES = '{{%commerce_taxzones}}';
    public const TRANSACTIONS = '{{%commerce_transactions}}';
    public const VARIANTS = '{{%commerce_variants}}';

    /** @since 5.0.0 */
    public const CATALOG_PRICING = '{{%commerce_catalogpricing}}';
    public const CATALOG_PRICING_RULES = '{{%commerce_catalogpricingrules}}';
    public const CATALOG_PRICING_RULES_USERS = '{{%commerce_catalogpricingrules_users}}';
    public const PURCHASABLES_STORES = '{{%commerce_purchasables_stores}}';
    public const SITESTORES = '{{%commerce_site_stores}}';
    public const STORES = '{{%commerce_stores}}';
    public const STORESETTINGS = '{{%commerce_storesettings}}'; // Previously stores table
    public const TRANSFERS = '{{%commerce_transfers}}';
    public const TRANSFERDETAILS = '{{%commerce_transferdetails}}';
    public const INVENTORYITEMS = '{{%commerce_inventoryitems}}';
    public const INVENTORYLOCATIONS = '{{%commerce_inventorylocations}}';
    public const INVENTORYLOCATIONS_STORES = '{{%commerce_inventorylocations_stores}}';
    public const INVENTORYTRANSACTIONS = '{{%commerce_inventorytransactions}}';
}
