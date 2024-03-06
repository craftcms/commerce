<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Donation;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\gateways\Dummy;
use craft\commerce\models\OrderStatus as OrderStatusModel;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency;
use craft\commerce\records\ShippingCategory;
use craft\commerce\records\ShippingMethod;
use craft\commerce\records\ShippingRule;
use craft\commerce\records\Store;
use craft\commerce\records\TaxCategory;
use craft\commerce\services\Coupons;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\records\FieldLayout;
use Exception;
use ReflectionClass;
use yii\base\NotSupportedException;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
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
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [Order::class, Product::class, Variant::class]]);

        return true;
    }


    /**
     * Creates the tables for Craft Commerce
     */
    public function createTables(): void
    {
        $this->archiveTableIfExists(Table::CUSTOMERS);
        $this->createTable(Table::CUSTOMERS, [
            'id' => $this->primaryKey(), // Not used in v4 but is the old customerId
            'customerId' => $this->integer()->notNull(), // This is the User element ID
            'primaryBillingAddressId' => $this->integer(),
            'primaryShippingAddressId' => $this->integer(),
            'primaryPaymentSourceId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::COUPONS);
        $this->createTable(Table::COUPONS, [
            'id' => $this->primaryKey(),
            'code' => $this->string(),
            'discountId' => $this->integer()->notNull(),
            'uses' => $this->integer()->notNull()->defaultValue(0),
            'maxUses' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::CUSTOMER_DISCOUNTUSES);
        $this->createTable(Table::CUSTOMER_DISCOUNTUSES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::EMAIL_DISCOUNTUSES);
        $this->createTable(Table::EMAIL_DISCOUNTUSES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'email' => $this->string()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::DISCOUNT_PURCHASABLES);
        $this->createTable(Table::DISCOUNT_PURCHASABLES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // TODO: rename to `discount_entries` table in Commerce 5 or remove if purchasable condition builder can replace it
        $this->archiveTableIfExists(Table::DISCOUNT_CATEGORIES);
        $this->createTable(Table::DISCOUNT_CATEGORIES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::DISCOUNTS);
        $this->createTable(Table::DISCOUNTS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'couponFormat' => $this->string(20)->notNull()->defaultValue(Coupons::DEFAULT_COUPON_FORMAT),
            'orderCondition' => $this->text(),
            'customerCondition' => $this->text(),
            'shippingAddressCondition' => $this->text(),
            'billingAddressCondition' => $this->text(),
            'perUserLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'perEmailLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalDiscountUses' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalDiscountUseLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'purchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'purchaseTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxPurchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'baseDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'baseDiscountType' => $this->enum('baseDiscountType', ['value', 'percentTotal', 'percentTotalDiscounted', 'percentItems', 'percentItemsDiscounted'])->notNull()->defaultValue('value'),
            'perItemDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageOffSubject' => $this->enum('percentageOffSubject', ['original', 'discounted'])->notNull(),
            'excludeOnSale' => $this->boolean()->notNull()->defaultValue(false),
            'hasFreeShippingForMatchingItems' => $this->boolean()->notNull()->defaultValue(false),
            'hasFreeShippingForOrder' => $this->boolean()->notNull()->defaultValue(false),
            'allPurchasables' => $this->boolean()->notNull()->defaultValue(false),
            'purchasableIds' => $this->text(),
            'allCategories' => $this->boolean()->notNull()->defaultValue(false),
            'categoryIds' => $this->text(),
            'appliedTo' => $this->enum('appliedTo', ['matchingLineItems', 'allLineItems'])->notNull()->defaultValue('matchingLineItems'),
            'categoryRelationshipType' => $this->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->notNull()->defaultValue('element'),
            'orderConditionFormula' => $this->text(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'stopProcessing' => $this->boolean()->notNull()->defaultValue(false),
            'ignoreSales' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::DONATIONS);
        $this->createTable(Table::DONATIONS, [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'availableForPurchase' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::EMAILS);
        $this->createTable(Table::EMAILS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'subject' => $this->string()->notNull(),
            'recipientType' => $this->enum('recipientType', ['customer', 'custom'])->defaultValue('custom'),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'cc' => $this->string(),
            'replyTo' => $this->string(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'templatePath' => $this->string()->notNull(),
            'plainTextTemplatePath' => $this->string(),
            'pdfId' => $this->integer(),
            'language' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PDFS);
        $this->createTable(Table::PDFS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'templatePath' => $this->string()->notNull(),
            'fileNameFormat' => $this->string(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'isDefault' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer(),
            'language' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::GATEWAYS);
        $this->createTable(Table::GATEWAYS, [
            'id' => $this->primaryKey(),
            'type' => $this->string()->notNull(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'settings' => $this->text(),
            'paymentType' => $this->enum('paymentType', ['authorize', 'purchase'])->notNull()->defaultValue('purchase'),
            'isFrontendEnabled' => $this->string(500)->notNull()->defaultValue('1'),
            'isArchived' => $this->boolean()->notNull()->defaultValue(false),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::LINEITEMS);
        $this->createTable(Table::LINEITEMS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'description' => $this->text(),
            'options' => $this->text(),
            'optionsSignature' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->unsigned(),
            'saleAmount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'salePrice' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'sku' => $this->string(),
            'weight' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'height' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'length' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'width' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'subtotal' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'total' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'qty' => $this->integer()->notNull()->unsigned(),
            'note' => $this->text(),
            'privateNote' => $this->text(),
            'snapshot' => $this->longText(),
            'lineItemStatusId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::LINEITEMSTATUSES);
        $this->createTable(Table::LINEITEMSTATUSES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'isArchived' => $this->boolean()->notNull()->defaultValue(false),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ORDERADJUSTMENTS);
        $this->createTable(Table::ORDERADJUSTMENTS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'lineItemId' => $this->integer(),
            'type' => $this->string()->notNull(),
            'name' => $this->string(),
            'description' => $this->string(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'included' => $this->boolean()->notNull()->defaultValue(false),
            'isEstimated' => $this->boolean()->notNull()->defaultValue(false),
            'sourceSnapshot' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ORDERNOTICES);
        $this->createTable(Table::ORDERNOTICES, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'type' => $this->string(),
            'attribute' => $this->string(),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ORDERHISTORIES);
        $this->createTable(Table::ORDERHISTORIES, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'userId' => $this->integer(),
            'userName' => $this->string(),
            'prevStatusId' => $this->integer(),
            'newStatusId' => $this->integer(),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ORDERS);
        $this->createTable(Table::ORDERS, [
            'id' => $this->integer()->notNull(),
            'billingAddressId' => $this->integer(),
            'shippingAddressId' => $this->integer(),
            'estimatedBillingAddressId' => $this->integer(),
            'estimatedShippingAddressId' => $this->integer(),
            'sourceShippingAddressId' => $this->integer(),
            'sourceBillingAddressId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'paymentSourceId' => $this->integer(),
            'customerId' => $this->integer(), // Customer ID is a User element ID
            'orderStatusId' => $this->integer(),
            'number' => $this->string(32),
            'reference' => $this->string(),
            'couponCode' => $this->string(),
            'itemTotal' => $this->decimal(14, 4)->defaultValue(0),
            'itemSubtotal' => $this->decimal(14, 4)->defaultValue(0),
            'totalQty' => $this->integer()->unsigned(),
            'total' => $this->decimal(14, 4)->defaultValue(0),
            'totalPrice' => $this->decimal(14, 4)->defaultValue(0),
            'totalPaid' => $this->decimal(14, 4)->defaultValue(0),
            'totalDiscount' => $this->decimal(14, 4)->defaultValue(0),
            'totalTax' => $this->decimal(14, 4)->defaultValue(0),
            'totalTaxIncluded' => $this->decimal(14, 4)->defaultValue(0),
            'totalShippingCost' => $this->decimal(14, 4)->defaultValue(0),
            'paidStatus' => $this->enum('paidStatus', ['paid', 'partial', 'unpaid', 'overPaid']),
            'email' => $this->string(),
            'orderCompletedEmail' => $this->string(),
            'isCompleted' => $this->boolean()->notNull()->defaultValue(false),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'dateAuthorized' => $this->dateTime(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'lastIp' => $this->string(),
            'orderLanguage' => $this->string(12)->notNull(),
            'origin' => $this->enum('origin', ['web', 'cp', 'remote'])->notNull()->defaultValue('web'),
            'message' => $this->text(),
            'registerUserOnOrderComplete' => $this->boolean()->notNull()->defaultValue(false),
            'saveBillingAddressOnOrderComplete' => $this->boolean()->notNull()->defaultValue(false),
            'saveShippingAddressOnOrderComplete' => $this->boolean()->notNull()->defaultValue(false),
            'recalculationMode' => $this->enum('recalculationMode', ['all', 'none', 'adjustmentsOnly'])->notNull()->defaultValue('all'),
            'returnUrl' => $this->text(),
            'cancelUrl' => $this->text(),
            'shippingMethodHandle' => $this->string()->notNull()->defaultValue(''),
            'shippingMethodName' => $this->string()->notNull()->defaultValue(''),
            'orderSiteId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->archiveTableIfExists(Table::ORDERSTATUS_EMAILS);
        $this->createTable(Table::ORDERSTATUS_EMAILS, [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer()->notNull(),
            'emailId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ORDERSTATUSES);
        $this->createTable(Table::ORDERSTATUSES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'description' => $this->string(),
            'dateDeleted' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PAYMENTCURRENCIES);
        $this->createTable(Table::PAYMENTCURRENCIES, [
            'id' => $this->primaryKey(),
            'iso' => $this->string(3)->notNull(),
            'primary' => $this->boolean()->notNull()->defaultValue(false),
            'rate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PAYMENTSOURCES);
        $this->createTable(Table::PAYMENTSOURCES, [
            'id' => $this->primaryKey(),
            'customerId' => $this->integer()->notNull(),
            'gatewayId' => $this->integer()->notNull(),
            'token' => $this->string()->notNull(),
            'description' => $this->string(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PLANS);
        $this->createTable(Table::PLANS, [
            'id' => $this->primaryKey(),
            'gatewayId' => $this->integer(),
            'planInformationId' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'reference' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(false),
            'planData' => $this->text(),
            'isArchived' => $this->boolean()->notNull()->defaultValue(false),
            'dateArchived' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'sortOrder' => $this->integer(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTS);
        $this->createTable(Table::PRODUCTS, [
            'id' => $this->integer()->notNull(),
            'typeId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'defaultVariantId' => $this->integer(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean()->notNull()->defaultValue(false),
            'availableForPurchase' => $this->boolean()->notNull()->defaultValue(true),
            'freeShipping' => $this->boolean()->notNull()->defaultValue(true),
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

        $this->archiveTableIfExists(Table::PRODUCTTYPES);
        $this->createTable(Table::PRODUCTTYPES, [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'variantFieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'hasDimensions' => $this->boolean()->notNull()->defaultValue(false),
            'hasVariants' => $this->boolean()->notNull()->defaultValue(false),

            // Variant title stuff
            'hasVariantTitleField' => $this->boolean()->notNull()->defaultValue(true),
            'variantTitleFormat' => $this->string()->notNull(),

            // Product title stuff
            'hasProductTitleField' => $this->boolean()->notNull()->defaultValue(true),
            'productTitleFormat' => $this->string(),

            'skuFormat' => $this->string(),
            'descriptionFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTTYPES_SITES);
        $this->createTable(Table::PRODUCTTYPES_SITES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'uriFormat' => $this->text(),
            'template' => $this->string(500),
            'hasUrls' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTTYPES_SHIPPINGCATEGORIES);
        $this->createTable(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTTYPES_TAXCATEGORIES);
        $this->createTable(Table::PRODUCTTYPES_TAXCATEGORIES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PURCHASABLES);
        $this->createTable(Table::PURCHASABLES, [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull(),
            'description' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SALE_PURCHASABLES);
        $this->createTable(Table::SALE_PURCHASABLES, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // TODO: rename to `sale_entries` table in Commerce 5 or remove if purchasable condition builder can replace it
        $this->archiveTableIfExists(Table::SALE_CATEGORIES);
        $this->createTable(Table::SALE_CATEGORIES, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SALE_USERGROUPS);
        $this->createTable(Table::SALE_USERGROUPS, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SALES);
        $this->createTable(Table::SALES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'apply' => $this->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat'])->notNull(),
            'applyAmount' => $this->decimal(14, 4)->notNull(),
            'allGroups' => $this->boolean()->notNull()->defaultValue(false),
            'allPurchasables' => $this->boolean()->notNull()->defaultValue(false),
            'allCategories' => $this->boolean()->notNull()->defaultValue(false),
            'categoryRelationshipType' => $this->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->notNull()->defaultValue('element'),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'ignorePrevious' => $this->boolean()->notNull()->defaultValue(false),
            'stopProcessing' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SHIPPINGCATEGORIES);
        $this->createTable(Table::SHIPPINGCATEGORIES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateDeleted' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SHIPPINGMETHODS);
        $this->createTable(Table::SHIPPINGMETHODS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SHIPPINGRULE_CATEGORIES);
        $this->createTable(Table::SHIPPINGRULE_CATEGORIES, [
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

        $this->archiveTableIfExists(Table::SHIPPINGRULES);
        $this->createTable(Table::SHIPPINGRULES, [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'methodId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'priority' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'orderConditionFormula' => $this->text(),
            'minQty' => $this->integer()->notNull()->defaultValue(0),
            'maxQty' => $this->integer()->notNull()->defaultValue(0),
            'minTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minMaxTotalType' => $this->enum('minMaxTotalType', ['salePrice', 'salePriceWithDiscounts'])->notNull()->defaultValue('salePrice'),
            'minWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'baseRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'perItemRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'weightRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SHIPPINGZONES);
        $this->createTable(Table::SHIPPINGZONES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'condition' => $this->text(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::STORES);
        $this->createTable(Table::STORES, [
            'id' => $this->primaryKey(),
            'locationAddressId' => $this->integer(),
            'countries' => $this->text(),
            'marketAddressCondition' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SUBSCRIPTIONS);
        $this->createTable(Table::SUBSCRIPTIONS, [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'planId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'orderId' => $this->integer(),
            'reference' => $this->string()->notNull(),
            'subscriptionData' => $this->text(),
            'trialDays' => $this->integer()->notNull(),
            'nextPaymentDate' => $this->dateTime(),
            'hasStarted' => $this->boolean()->notNull()->defaultValue(true),
            'isSuspended' => $this->boolean()->notNull()->defaultValue(false),
            'dateSuspended' => $this->dateTime(),
            'isCanceled' => $this->boolean()->notNull()->defaultValue(false),
            'dateCanceled' => $this->dateTime(),
            'isExpired' => $this->boolean()->notNull()->defaultValue(false),
            'dateExpired' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::TAXCATEGORIES);
        $this->createTable(Table::TAXCATEGORIES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateDeleted' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::TAXRATES);
        $this->createTable(Table::TAXRATES, [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'isEverywhere' => $this->boolean()->notNull()->defaultValue(true),
            'taxCategoryId' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            'code' => $this->string(),
            'rate' => $this->decimal(14, 10)->notNull(),
            'include' => $this->boolean()->notNull()->defaultValue(false),
            'isVat' => $this->boolean()->notNull()->defaultValue(false), // TODO rename to isEuVat #COM-45
            'removeIncluded' => $this->boolean()->notNull()->defaultValue(false),
            'removeVatIncluded' => $this->boolean()->notNull()->defaultValue(false),
            'taxable' => $this->enum('taxable', ['purchasable', 'price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price'])->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::TAXZONES);
        $this->createTable(Table::TAXZONES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'condition' => $this->text(),
            'default' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::TRANSACTIONS);
        $this->createTable(Table::TRANSACTIONS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'parentId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'userId' => $this->integer(), // Stays as userId since it could be a logged-in user or store administrator. So not just a customer.
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

        $this->archiveTableIfExists(Table::VARIANTS);
        $this->createTable(Table::VARIANTS, [
            'id' => $this->integer()->notNull(),
            'productId' => $this->integer(), // Allow null so we can delete a product THEN the variants.
            'sku' => $this->string()->notNull(),
            'isDefault' => $this->boolean()->notNull()->defaultValue(false),
            'price' => $this->decimal(14, 4)->notNull(),
            'sortOrder' => $this->integer(),
            'width' => $this->decimal(14, 4),
            'height' => $this->decimal(14, 4),
            'length' => $this->decimal(14, 4),
            'weight' => $this->decimal(14, 4),
            'stock' => $this->integer()->notNull()->defaultValue(0),
            'hasUnlimitedStock' => $this->boolean()->notNull()->defaultValue(false),
            'minQty' => $this->integer(),
            'maxQty' => $this->integer(),
            'deletedWithProduct' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);
    }

    /**
     * Drop the tables
     */
    public function dropTables(): void
    {
        $tables = $this->_getAllTableNames();
        foreach ($tables as $table) {
            $this->dropTableIfExists($table);
        }
    }

    /**
     * Deletes the project config entry.
     */
    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('commerce');
    }

    /**
     * Creates the indexes.
     */
    public function createIndexes(): void
    {
        $this->createIndex(null, Table::CUSTOMERS, 'customerId', true);
        $this->createIndex(null, Table::CUSTOMERS, 'primaryBillingAddressId', false);
        $this->createIndex(null, Table::CUSTOMERS, 'primaryShippingAddressId', false);
        $this->createIndex(null, Table::CUSTOMERS, 'primaryPaymentSourceId', false);
        $this->createIndex(null, Table::EMAIL_DISCOUNTUSES, ['email', 'discountId'], true);
        $this->createIndex(null, Table::EMAIL_DISCOUNTUSES, ['discountId'], false);
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId', 'discountId'], true);
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, 'discountId', false);
        $this->createIndex(null, Table::COUPONS, 'discountId', false);
        $this->createIndex(null, Table::COUPONS, 'code', false);
        $this->createIndex(null, Table::DISCOUNT_PURCHASABLES, ['discountId', 'purchasableId'], true);
        $this->createIndex(null, Table::DISCOUNT_PURCHASABLES, 'purchasableId', false);
        $this->createIndex(null, Table::DISCOUNT_CATEGORIES, ['discountId', 'categoryId'], true);
        $this->createIndex(null, Table::DISCOUNT_CATEGORIES, 'categoryId', false);
        $this->createIndex(null, Table::DISCOUNTS, 'dateFrom', false);
        $this->createIndex(null, Table::DISCOUNTS, 'dateTo', false);
        $this->createIndex(null, Table::GATEWAYS, 'handle', false);
        $this->createIndex(null, Table::GATEWAYS, 'isArchived', false);
        $this->createIndex(null, Table::LINEITEMS, ['orderId', 'purchasableId', 'optionsSignature'], true);
        $this->createIndex(null, Table::LINEITEMS, 'purchasableId', false);
        $this->createIndex(null, Table::LINEITEMS, 'taxCategoryId', false);
        $this->createIndex(null, Table::LINEITEMS, 'shippingCategoryId', false);
        $this->createIndex(null, Table::ORDERADJUSTMENTS, 'orderId', false);
        $this->createIndex(null, Table::ORDERNOTICES, 'orderId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'orderId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'prevStatusId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'newStatusId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'userId', false);
        $this->createIndex(null, Table::ORDERS, 'number', true);
        $this->createIndex(null, Table::ORDERS, 'reference', false);
        $this->createIndex(null, Table::ORDERS, 'billingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'shippingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'estimatedBillingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'estimatedShippingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'sourceBillingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'sourceShippingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'gatewayId', false);
        $this->createIndex(null, Table::ORDERS, 'customerId', false);
        $this->createIndex(null, Table::ORDERS, 'orderStatusId', false);
        $this->createIndex(null, Table::ORDERS, 'email', false);
        $this->createIndex(null, Table::ORDERSTATUS_EMAILS, 'orderStatusId', false);
        $this->createIndex(null, Table::ORDERSTATUS_EMAILS, 'emailId', false);
        $this->createIndex(null, Table::PAYMENTCURRENCIES, 'iso', true);
        $this->createIndex(null, Table::PDFS, 'handle', false);
        $this->createIndex(null, Table::PLANS, 'gatewayId', false);
        $this->createIndex(null, Table::PLANS, 'handle', true);
        $this->createIndex(null, Table::PLANS, 'reference', false);
        $this->createIndex(null, Table::PRODUCTS, 'typeId', false);
        $this->createIndex(null, Table::PRODUCTS, 'postDate', false);
        $this->createIndex(null, Table::PRODUCTS, 'expiryDate', false);
        $this->createIndex(null, Table::PRODUCTS, 'taxCategoryId', false);
        $this->createIndex(null, Table::PRODUCTS, 'shippingCategoryId', false);
        $this->createIndex(null, Table::PRODUCTTYPES, 'handle', true);
        $this->createIndex(null, Table::PRODUCTTYPES, 'fieldLayoutId', false);
        $this->createIndex(null, Table::PRODUCTTYPES, 'variantFieldLayoutId', false);
        $this->createIndex(null, Table::PRODUCTTYPES_SITES, ['productTypeId', 'siteId'], true);
        $this->createIndex(null, Table::PRODUCTTYPES_SITES, 'siteId', false);
        $this->createIndex(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['productTypeId', 'shippingCategoryId'], true);
        $this->createIndex(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, 'shippingCategoryId', false);
        $this->createIndex(null, Table::PRODUCTTYPES_TAXCATEGORIES, ['productTypeId', 'taxCategoryId'], true);
        $this->createIndex(null, Table::PRODUCTTYPES_TAXCATEGORIES, 'taxCategoryId', false);
        $this->createIndex(null, Table::PURCHASABLES, 'sku', false); // Application layer enforces unique
        $this->createIndex(null, Table::SALE_PURCHASABLES, ['saleId', 'purchasableId'], true);
        $this->createIndex(null, Table::SALE_PURCHASABLES, 'purchasableId', false);
        $this->createIndex(null, Table::SALE_CATEGORIES, ['saleId', 'categoryId'], true);
        $this->createIndex(null, Table::SALE_CATEGORIES, 'categoryId', false);
        $this->createIndex(null, Table::SALE_USERGROUPS, ['saleId', 'userGroupId'], true);
        $this->createIndex(null, Table::SALE_USERGROUPS, 'userGroupId', false);
        $this->createIndex(null, Table::SHIPPINGMETHODS, 'name', true);
        $this->createIndex(null, Table::SHIPPINGRULE_CATEGORIES, 'shippingRuleId', false);
        $this->createIndex(null, Table::SHIPPINGRULE_CATEGORIES, 'shippingCategoryId', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'name', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'methodId', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'shippingZoneId', false);
        $this->createIndex(null, Table::SHIPPINGZONES, 'name', true);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'userId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'planId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'gatewayId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'reference', true);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'nextPaymentDate', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'dateCreated', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'dateExpired', false);
        $this->createIndex(null, Table::TAXRATES, 'taxZoneId', false);
        $this->createIndex(null, Table::TAXRATES, 'taxCategoryId', false);
        $this->createIndex(null, Table::TAXZONES, 'name', true);
        $this->createIndex(null, Table::TRANSACTIONS, 'parentId', false);
        $this->createIndex(null, Table::TRANSACTIONS, 'gatewayId', false);
        $this->createIndex(null, Table::TRANSACTIONS, 'orderId', false);
        $this->createIndex(null, Table::TRANSACTIONS, 'userId', false);
        $this->createIndex(null, Table::VARIANTS, 'sku', false);
        $this->createIndex(null, Table::VARIANTS, 'productId', false);
    }

    /**
     * Adds the foreign keys.
     */
    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::COUPONS, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryPaymentSourceId'], Table::PAYMENTSOURCES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['categoryId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_PURCHASABLES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_PURCHASABLES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DONATIONS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::EMAILS, ['pdfId'], Table::PDFS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::EMAIL_DISCOUNTUSES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['purchasableId'], '{{%elements}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::ORDERADJUSTMENTS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['newStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['orderId'], Table::ORDERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['prevStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['userId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERNOTICES, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['billingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['estimatedBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['estimatedShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['gatewayId'], Table::GATEWAYS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['orderStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['paymentSourceId'], Table::PAYMENTSOURCES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['shippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERSTATUS_EMAILS, ['emailId'], Table::EMAILS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERSTATUS_EMAILS, ['orderStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['gatewayId'], Table::GATEWAYS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLANS, ['gatewayId'], Table::GATEWAYS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLANS, ['planInformationId'], '{{%elements}}', 'id', 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTS, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::PRODUCTS, ['taxCategoryId'], Table::TAXCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::PRODUCTS, ['typeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES, ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTTYPES, ['variantFieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SITES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SITES, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_TAXCATEGORIES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_TAXCATEGORIES, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PURCHASABLES, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['categoryId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_PURCHASABLES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_PURCHASABLES, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_USERGROUPS, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_USERGROUPS, ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGRULES, ['methodId'], Table::SHIPPINGMETHODS, ['id']);
        $this->addForeignKey(null, Table::SHIPPINGRULES, ['shippingZoneId'], Table::SHIPPINGZONES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::SHIPPINGRULE_CATEGORIES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGRULE_CATEGORIES, ['shippingRuleId'], Table::SHIPPINGRULES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['gatewayId'], Table::GATEWAYS, ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['orderId'], Table::ORDERS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['planId'], Table::PLANS, ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['userId'], CraftTable::USERS, ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::TAXRATES, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TAXRATES, ['taxZoneId'], Table::TAXZONES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['gatewayId'], Table::GATEWAYS, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['parentId'], Table::TRANSACTIONS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['userId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::VARIANTS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::VARIANTS, ['productId'], Table::PRODUCTS, ['id'], 'SET NULL'); // Allow null so we can delete a product THEN the variants.
    }

    /**
     * Removes the foreign keys.
     */
    public function dropForeignKeys(): void
    {
        $tables = $this->_getAllTableNames();

        foreach ($tables as $table) {
            $this->_dropForeignKeyToAndFromTable($table);
        }
    }

    /**
     * Insert the default data.
     */
    public function insertDefaultData(): void
    {
        // The following defaults are not stored in the project config.
        $this->_defaultCurrency();
        $this->_defaultStore();
        $this->_defaultShippingMethod();
        $this->_defaultTaxCategories();
        $this->_defaultShippingCategories();
        $this->_defaultDonationPurchasable();

        // Don't make the same config changes twice
        $installed = (Craft::$app->projectConfig->get('plugins.commerce', true) !== null);
        $configExists = (Craft::$app->projectConfig->get('commerce', true) !== null);

        if (!$installed && !$configExists) {
            $this->_defaultOrderSettings();
            $this->_defaultGateways();
        }
    }

    /**
     * Make USD the default currency.
     */
    private function _defaultCurrency(): void
    {
        $data = [
            'iso' => 'USD',
            'rate' => 1,
            'primary' => true,
        ];
        $this->insert(PaymentCurrency::tableName(), $data);
    }

    /**
     * Make the default store market US
     */
    private function _defaultStore(): void
    {
        $data = [
            'countries' => Json::encode(['US']),
        ];
        $this->insert(Store::tableName(), $data);
    }

    /**
     * Add a default shipping method and rule.
     */
    private function _defaultShippingMethod(): void
    {
        $data = [
            'name' => 'Free Shipping',
            'handle' => 'freeShipping',
            'enabled' => true,
        ];
        $this->insert(ShippingMethod::tableName(), $data);

        $data = [
            'methodId' => $this->db->getLastInsertID(ShippingMethod::tableName()),
            'description' => 'All countries, free shipping',
            'name' => 'Free Everywhere',
            'enabled' => true,
        ];
        $this->insert(ShippingRule::tableName(), $data);
    }

    /**
     * Add a default Tax category.
     */
    private function _defaultTaxCategories(): void
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true,
        ];
        $this->insert(TaxCategory::tableName(), $data);
    }

    /**
     * Add a default shipping category.
     */
    private function _defaultShippingCategories(): void
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true,
        ];
        $this->insert(ShippingCategory::tableName(), $data);
    }

    /**
     * Add the donation purchasable
     */
    public function _defaultDonationPurchasable(): void
    {
        $donation = new Donation();
        $donation->sku = 'DONATION-CC4';
        $donation->availableForPurchase = false;
        Craft::$app->getElements()->saveElement($donation);
    }

    /**
     * Add the default order settings.
     *
     * @throws Exception
     */
    private function _defaultOrderSettings(): void
    {
        $this->insert(FieldLayout::tableName(), ['type' => Order::class]);

        $data = [
            'name' => 'New',
            'handle' => 'new',
            'color' => 'green',
            'default' => true,
        ];
        $orderStatus = new OrderStatusModel($data);
        Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, []);
    }

    /**
     * Add a payment method.
     */
    private function _defaultGateways(): void
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
     * @return bool If the table exists.
     * @throws NotSupportedException
     */
    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }

    /**
     * @param $tableName
     * @throws NotSupportedException
     */
    private function _dropForeignKeyToAndFromTable($tableName): void
    {
        if ($this->_tableExists($tableName)) {
            $this->dropAllForeignKeysToTable($tableName);
            MigrationHelper::dropAllForeignKeysOnTable($tableName, $this);
        }
    }

    /**
     * @return string[]
     */
    private function _getAllTableNames(): array
    {
        $class = new ReflectionClass(Table::class);
        return $class->getConstants();
    }
}
