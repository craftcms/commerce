<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Donation;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Variant;
use craft\commerce\gateways\Dummy;
use craft\commerce\models\OrderStatus as OrderStatusModel;
use craft\commerce\models\ProductType as ProductTypeModel;
use craft\commerce\models\ProductTypeSite as ProductTypeSiteModel;
use craft\commerce\Plugin;
use craft\commerce\records\Country;
use craft\commerce\records\PaymentCurrency;
use craft\commerce\records\Product as ProductRecord;
use craft\commerce\records\ProductType;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\commerce\records\ShippingCategory;
use craft\commerce\records\ShippingMethod;
use craft\commerce\records\ShippingRule;
use craft\commerce\records\State;
use craft\commerce\records\TaxCategory;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\ActiveRecord;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use craft\records\FieldLayout;
use craft\records\Site;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Install extends Migration
{

    // Private properties
    // =========================================================================

    private $_variantFieldLayoutId;
    private $_productFieldLayoutId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        $this->delete('{{%elementindexsettings}}', ['type' => [Order::class, Product::class, Subscription::class]]);

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables for Craft Commerce
     */
    public function createTables()
    {
        $this->createTable('{{%commerce_addresses}}', [
            'id' => $this->primaryKey(),
            'countryId' => $this->integer(),
            'stateId' => $this->integer(),
            'isStoreLocation' => $this->boolean()->notNull()->defaultValue(false),
            'attention' => $this->string(),
            'title' => $this->string(),
            'firstName' => $this->string(),
            'lastName' => $this->string(),
            'address1' => $this->string(),
            'address2' => $this->string(),
            'city' => $this->string(),
            'zipCode' => $this->string(),
            'phone' => $this->string(),
            'alternativePhone' => $this->string(),
            'businessName' => $this->string(),
            'businessTaxId' => $this->string(),
            'businessId' => $this->string(),
            'stateName' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_countries}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'iso' => $this->string(2)->notNull(),
            'isStateRequired' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customer_discountuses}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_email_discountuses}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'email' => $this->string()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customers}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'primaryBillingAddressId' => $this->integer(),
            'primaryShippingAddressId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customers_addresses}}', [
            'id' => $this->primaryKey(),
            'customerId' => $this->integer()->notNull(),
            'addressId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_purchasables}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_categories}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_usergroups}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discounts}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'code' => $this->string(),
            'perUserLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'perEmailLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalUseLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalUses' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'purchaseTotal' => $this->integer()->notNull()->defaultValue(0),
            'purchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'maxPurchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'baseDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'perItemDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageOffSubject' => $this->enum('percentageOffSubject', ['original', 'discounted'])->notNull(),
            'excludeOnSale' => $this->boolean(),
            'hasFreeShippingForMatchingItems' => $this->boolean(),
            'hasFreeShippingForOrder' => $this->boolean(),
            'allGroups' => $this->boolean(),
            'allPurchasables' => $this->boolean(),
            'allCategories' => $this->boolean(),
            'enabled' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_donations}}', [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'availableForPurchase' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_emails}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'subject' => $this->string()->notNull(),
            'recipientType' => $this->enum('recipientType', ['customer', 'custom'])->defaultValue('custom'),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'enabled' => $this->boolean(),
            'attachPdf' => $this->boolean(),
            'templatePath' => $this->string()->notNull(),
            'pdfTemplatePath' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_gateways}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string()->notNull(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'settings' => $this->text(),
            'paymentType' => $this->enum('paymentType', ['authorize', 'purchase'])->notNull()->defaultValue('purchase'),
            'isFrontendEnabled' => $this->boolean(),
            'sendCartInfo' => $this->boolean(),
            'isArchived' => $this->boolean(),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_lineitems}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'options' => $this->text(),
            'optionsSignature' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->unsigned(),
            'saleAmount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'salePrice' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'weight' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'height' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'length' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'width' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'subtotal' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'total' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'qty' => $this->integer()->notNull()->unsigned(),
            'note' => $this->text(),
            'snapshot' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderadjustments}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'lineItemId' => $this->integer(),
            'type' => $this->string()->notNull(),
            'name' => $this->string(),
            'description' => $this->string(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'included' => $this->boolean(),
            'sourceSnapshot' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderhistories}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'prevStatusId' => $this->integer(),
            'newStatusId' => $this->integer(),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orders}}', [
            'id' => $this->integer()->notNull(),
            'billingAddressId' => $this->integer(),
            'shippingAddressId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'paymentSourceId' => $this->integer(),
            'customerId' => $this->integer(),
            'orderStatusId' => $this->integer(),
            'number' => $this->string(32),
            'reference' => $this->string(),
            'couponCode' => $this->string(),
            'itemTotal' => $this->decimal(14, 4)->defaultValue(0),
            'total' => $this->decimal(14, 4)->defaultValue(0),
            'totalPrice' => $this->decimal(14, 4)->defaultValue(0),
            'totalPaid' => $this->decimal(14, 4)->defaultValue(0),
            'paidStatus' => $this->enum('paidStatus', ['paid', 'partial', 'unpaid']),
            'email' => $this->string(),
            'isCompleted' => $this->boolean(),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'lastIp' => $this->string(),
            'orderLanguage' => $this->string(12)->notNull(),
            'message' => $this->text(),
            'registerUserOnOrderComplete' => $this->boolean(),
            'returnUrl' => $this->string(),
            'cancelUrl' => $this->string(),
            'shippingMethodHandle' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_orderstatus_emails}}', [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer()->notNull(),
            'emailId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderstatuses}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'isArchived' => $this->boolean()->notNull()->defaultValue(false),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_paymentcurrencies}}', [
            'id' => $this->primaryKey(),
            'iso' => $this->string(3)->notNull(),
            'primary' => $this->boolean()->notNull()->defaultValue(false),
            'rate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_paymentsources}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'gatewayId' => $this->integer()->notNull(),
            'token' => $this->string()->notNull(),
            'description' => $this->string(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_plans}}', [
            'id' => $this->primaryKey(),
            'gatewayId' => $this->integer(),
            'planInformationId' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'reference' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull(),
            'planData' => $this->text(),
            'isArchived' => $this->boolean()->notNull(),
            'dateArchived' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'sortOrder' => $this->integer(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_products}}', [
            'id' => $this->integer()->notNull(),
            'typeId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'defaultVariantId' => $this->integer(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean(),
            'availableForPurchase' => $this->boolean(),
            'freeShipping' => $this->boolean(),
            'defaultSku' => $this->string(),
            'defaultPrice' => $this->decimal(14, 4),
            'defaultHeight' => $this->decimal(14, 4),
            'defaultLength' => $this->decimal(14, 4),
            'defaultWidth' => $this->decimal(14, 4),
            'defaultWeight' => $this->decimal(14, 4),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_producttypes}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'variantFieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'hasDimensions' => $this->boolean(),
            'hasVariants' => $this->boolean(),
            'hasVariantTitleField' => $this->boolean(),
            'titleFormat' => $this->string()->notNull(),
            'skuFormat' => $this->string(),
            'descriptionFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_sites}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'uriFormat' => $this->text(),
            'template' => $this->string(500),
            'hasUrls' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_taxcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_purchasables}}', [
            'id' => $this->integer()->notNull(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_sale_purchasables}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sale_categories}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sale_usergroups}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sales}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'apply' => $this->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat'])->notNull(),
            'applyAmount' => $this->decimal(14, 4)->notNull(),
            'allGroups' => $this->boolean(),
            'allPurchasables' => $this->boolean(),
            'allCategories' => $this->boolean(),
            'enabled' => $this->boolean(),
            'ignorePrevious' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingmethods}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'enabled' => $this->boolean(),
            'isLite' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingrule_categories}}', [
            'id' => $this->primaryKey(),
            'shippingRuleId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'condition' => $this->enum('condition', ['allow', 'disallow', 'require'])->notNull(),
            'perItemRate' => $this->decimal(14, 4),
            'weightRate' => $this->decimal(14, 4),
            'percentageRate' => $this->decimal(14, 4),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingrules}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'methodId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'priority' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean(),
            'minQty' => $this->integer()->notNull()->defaultValue(0),
            'maxQty' => $this->integer()->notNull()->defaultValue(0),
            'minTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'baseRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'perItemRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'weightRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'isLite' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzone_countries}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzone_states}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'isCountryBased' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_states}}', [
            'id' => $this->primaryKey(),
            'countryId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'abbreviation' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_subscriptions}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'planId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'orderId' => $this->integer(),
            'reference' => $this->string()->notNull(),
            'subscriptionData' => $this->text(),
            'trialDays' => $this->integer()->notNull(),
            'nextPaymentDate' => $this->dateTime(),
            'isCanceled' => $this->boolean()->notNull(),
            'dateCanceled' => $this->dateTime(),
            'isExpired' => $this->boolean()->notNull(),
            'dateExpired' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxrates}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'isEverywhere' => $this->boolean(),
            'taxCategoryId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'rate' => $this->decimal(14, 10)->notNull(),
            'include' => $this->boolean(),
            'isVat' => $this->boolean(),
            'taxable' => $this->enum('taxable', ['price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price'])->notNull(),
            'isLite' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzone_countries}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzone_states}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'isCountryBased' => $this->boolean(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_transactions}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'parentId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'userId' => $this->integer(),
            'hash' => $this->string(32),
            'type' => $this->enum('type', ['authorize', 'capture', 'purchase', 'refund'])->notNull(),
            'amount' => $this->decimal(14, 4),
            'paymentAmount' => $this->decimal(14, 4),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'paymentRate' => $this->decimal(14, 4),
            'status' => $this->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing'])->notNull(),
            'reference' => $this->string(),
            'code' => $this->string(),
            'message' => $this->text(),
            'note' => $this->mediumText(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_variants}}', [
            'id' => $this->integer()->notNull(),
            'productId' => $this->integer(), // Allow null so we can delete a product THEN the variants.
            'sku' => $this->string()->notNull(),
            'isDefault' => $this->boolean(),
            'price' => $this->decimal(14, 4)->notNull(),
            'sortOrder' => $this->integer(),
            'width' => $this->decimal(14, 4),
            'height' => $this->decimal(14, 4),
            'length' => $this->decimal(14, 4),
            'weight' => $this->decimal(14, 4),
            'stock' => $this->integer()->notNull()->defaultValue(0),
            'hasUnlimitedStock' => $this->boolean(),
            'minQty' => $this->integer(),
            'maxQty' => $this->integer(),
            'deletedWithProduct' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);
    }

    /**
     * Drop the tables
     */
    public function dropTables()
    {
        $this->dropTableIfExists('{{%commerce_addresses}}');
        $this->dropTableIfExists('{{%commerce_countries}}');
        $this->dropTableIfExists('{{%commerce_customer_discountuses}}');
        $this->dropTableIfExists('{{%commerce_email_discountuses}}');
        $this->dropTableIfExists('{{%commerce_customers}}');
        $this->dropTableIfExists('{{%commerce_customers_addresses}}');
        $this->dropTableIfExists('{{%commerce_discount_purchasables}}');
        $this->dropTableIfExists('{{%commerce_discount_categories}}');
        $this->dropTableIfExists('{{%commerce_discount_usergroups}}');
        $this->dropTableIfExists('{{%commerce_discounts}}');
        $this->dropTableIfExists('{{%commerce_donations}}');
        $this->dropTableIfExists('{{%commerce_emails}}');
        $this->dropTableIfExists('{{%commerce_gateways}}');
        $this->dropTableIfExists('{{%commerce_lineitems}}');
        $this->dropTableIfExists('{{%commerce_orderadjustments}}');
        $this->dropTableIfExists('{{%commerce_orderhistories}}');
        $this->dropTableIfExists('{{%commerce_orders}}');
        $this->dropTableIfExists('{{%commerce_orderstatus_emails}}');
        $this->dropTableIfExists('{{%commerce_orderstatuses}}');
        $this->dropTableIfExists('{{%commerce_paymentcurrencies}}');
        $this->dropTableIfExists('{{%commerce_paymentsources}}');
        $this->dropTableIfExists('{{%commerce_plans}}');
        $this->dropTableIfExists('{{%commerce_products}}');
        $this->dropTableIfExists('{{%commerce_producttypes}}');
        $this->dropTableIfExists('{{%commerce_producttypes_sites}}');
        $this->dropTableIfExists('{{%commerce_producttypes_shippingcategories}}');
        $this->dropTableIfExists('{{%commerce_producttypes_taxcategories}}');
        $this->dropTableIfExists('{{%commerce_purchasables}}');
        $this->dropTableIfExists('{{%commerce_sale_purchasables}}');
        $this->dropTableIfExists('{{%commerce_sale_categories}}');
        $this->dropTableIfExists('{{%commerce_sale_usergroups}}');
        $this->dropTableIfExists('{{%commerce_sales}}');
        $this->dropTableIfExists('{{%commerce_shippingcategories}}');
        $this->dropTableIfExists('{{%commerce_shippingmethods}}');
        $this->dropTableIfExists('{{%commerce_shippingrule_categories}}');
        $this->dropTableIfExists('{{%commerce_shippingrules}}');
        $this->dropTableIfExists('{{%commerce_shippingzone_countries}}');
        $this->dropTableIfExists('{{%commerce_shippingzone_states}}');
        $this->dropTableIfExists('{{%commerce_shippingzones}}');
        $this->dropTableIfExists('{{%commerce_states}}');
        $this->dropTableIfExists('{{%commerce_subscriptions}}');
        $this->dropTableIfExists('{{%commerce_taxcategories}}');
        $this->dropTableIfExists('{{%commerce_taxrates}}');
        $this->dropTableIfExists('{{%commerce_taxzone_countries}}');
        $this->dropTableIfExists('{{%commerce_taxzone_states}}');
        $this->dropTableIfExists('{{%commerce_taxzones}}');
        $this->dropTableIfExists('{{%commerce_transactions}}');
        $this->dropTableIfExists('{{%commerce_variants}}');

        return null;
    }

    /**
     * Deletes the project config entry.
     */
    public function dropProjectConfig()
    {
        Craft::$app->projectConfig->remove('commerce');
    }

    /**
     * Creates the indexes.
     */
    public function createIndexes()
    {
        $this->createIndex(null, '{{%commerce_addresses}}', 'countryId', false);
        $this->createIndex(null, '{{%commerce_addresses}}', 'stateId', false);
        $this->createIndex(null, '{{%commerce_countries}}', 'name', true);
        $this->createIndex(null, '{{%commerce_countries}}', 'iso', true);
        $this->createIndex(null, '{{%commerce_email_discountuses}}', ['email', 'discountId'], true);
        $this->createIndex(null, '{{%commerce_email_discountuses}}', ['discountId'], false);
        $this->createIndex(null, '{{%commerce_customer_discountuses}}', ['customerId', 'discountId'], true);
        $this->createIndex(null, '{{%commerce_customer_discountuses}}', 'discountId', false);
        $this->createIndex(null, '{{%commerce_customers}}', 'userId', false);
        $this->createIndex(null, '{{%commerce_customers}}', 'primaryBillingAddressId', false);
        $this->createIndex(null, '{{%commerce_customers}}', 'primaryShippingAddressId', false);
        $this->createIndex(null, '{{%commerce_customers_addresses}}', ['customerId', 'addressId'], true);
        $this->createIndex(null, '{{%commerce_discount_purchasables}}', ['discountId', 'purchasableId'], true);
        $this->createIndex(null, '{{%commerce_discount_purchasables}}', 'purchasableId', false);
        $this->createIndex(null, '{{%commerce_discount_categories}}', ['discountId', 'categoryId'], true);
        $this->createIndex(null, '{{%commerce_discount_categories}}', 'categoryId', false);
        $this->createIndex(null, '{{%commerce_discount_usergroups}}', ['discountId', 'userGroupId'], true);
        $this->createIndex(null, '{{%commerce_discount_usergroups}}', 'userGroupId', false);
        $this->createIndex(null, '{{%commerce_discounts}}', 'code', true);
        $this->createIndex(null, '{{%commerce_discounts}}', 'dateFrom', false);
        $this->createIndex(null, '{{%commerce_discounts}}', 'dateTo', false);
        $this->createIndex(null, '{{%commerce_gateways}}', 'handle', false);
        $this->createIndex(null, '{{%commerce_gateways}}', 'isArchived', false);
        $this->createIndex(null, '{{%commerce_lineitems}}', ['orderId', 'purchasableId', 'optionsSignature'], true);
        $this->createIndex(null, '{{%commerce_lineitems}}', 'purchasableId', false);
        $this->createIndex(null, '{{%commerce_lineitems}}', 'taxCategoryId', false);
        $this->createIndex(null, '{{%commerce_lineitems}}', 'shippingCategoryId', false);
        $this->createIndex(null, '{{%commerce_orderadjustments}}', 'orderId', false);
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'orderId', false);
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'prevStatusId', false);
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'newStatusId', false);
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'customerId', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'number', true);
        $this->createIndex(null, '{{%commerce_orders}}', 'reference', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'billingAddressId', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'shippingAddressId', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'gatewayId', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'customerId', false);
        $this->createIndex(null, '{{%commerce_orders}}', 'orderStatusId', false);
        $this->createIndex(null, '{{%commerce_orderstatuses}}', 'isArchived', false);
        $this->createIndex(null, '{{%commerce_orderstatus_emails}}', 'orderStatusId', false);
        $this->createIndex(null, '{{%commerce_orderstatus_emails}}', 'emailId', false);
        $this->createIndex(null, '{{%commerce_paymentcurrencies}}', 'iso', true);
        $this->createIndex(null, '{{%commerce_plans}}', 'gatewayId', false);
        $this->createIndex(null, '{{%commerce_plans}}', 'handle', true);
        $this->createIndex(null, '{{%commerce_plans}}', 'reference', false);
        $this->createIndex(null, '{{%commerce_products}}', 'typeId', false);
        $this->createIndex(null, '{{%commerce_products}}', 'postDate', false);
        $this->createIndex(null, '{{%commerce_products}}', 'expiryDate', false);
        $this->createIndex(null, '{{%commerce_products}}', 'taxCategoryId', false);
        $this->createIndex(null, '{{%commerce_products}}', 'shippingCategoryId', false);
        $this->createIndex(null, '{{%commerce_producttypes}}', 'handle', true);
        $this->createIndex(null, '{{%commerce_producttypes}}', 'fieldLayoutId', false);
        $this->createIndex(null, '{{%commerce_producttypes}}', 'variantFieldLayoutId', false);
        $this->createIndex(null, '{{%commerce_producttypes_sites}}', ['productTypeId', 'siteId'], true);
        $this->createIndex(null, '{{%commerce_producttypes_sites}}', 'siteId', false);
        $this->createIndex(null, '{{%commerce_producttypes_shippingcategories}}', ['productTypeId', 'shippingCategoryId'], true);
        $this->createIndex(null, '{{%commerce_producttypes_shippingcategories}}', 'shippingCategoryId', false);
        $this->createIndex(null, '{{%commerce_producttypes_taxcategories}}', ['productTypeId', 'taxCategoryId'], true);
        $this->createIndex(null, '{{%commerce_producttypes_taxcategories}}', 'taxCategoryId', false);
        $this->createIndex(null, '{{%commerce_purchasables}}', 'sku', false); // Application layer enforces unique
        $this->createIndex(null, '{{%commerce_sale_purchasables}}', ['saleId', 'purchasableId'], true);
        $this->createIndex(null, '{{%commerce_sale_purchasables}}', 'purchasableId', false);
        $this->createIndex(null, '{{%commerce_sale_categories}}', ['saleId', 'categoryId'], true);
        $this->createIndex(null, '{{%commerce_sale_categories}}', 'categoryId', false);
        $this->createIndex(null, '{{%commerce_sale_usergroups}}', ['saleId', 'userGroupId'], true);
        $this->createIndex(null, '{{%commerce_sale_usergroups}}', 'userGroupId', false);
        $this->createIndex(null, '{{%commerce_shippingcategories}}', 'handle', true);
        $this->createIndex(null, '{{%commerce_shippingmethods}}', 'name', true);
        $this->createIndex(null, '{{%commerce_shippingrule_categories}}', 'shippingRuleId', false);
        $this->createIndex(null, '{{%commerce_shippingrule_categories}}', 'shippingCategoryId', false);
        $this->createIndex(null, '{{%commerce_shippingrules}}', 'name', false);
        $this->createIndex(null, '{{%commerce_shippingrules}}', 'methodId', false);
        $this->createIndex(null, '{{%commerce_shippingrules}}', 'shippingZoneId', false);
        $this->createIndex(null, '{{%commerce_shippingzone_countries}}', ['shippingZoneId', 'countryId'], true);
        $this->createIndex(null, '{{%commerce_shippingzone_countries}}', 'shippingZoneId', false);
        $this->createIndex(null, '{{%commerce_shippingzone_countries}}', 'countryId', false);
        $this->createIndex(null, '{{%commerce_shippingzone_states}}', ['shippingZoneId', 'stateId'], true);
        $this->createIndex(null, '{{%commerce_shippingzone_states}}', 'shippingZoneId', false);
        $this->createIndex(null, '{{%commerce_shippingzone_states}}', 'stateId', false);
        $this->createIndex(null, '{{%commerce_shippingzones}}', 'name', true);
        $this->createIndex(null, '{{%commerce_states}}', 'countryId', false);
        $this->createIndex(null, '{{%commerce_states}}', ['countryId', 'abbreviation'], true);
        $this->createIndex(null, '{{%commerce_states}}', ['countryId', 'name'], true);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'userId', false);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'planId', false);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'gatewayId', false);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'reference', true);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'nextPaymentDate', false);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'dateCreated', false);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'dateExpired', false);
        $this->createIndex(null, '{{%commerce_taxcategories}}', 'handle', true);
        $this->createIndex(null, '{{%commerce_taxrates}}', 'taxZoneId', false);
        $this->createIndex(null, '{{%commerce_taxrates}}', 'taxCategoryId', false);
        $this->createIndex(null, '{{%commerce_taxzone_countries}}', ['taxZoneId', 'countryId'], true);
        $this->createIndex(null, '{{%commerce_taxzone_countries}}', 'taxZoneId', false);
        $this->createIndex(null, '{{%commerce_taxzone_countries}}', 'countryId', false);
        $this->createIndex(null, '{{%commerce_taxzone_states}}', ['taxZoneId', 'stateId'], true);
        $this->createIndex(null, '{{%commerce_taxzone_states}}', 'taxZoneId', false);
        $this->createIndex(null, '{{%commerce_taxzone_states}}', 'stateId', false);
        $this->createIndex(null, '{{%commerce_taxzones}}', 'name', true);
        $this->createIndex(null, '{{%commerce_transactions}}', 'parentId', false);
        $this->createIndex(null, '{{%commerce_transactions}}', 'gatewayId', false);
        $this->createIndex(null, '{{%commerce_transactions}}', 'orderId', false);
        $this->createIndex(null, '{{%commerce_transactions}}', 'userId', false);
        $this->createIndex(null, '{{%commerce_variants}}', 'sku', false);
        $this->createIndex(null, '{{%commerce_variants}}', 'productId', false);
    }

    /**
     * Adds the foreign keys.
     */
    public function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%commerce_addresses}}', ['countryId'], '{{%commerce_countries}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_addresses}}', ['stateId'], '{{%commerce_states}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customer_discountuses}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_customer_discountuses}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_email_discountuses}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryBillingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryShippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers_addresses}}', ['addressId'], '{{%commerce_addresses}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_customers_addresses}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_purchasables}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_purchasables}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_categories}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_categories}}', ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_usergroups}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_discount_usergroups}}', ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_donations}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_lineitems}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_lineitems}}', ['purchasableId'], '{{%elements}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_lineitems}}', ['shippingCategoryId'], '{{%commerce_shippingcategories}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_lineitems}}', ['taxCategoryId'], '{{%commerce_taxcategories}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderadjustments}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['newStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['prevStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['billingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['orderStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['paymentSourceId'], '{{%commerce_paymentsources}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['shippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orderstatus_emails}}', ['emailId'], '{{%commerce_emails}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderstatus_emails}}', ['orderStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_paymentsources}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_paymentsources}}', ['userId'], '{{%users}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_plans}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_plans}}', ['planInformationId'], '{{%elements}}', 'id', 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_products}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_products}}', ['shippingCategoryId'], '{{%commerce_shippingcategories}}', ['id']);
        $this->addForeignKey(null, '{{%commerce_products}}', ['taxCategoryId'], '{{%commerce_taxcategories}}', ['id']);
        $this->addForeignKey(null, '{{%commerce_products}}', ['typeId'], '{{%commerce_producttypes}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_producttypes}}', ['variantFieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_producttypes_sites}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes_sites}}', ['productTypeId'], '{{%commerce_producttypes}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes_shippingcategories}}', ['shippingCategoryId'], '{{%commerce_shippingcategories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes_shippingcategories}}', ['productTypeId'], '{{%commerce_producttypes}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes_taxcategories}}', ['productTypeId'], '{{%commerce_producttypes}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_producttypes_taxcategories}}', ['taxCategoryId'], '{{%commerce_taxcategories}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_purchasables}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_purchasables}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_purchasables}}', ['saleId'], '{{%commerce_sales}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_categories}}', ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_categories}}', ['saleId'], '{{%commerce_sales}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_usergroups}}', ['saleId'], '{{%commerce_sales}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_sale_usergroups}}', ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingrule_categories}}', ['shippingCategoryId'], '{{%commerce_shippingcategories}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingrule_categories}}', ['shippingRuleId'], '{{%commerce_shippingrules}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingrules}}', ['methodId'], '{{%commerce_shippingmethods}}', ['id']);
        $this->addForeignKey(null, '{{%commerce_shippingrules}}', ['shippingZoneId'], '{{%commerce_shippingzones}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_shippingzone_countries}}', ['countryId'], '{{%commerce_countries}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingzone_countries}}', ['shippingZoneId'], '{{%commerce_shippingzones}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingzone_states}}', ['shippingZoneId'], '{{%commerce_shippingzones}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_shippingzone_states}}', ['stateId'], '{{%commerce_states}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_states}}', ['countryId'], '{{%commerce_countries}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['userId'], '{{%users}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['planId'], '{{%commerce_plans}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_taxrates}}', ['taxCategoryId'], '{{%commerce_taxcategories}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_taxrates}}', ['taxZoneId'], '{{%commerce_taxzones}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_taxzone_countries}}', ['countryId'], '{{%commerce_countries}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_taxzone_countries}}', ['taxZoneId'], '{{%commerce_taxzones}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_taxzone_states}}', ['stateId'], '{{%commerce_states}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_taxzone_states}}', ['taxZoneId'], '{{%commerce_taxzones}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transactions}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transactions}}', ['parentId'], '{{%commerce_transactions}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transactions}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transactions}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_variants}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_variants}}', ['productId'], '{{%commerce_products}}', ['id'], 'SET NULL'); // Allow null so we can delete a product THEN the variants.
    }

    /**
     * Removes the foreign keys.
     */
    public function dropForeignKeys()
    {
        if ($this->_tableExists('{{%commerce_addresses}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_addresses}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_addresses}}', $this);
        }
        if ($this->_tableExists('{{%commerce_customer_discountuses}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_customer_discountuses}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customer_discountuses}}', $this);
        }
        if ($this->_tableExists('{{%commerce_email_discountuses}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_email_discountuses}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_email_discountuses}}', $this);
        }
        if ($this->_tableExists('{{%commerce_customers}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_customers}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customers}}', $this);
        }
        if ($this->_tableExists('{{%commerce_customers_addresses}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_customers_addresses}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customers_addresses}}', $this);
        }
        if ($this->_tableExists('{{%commerce_discount_purchasables}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_discount_purchasables}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_purchasables}}', $this);
        }
        if ($this->_tableExists('{{%commerce_discount_categories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_discount_categories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_categories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_discount_usergroups}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_discount_usergroups}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_usergroups}}', $this);
        }
        if ($this->_tableExists('{{%commerce_donations}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_donations}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_donations}}', $this);
        }
        if ($this->_tableExists('{{%commerce_lineitems}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_lineitems}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_lineitems}}', $this);
        }
        if ($this->_tableExists('{{%commerce_orderadjustments}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_orderadjustments}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderadjustments}}', $this);
        }
        if ($this->_tableExists('{{%commerce_orderhistories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_orderhistories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderhistories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_orders}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_orders}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orders}}', $this);
        }
        if ($this->_tableExists('{{%commerce_orderstatus_emails}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_orderstatus_emails}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderstatus_emails}}', $this);
        }
        if ($this->_tableExists('{{%commerce_paymentsources}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_paymentsources}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_paymentsources}}', $this);
        }
        if ($this->_tableExists('{{%commerce_plans}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_plans}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_plans}}', $this);
        }
        if ($this->_tableExists('{{%commerce_products}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_products}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_products}}', $this);
        }
        if ($this->_tableExists('{{%commerce_producttypes}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_producttypes}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes}}', $this);
        }
        if ($this->_tableExists('{{%commerce_producttypes_sites}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_producttypes_sites}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_sites}}', $this);
        }
        if ($this->_tableExists('{{%commerce_producttypes_shippingcategories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_producttypes_shippingcategories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_shippingcategories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_producttypes_taxcategories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_producttypes_taxcategories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_taxcategories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_purchasables}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_purchasables}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_purchasables}}', $this);
        }
        if ($this->_tableExists('{{%commerce_sale_purchasables}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_sale_purchasables}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_purchasables}}', $this);
        }
        if ($this->_tableExists('{{%commerce_sale_categories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_sale_categories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_categories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_sale_usergroups}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_sale_usergroups}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_usergroups}}', $this);
        }
        if ($this->_tableExists('{{%commerce_shippingrule_categories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_shippingrule_categories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingrule_categories}}', $this);
        }
        if ($this->_tableExists('{{%commerce_shippingrules}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_shippingrules}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingrules}}', $this);
        }
        if ($this->_tableExists('{{%commerce_shippingzone_countries}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_shippingzone_countries}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingzone_countries}}', $this);
        }
        if ($this->_tableExists('{{%commerce_shippingzone_states}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_shippingzone_states}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingzone_states}}', $this);
        }
        if ($this->_tableExists('{{%commerce_states}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_states}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_states}}', $this);
        }
        if ($this->_tableExists('{{%commerce_subscriptions}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_subscriptions}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_subscriptions}}', $this);
        }
        if ($this->_tableExists('{{%commerce_taxrates}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_taxrates}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxrates}}', $this);
        }
        if ($this->_tableExists('{{%commerce_taxzone_countries}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_taxzone_countries}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxzone_countries}}', $this);
        }
        if ($this->_tableExists('{{%commerce_taxzone_states}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_taxzone_states}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxzone_states}}', $this);
        }
        if ($this->_tableExists('{{%commerce_transactions}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_transactions}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_transactions}}', $this);
        }
        if ($this->_tableExists('{{%commerce_variants}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%commerce_variants}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_variants}}', $this);
        }
    }

    /**
     * Insert the default data.
     */
    public function insertDefaultData()
    {
        // The following defaults are not stored in the project config.
        $this->_defaultCountries();
        $this->_defaultStates();
        $this->_defaultCurrency();
        $this->_defaultShippingMethod();
        $this->_defaultTaxCategories();
        $this->_defaultShippingCategories();
        $this->_defaultDonationPurchasable();

        // Don't make the same config changes twice
        $installed = (Craft::$app->projectConfig->get('plugins.commerce', true) !== null);
        $configExists = (Craft::$app->projectConfig->get('commerce', true) !== null);

        if (!$installed && !$configExists) {
            $this->_defaultOrderSettings();
            $this->_defaultProductTypes();
            $this->_defaultProducts(); // Not in project config, but dependant on demo product type
            $this->_defaultGateways();
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Insert default countries data.
     */
    private function _defaultCountries()
    {
        $countries = [
            ['AD', 'Andorra'],
            ['AE', 'United Arab Emirates'],
            ['AF', 'Afghanistan'],
            ['AG', 'Antigua and Barbuda'],
            ['AI', 'Anguilla'],
            ['AL', 'Albania'],
            ['AM', 'Armenia'],
            ['AO', 'Angola'],
            ['AQ', 'Antarctica'],
            ['AR', 'Argentina'],
            ['AS', 'American Samoa'],
            ['AT', 'Austria'],
            ['AU', 'Australia'],
            ['AW', 'Aruba'],
            ['AX', 'Aland Islands'],
            ['AZ', 'Azerbaijan'],
            ['BA', 'Bosnia and Herzegovina'],
            ['BB', 'Barbados'],
            ['BD', 'Bangladesh'],
            ['BE', 'Belgium'],
            ['BF', 'Burkina Faso'],
            ['BG', 'Bulgaria'],
            ['BH', 'Bahrain'],
            ['BI', 'Burundi'],
            ['BJ', 'Benin'],
            ['BL', 'Saint Barthelemy'],
            ['BM', 'Bermuda'],
            ['BN', 'Brunei Darussalam'],
            ['BO', 'Bolivia'],
            ['BQ', 'Bonaire, Sint Eustatius and Saba'],
            ['BR', 'Brazil'],
            ['BS', 'Bahamas'],
            ['BT', 'Bhutan'],
            ['BV', 'Bouvet Island'],
            ['BW', 'Botswana'],
            ['BY', 'Belarus'],
            ['BZ', 'Belize'],
            ['CA', 'Canada'],
            ['CC', 'Cocos (Keeling) Islands'],
            ['CD', 'Democratic Republic of Congo'],
            ['CF', 'Central African Republic'],
            ['CG', 'Congo'],
            ['CH', 'Switzerland'],
            ['CI', 'Ivory Coast'],
            ['CK', 'Cook Islands'],
            ['CL', 'Chile'],
            ['CM', 'Cameroon'],
            ['CN', 'China'],
            ['CO', 'Colombia'],
            ['CR', 'Costa Rica'],
            ['CU', 'Cuba'],
            ['CV', 'Cape Verde'],
            ['CW', 'Curacao'],
            ['CX', 'Christmas Island'],
            ['CY', 'Cyprus'],
            ['CZ', 'Czech Republic'],
            ['DE', 'Germany'],
            ['DJ', 'Djibouti'],
            ['DK', 'Denmark'],
            ['DM', 'Dominica'],
            ['DO', 'Dominican Republic'],
            ['DZ', 'Algeria'],
            ['EC', 'Ecuador'],
            ['EE', 'Estonia'],
            ['EG', 'Egypt'],
            ['EH', 'Western Sahara'],
            ['ER', 'Eritrea'],
            ['ES', 'Spain'],
            ['ET', 'Ethiopia'],
            ['FI', 'Finland'],
            ['FJ', 'Fiji'],
            ['FK', 'Falkland Islands (Malvinas)'],
            ['FM', 'Micronesia'],
            ['FO', 'Faroe Islands'],
            ['FR', 'France'],
            ['GA', 'Gabon'],
            ['GB', 'United Kingdom'],
            ['GD', 'Grenada'],
            ['GE', 'Georgia'],
            ['GF', 'French Guiana'],
            ['GG', 'Guernsey'],
            ['GH', 'Ghana'],
            ['GI', 'Gibraltar'],
            ['GL', 'Greenland'],
            ['GM', 'Gambia'],
            ['GN', 'Guinea'],
            ['GP', 'Guadeloupe'],
            ['GQ', 'Equatorial Guinea'],
            ['GR', 'Greece'],
            ['GS', 'S. Georgia and S. Sandwich Isls.'],
            ['GT', 'Guatemala'],
            ['GU', 'Guam'],
            ['GW', 'Guinea-Bissau'],
            ['GY', 'Guyana'],
            ['HK', 'Hong Kong'],
            ['HM', 'Heard and McDonald Islands'],
            ['HN', 'Honduras'],
            ['HR', 'Croatia (Hrvatska)'],
            ['HT', 'Haiti'],
            ['HU', 'Hungary'],
            ['ID', 'Indonesia'],
            ['IE', 'Ireland'],
            ['IL', 'Israel'],
            ['IM', 'Isle Of Man'],
            ['IN', 'India'],
            ['IO', 'British Indian Ocean Territory'],
            ['IQ', 'Iraq'],
            ['IR', 'Iran'],
            ['IS', 'Iceland'],
            ['IT', 'Italy'],
            ['JE', 'Jersey'],
            ['JM', 'Jamaica'],
            ['JO', 'Jordan'],
            ['JP', 'Japan'],
            ['KE', 'Kenya'],
            ['KG', 'Kyrgyzstan'],
            ['KH', 'Cambodia'],
            ['KI', 'Kiribati'],
            ['KM', 'Comoros'],
            ['KN', 'Saint Kitts and Nevis'],
            ['KP', 'Korea (North)'],
            ['KR', 'Korea (South)'],
            ['KW', 'Kuwait'],
            ['KY', 'Cayman Islands'],
            ['KZ', 'Kazakhstan'],
            ['LA', 'Laos'],
            ['LB', 'Lebanon'],
            ['LC', 'Saint Lucia'],
            ['LI', 'Liechtenstein'],
            ['LK', 'Sri Lanka'],
            ['LR', 'Liberia'],
            ['LS', 'Lesotho'],
            ['LT', 'Lithuania'],
            ['LU', 'Luxembourg'],
            ['LV', 'Latvia'],
            ['LY', 'Libya'],
            ['MA', 'Morocco'],
            ['MC', 'Monaco'],
            ['MD', 'Moldova'],
            ['ME', 'Montenegro'],
            ['MF', 'Saint Martin (French part)'],
            ['MG', 'Madagascar'],
            ['MH', 'Marshall Islands'],
            ['MK', 'Macedonia'],
            ['ML', 'Mali'],
            ['MM', 'Burma (Myanmar)'],
            ['MN', 'Mongolia'],
            ['MO', 'Macau'],
            ['MP', 'Northern Mariana Islands'],
            ['MQ', 'Martinique'],
            ['MR', 'Mauritania'],
            ['MS', 'Montserrat'],
            ['MT', 'Malta'],
            ['MU', 'Mauritius'],
            ['MV', 'Maldives'],
            ['MW', 'Malawi'],
            ['MX', 'Mexico'],
            ['MY', 'Malaysia'],
            ['MZ', 'Mozambique'],
            ['NA', 'Namibia'],
            ['NC', 'New Caledonia'],
            ['NE', 'Niger'],
            ['NF', 'Norfolk Island'],
            ['NG', 'Nigeria'],
            ['NI', 'Nicaragua'],
            ['NL', 'Netherlands'],
            ['NO', 'Norway'],
            ['NP', 'Nepal'],
            ['NR', 'Nauru'],
            ['NU', 'Niue'],
            ['NZ', 'New Zealand'],
            ['OM', 'Oman'],
            ['PA', 'Panama'],
            ['PE', 'Peru'],
            ['PF', 'French Polynesia'],
            ['PG', 'Papua New Guinea'],
            ['PH', 'Philippines'],
            ['PK', 'Pakistan'],
            ['PL', 'Poland'],
            ['PM', 'St. Pierre and Miquelon'],
            ['PN', 'Pitcairn'],
            ['PR', 'Puerto Rico'],
            ['PS', 'Palestinian Territory, Occupied'],
            ['PT', 'Portugal'],
            ['PW', 'Palau'],
            ['PY', 'Paraguay'],
            ['QA', 'Qatar'],
            ['RE', 'Reunion'],
            ['RO', 'Romania'],
            ['RS', 'Republic of Serbia'],
            ['RU', 'Russia'],
            ['RW', 'Rwanda'],
            ['SA', 'Saudi Arabia'],
            ['SB', 'Solomon Islands'],
            ['SC', 'Seychelles'],
            ['SD', 'Sudan'],
            ['SE', 'Sweden'],
            ['SG', 'Singapore'],
            ['SH', 'St. Helena'],
            ['SI', 'Slovenia'],
            ['SJ', 'Svalbard and Jan Mayen Islands'],
            ['SK', 'Slovak Republic'],
            ['SL', 'Sierra Leone'],
            ['SM', 'San Marino'],
            ['SN', 'Senegal'],
            ['SO', 'Somalia'],
            ['SR', 'Suriname'],
            ['SS', 'South Sudan'],
            ['ST', 'Sao Tome and Principe'],
            ['SV', 'El Salvador'],
            ['SX', 'Sint Maarten (Dutch part)'],
            ['SY', 'Syria'],
            ['SZ', 'Swaziland'],
            ['TC', 'Turks and Caicos Islands'],
            ['TD', 'Chad'],
            ['TF', 'French Southern Territories'],
            ['TG', 'Togo'],
            ['TH', 'Thailand'],
            ['TJ', 'Tajikistan'],
            ['TK', 'Tokelau'],
            ['TL', 'Timor-Leste'],
            ['TM', 'Turkmenistan'],
            ['TN', 'Tunisia'],
            ['TO', 'Tonga'],
            ['TR', 'Turkey'],
            ['TT', 'Trinidad and Tobago'],
            ['TV', 'Tuvalu'],
            ['TW', 'Taiwan'],
            ['TZ', 'Tanzania'],
            ['UA', 'Ukraine'],
            ['UG', 'Uganda'],
            ['UM', 'United States Minor Outlying Islands'],
            ['US', 'United States'],
            ['UY', 'Uruguay'],
            ['UZ', 'Uzbekistan'],
            ['VA', 'Vatican City State (Holy See)'],
            ['VC', 'Saint Vincent and the Grenadines'],
            ['VE', 'Venezuela'],
            ['VG', 'Virgin Islands (British)'],
            ['VI', 'Virgin Islands (U.S.)'],
            ['VN', 'Viet Nam'],
            ['VU', 'Vanuatu'],
            ['WF', 'Wallis and Futuna Islands'],
            ['WS', 'Samoa'],
            ['YE', 'Yemen'],
            ['YT', 'Mayotte'],
            ['ZA', 'South Africa'],
            ['ZM', 'Zambia'],
            ['ZW', 'Zimbabwe'],
        ];

        $this->batchInsert('{{%commerce_countries}}', ['iso', 'name'], $countries);
    }

    /**
     * Add default States.
     */
    private function _defaultStates()
    {
        $states = [
            'AU' => [
                'ACT' => 'Australian Capital Territory',
                'NSW' => 'New South Wales',
                'NT' => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA' => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA' => 'Western Australia',
            ],
            'CA' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NT' => 'Northwest Territories',
                'NS' => 'Nova Scotia',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],
            'US' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'DC' => 'District of Columbia',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
            ],
        ];

        /** @var ActiveRecord $countries */
        $countries = Country::find()->where(['in', 'iso', array_keys($states)])->all();
        $code2id = [];
        foreach ($countries as $record) {
            $code2id[$record->iso] = $record->id;
        }

        $rows = [];
        foreach ($states as $iso => $list) {
            foreach ($list as $abbr => $name) {
                $rows[] = [$code2id[$iso], $abbr, $name];
            }
        }

        $this->batchInsert(State::tableName(), ['countryId', 'abbreviation', 'name'], $rows);
    }

    /**
     * Make USD the default currency.
     */
    private function _defaultCurrency()
    {
        $data = [
            'iso' => 'USD',
            'rate' => 1,
            'primary' => true
        ];
        $this->insert(PaymentCurrency::tableName(), $data);
    }

    /**
     * Add a default shipping method and rule.
     */
    private function _defaultShippingMethod()
    {
        $data = [
            'name' => 'Free Shipping',
            'handle' => 'freeShipping',
            'enabled' => true
        ];
        $this->insert(ShippingMethod::tableName(), $data);

        $data = [
            'methodId' => $this->db->getLastInsertID(ShippingMethod::tableName()),
            'description' => 'All Countries, free shipping.',
            'name' => 'Free Everywhere',
            'enabled' => true
        ];
        $this->insert(ShippingRule::tableName(), $data);
    }

    /**
     * Add a default Tax category.
     */
    private function _defaultTaxCategories()
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true
        ];
        $this->insert(TaxCategory::tableName(), $data);
    }

    /**
     * Add a default shipping category.
     */
    private function _defaultShippingCategories()
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true
        ];
        $this->insert(ShippingCategory::tableName(), $data);
    }

    /**
     * Add the donation purchasable
     */
    public function _defaultDonationPurchasable()
    {
        $donation = new Donation();
        $donation->sku = 'DONATION-CC3';
        $donation->availableForPurchase = false;
        Craft::$app->getElements()->saveElement($donation);
    }

    /**
     * Add the default order settings.
     *
     * @throws \Exception
     */
    private function _defaultOrderSettings()
    {
        $this->insert(FieldLayout::tableName(), ['type' => Order::class]);

        $data = [
            'name' => 'New',
            'handle' => 'new',
            'color' => 'green',
            'default' => true
        ];
        $orderStatus = new OrderStatusModel($data);
        Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, []);

        $data = [
            'name' => 'Shipped',
            'handle' => 'shipped',
            'color' => 'blue',
            'default' => false
        ];
        $orderStatus = new OrderStatusModel($data);
        Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, []);
    }

    /**
     * Set the default product types.
     *
     * @throws \Exception
     */
    private function _defaultProductTypes()
    {
        $this->insert(FieldLayout::tableName(), ['type' => Product::class]);
        $this->_productFieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());
        $this->insert(FieldLayout::tableName(), ['type' => Variant::class]);
        $this->_variantFieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());

        $data = [
            'name' => 'Clothing',
            'handle' => 'clothing',
            'hasDimensions' => true,
            'hasVariants' => false,
            'hasVariantTitleField' => false,
            'titleFormat' => '{product.title}',
            'fieldLayoutId' => $this->_productFieldLayoutId,
            'skuFormat' => '',
            'descriptionFormat' => '',
            'variantFieldLayoutId' => $this->_variantFieldLayoutId
        ];

        $productType = new ProductTypeModel($data);

        $siteIds = (new Query())
            ->select(['id'])
            ->from(Site::tableName())
            ->column();

        $allSiteSettings = [];

        foreach ($siteIds as $siteId) {

            $siteSettings = new ProductTypeSiteModel();

            $siteSettings->siteId = $siteId;
            $siteSettings->hasUrls = true;
            $siteSettings->uriFormat = 'shop/products/{slug}';
            $siteSettings->template = 'shop/products/_product';

            $allSiteSettings[$siteId] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        Plugin::getInstance()->getProductTypes()->saveProductType($productType);
    }

    /**
     * Add some default products.
     *
     * @throws \Exception
     */
    private function _defaultProducts()
    {
        $productTypeId = (new Query())
            ->select(['id'])
            ->from(ProductType::tableName())
            ->scalar();

        $taxCategoryId = (new Query())
            ->select(['id'])
            ->from(TaxCategory::tableName())
            ->scalar();

        $shippingCategoryId = (new Query())
            ->select(['id'])
            ->from(ShippingCategory::tableName())
            ->scalar();

        if (!$productTypeId || !$taxCategoryId || !$shippingCategoryId) {
            throw new \RuntimeException('Cannot create the default products.');
        }

        $products = [
            ['title' => 'A New Toga', 'sku' => 'ANT-001'],
            ['title' => 'Parka with Stripes on Back', 'sku' => 'PSB-001'],
            ['title' => 'Romper for a Red Eye', 'sku' => 'RRE-001'],
            ['title' => 'The Fleece Awakens', 'sku' => 'TFA-001'],
            ['title' => 'The Last Knee-high', 'sku' => 'LKH-001']
        ];

        $count = 1;

        foreach ($products as $product) {
            // Create an element for product
            $productElementData = [
                'type' => Product::class,
                'enabled' => 1,
                'archived' => 0,
                'fieldLayoutId' => $this->_productFieldLayoutId
            ];
            $this->insert(Element::tableName(), $productElementData);
            $productId = $this->db->getLastInsertID(Element::tableName());

            // Create an element for variant
            $variantElementData = [
                'type' => Variant::class,
                'enabled' => 1,
                'archived' => 0,
                'fieldLayoutId' => $this->_variantFieldLayoutId
            ];
            $this->insert(Element::tableName(), $variantElementData);
            $variantId = $this->db->getLastInsertID(Element::tableName());

            // Populate the i18n data for each site
            $siteIds = (new Query())
                ->select(['id'])
                ->from(Site::tableName())
                ->column();

            foreach ($siteIds as $siteId) {
                // Product content data
                $productI18nData = [
                    'elementId' => $productId,
                    'siteId' => $siteId,
                    'slug' => ElementHelper::createSlug($product['sku']),
                    'uri' => null,
                    'enabled' => true
                ];
                $this->insert(Element_SiteSettings::tableName(), $productI18nData);

                $contentData = [
                    'elementId' => $productId,
                    'siteId' => $siteId,
                    'title' => StringHelper::toTitleCase($product['title'])
                ];
                $this->insert('{{%content}}', $contentData);

                // Variant content data
                $variantI18nData = [
                    'elementId' => $variantId,
                    'siteId' => $siteId,
                    'slug' => ElementHelper::createSlug($product['sku']),
                    'uri' => null,
                    'enabled' => true
                ];
                $this->insert(Element_SiteSettings::tableName(), $variantI18nData);

                $contentData = [
                    'elementId' => $variantId,
                    'siteId' => $siteId,
                    'title' => StringHelper::toTitleCase($product['title'])
                ];
                $this->insert('{{%content}}', $contentData);
            }

            $count++;

            // Prep data for variant and product
            $variantData = [
                'productId' => $productId,
                'id' => $variantId,
                'sku' => $product['sku'],
                'price' => 10 * $count,
                'hasUnlimitedStock' => true,
                'isDefault' => true
            ];

            $productData = [
                'id' => $productId,
                'typeId' => $productTypeId,
                'postDate' => DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s'),
                'expiryDate' => null,
                'promotable' => true,
                'availableForPurchase' => true,
                'defaultPrice' => 10 * $count,
                'defaultSku' => $product['sku'],
                'taxCategoryId' => $taxCategoryId,
                'shippingCategoryId' => $shippingCategoryId,
            ];

            // Insert the actual product and variant
            $this->insert(ProductRecord::tableName(), $productData);
            $this->insert(VariantRecord::tableName(), $variantData);

            $purchasableData = [
                'id' => $variantId,
                'sku' => $variantData['sku'],
                'price' => $variantData['price']
            ];
            $this->insert(PurchasableRecord::tableName(), $purchasableData);
        }

        // Generate URIs etc.
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Product::class
        ]));
    }

    /**
     * Add a payment method.
     */
    private function _defaultGateways()
    {
        $data = [
            'name' => 'Dummy',
            'handle' => 'dummy',
            'isFrontendEnabled' => true,
            'isArchived' => false,
        ];
        $gateway = new Dummy($data);
        Plugin::getInstance()->getGateways()->saveGateway($gateway);
    }

    /**
     * Returns if the table exists.
     *
     * @param string $tableName
     * @param Migration|null $migration
     * @return bool If the table exists.
     */
    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }
}
