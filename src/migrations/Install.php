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
use Exception;
use RuntimeException;
use yii\base\NotSupportedException;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Install extends Migration
{
    private $_variantFieldLayoutId;
    private $_productFieldLayoutId;


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


    /**
     * Creates the tables for Craft Commerce
     */
    public function createTables()
    {
        $this->createTable(Table::ADDRESSES, [
            'id' => $this->primaryKey(),
            'countryId' => $this->integer(),
            'stateId' => $this->integer(),
            'isStoreLocation' => $this->boolean()->notNull()->defaultValue(false),
            'attention' => $this->string(),
            'title' => $this->string(),
            'firstName' => $this->string(),
            'lastName' => $this->string(),
            'fullName' => $this->string(),
            'address1' => $this->string(),
            'address2' => $this->string(),
            'address3' => $this->string(),
            'city' => $this->string(),
            'zipCode' => $this->string(),
            'phone' => $this->string(),
            'alternativePhone' => $this->string(),
            'label' => $this->string(),
            'notes' => $this->text(),
            'businessName' => $this->string(),
            'businessTaxId' => $this->string(),
            'businessId' => $this->string(),
            'stateName' => $this->string(),
            'custom1' => $this->string(),
            'custom2' => $this->string(),
            'custom3' => $this->string(),
            'custom4' => $this->string(),
            'isEstimated' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::COUNTRIES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'iso' => $this->string(2)->notNull(),
            'isStateRequired' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::CUSTOMER_DISCOUNTUSES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::EMAIL_DISCOUNTUSES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'email' => $this->string()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::CUSTOMERS, [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'primaryBillingAddressId' => $this->integer(),
            'primaryShippingAddressId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::CUSTOMERS_ADDRESSES, [
            'id' => $this->primaryKey(),
            'customerId' => $this->integer()->notNull(),
            'addressId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::DISCOUNT_PURCHASABLES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::DISCOUNT_CATEGORIES, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::DISCOUNT_USERGROUPS, [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::DISCOUNTS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'code' => $this->string(),
            'perUserLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'perEmailLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalDiscountUses' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalDiscountUseLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'purchaseTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'purchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'maxPurchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'baseDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'baseDiscountType' => $this->enum('baseDiscountType', ['value', 'percentTotal', 'percentTotalDiscounted', 'percentItems', 'percentItemsDiscounted'])->notNull()->defaultValue('value'),
            'perItemDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageOffSubject' => $this->enum('percentageOffSubject', ['original', 'discounted'])->notNull(),
            'excludeOnSale' => $this->boolean(),
            'hasFreeShippingForMatchingItems' => $this->boolean(),
            'hasFreeShippingForOrder' => $this->boolean(),
            'allGroups' => $this->boolean(),
            'allPurchasables' => $this->boolean(),
            'allCategories' => $this->boolean(),
            'categoryRelationshipType' => $this->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->notNull()->defaultValue('element'),
            'enabled' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'ignoreSales' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::DONATIONS, [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'availableForPurchase' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::EMAILS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'subject' => $this->string()->notNull(),
            'recipientType' => $this->enum('recipientType', ['customer', 'custom'])->defaultValue('custom'),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'cc' => $this->string(),
            'replyTo' => $this->string(),
            'enabled' => $this->boolean(),
            'attachPdf' => $this->boolean(),
            'templatePath' => $this->string()->notNull(),
            'plainTextTemplatePath' => $this->string(),
            'pdfTemplatePath' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::GATEWAYS, [
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

        $this->createTable(Table::LINEITEMS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'description' => $this->string(),
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

        $this->createTable(Table::LINEITEMSTATUSES, [
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

        $this->createTable(Table::ORDERADJUSTMENTS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'lineItemId' => $this->integer(),
            'type' => $this->string()->notNull(),
            'name' => $this->string(),
            'description' => $this->string(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'included' => $this->boolean(),
            'isEstimated' => $this->boolean()->notNull()->defaultValue(false),
            'sourceSnapshot' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::ORDERHISTORIES, [
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

        $this->createTable(Table::ORDERS, [
            'id' => $this->integer()->notNull(),
            'billingAddressId' => $this->integer(),
            'shippingAddressId' => $this->integer(),
            'estimatedBillingAddressId' => $this->integer(),
            'estimatedShippingAddressId' => $this->integer(),
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
            'totalDiscount' => $this->decimal(14, 4)->defaultValue(0),
            'totalTax' => $this->decimal(14, 4)->defaultValue(0),
            'totalTaxIncluded' => $this->decimal(14, 4)->defaultValue(0),
            'totalShippingCost' => $this->decimal(14, 4)->defaultValue(0),
            'paidStatus' => $this->enum('paidStatus', ['paid', 'partial', 'unpaid', 'overPaid']),
            'email' => $this->string(),
            'isCompleted' => $this->boolean(),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'dateAuthorized' => $this->dateTime(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'lastIp' => $this->string(),
            'orderLanguage' => $this->string(12)->notNull(),
            'origin' => $this->enum('origin', ['web', 'cp', 'remote'])->notNull()->defaultValue('web'),
            'message' => $this->text(),
            'registerUserOnOrderComplete' => $this->boolean(),
            'recalculationMode' => $this->enum('recalculationMode', ['all', 'none', 'adjustmentsOnly'])->notNull()->defaultValue('all'),
            'returnUrl' => $this->string(),
            'cancelUrl' => $this->string(),
            'shippingMethodHandle' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable(Table::ORDERSTATUS_EMAILS, [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer()->notNull(),
            'emailId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::ORDERSTATUSES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'description' => $this->string(),
            'dateDeleted' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::PAYMENTCURRENCIES, [
            'id' => $this->primaryKey(),
            'iso' => $this->string(3)->notNull(),
            'primary' => $this->boolean()->notNull()->defaultValue(false),
            'rate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::PAYMENTSOURCES, [
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

        $this->createTable(Table::PLANS, [
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

        $this->createTable(Table::PRODUCTS, [
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

        $this->createTable(Table::PRODUCTTYPES, [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'variantFieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'hasDimensions' => $this->boolean(),
            'hasVariants' => $this->boolean(),
            'hasVariantTitleField' => $this->boolean(),
            'titleFormat' => $this->string()->notNull(),
            'titleLabel' => $this->string()->defaultValue('Title'),
            'variantTitleLabel' => $this->string()->defaultValue('Title'),
            'skuFormat' => $this->string(),
            'descriptionFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::PRODUCTTYPES_SITES, [
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

        $this->createTable(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::PRODUCTTYPES_TAXCATEGORIES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::PURCHASABLES, [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull(),
            'description' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SALE_PURCHASABLES, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SALE_CATEGORIES, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SALE_USERGROUPS, [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SALES, [
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
            'categoryRelationshipType' => $this->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->notNull()->defaultValue('element'),
            'enabled' => $this->boolean(),
            'ignorePrevious' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SHIPPINGCATEGORIES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SHIPPINGMETHODS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'enabled' => $this->boolean(),
            'isLite' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

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

        $this->createTable(Table::SHIPPINGRULES, [
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

        $this->createTable(Table::SHIPPINGZONE_COUNTRIES, [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SHIPPINGZONE_STATES, [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::SHIPPINGZONES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'isCountryBased' => $this->boolean(),
            'zipCodeConditionFormula' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::STATES, [
            'id' => $this->primaryKey(),
            'countryId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'abbreviation' => $this->string(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

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
            'isCanceled' => $this->boolean()->notNull(),
            'dateCanceled' => $this->dateTime(),
            'isExpired' => $this->boolean()->notNull(),
            'dateExpired' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TAXCATEGORIES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TAXRATES, [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'isEverywhere' => $this->boolean(),
            'taxCategoryId' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            'code' => $this->string(),
            'rate' => $this->decimal(14, 10)->notNull(),
            'include' => $this->boolean(),
            'isVat' => $this->boolean(),
            'taxable' => $this->enum('taxable', ['price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price'])->notNull(),
            'isLite' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TAXZONE_COUNTRIES, [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TAXZONE_STATES, [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TAXZONES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'isCountryBased' => $this->boolean(),
            'zipCodeConditionFormula' => $this->text(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::TRANSACTIONS, [
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

        $this->createTable(Table::VARIANTS, [
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
        $this->dropTableIfExists(Table::ADDRESSES);
        $this->dropTableIfExists(Table::COUNTRIES);
        $this->dropTableIfExists(Table::CUSTOMER_DISCOUNTUSES);
        $this->dropTableIfExists(Table::EMAIL_DISCOUNTUSES);
        $this->dropTableIfExists(Table::CUSTOMERS);
        $this->dropTableIfExists(Table::CUSTOMERS_ADDRESSES);
        $this->dropTableIfExists(Table::DISCOUNT_PURCHASABLES);
        $this->dropTableIfExists(Table::DISCOUNT_CATEGORIES);
        $this->dropTableIfExists(Table::DISCOUNT_USERGROUPS);
        $this->dropTableIfExists(Table::DISCOUNTS);
        $this->dropTableIfExists(Table::DONATIONS);
        $this->dropTableIfExists(Table::EMAILS);
        $this->dropTableIfExists(Table::GATEWAYS);
        $this->dropTableIfExists(Table::LINEITEMS);
        $this->dropTableIfExists(Table::ORDERADJUSTMENTS);
        $this->dropTableIfExists(Table::ORDERHISTORIES);
        $this->dropTableIfExists(Table::ORDERS);
        $this->dropTableIfExists(Table::ORDERSTATUS_EMAILS);
        $this->dropTableIfExists(Table::ORDERSTATUSES);
        $this->dropTableIfExists(Table::PAYMENTCURRENCIES);
        $this->dropTableIfExists(Table::PAYMENTSOURCES);
        $this->dropTableIfExists(Table::PLANS);
        $this->dropTableIfExists(Table::PRODUCTS);
        $this->dropTableIfExists(Table::PRODUCTTYPES);
        $this->dropTableIfExists(Table::PRODUCTTYPES_SITES);
        $this->dropTableIfExists(Table::PRODUCTTYPES_SHIPPINGCATEGORIES);
        $this->dropTableIfExists(Table::PRODUCTTYPES_TAXCATEGORIES);
        $this->dropTableIfExists(Table::PURCHASABLES);
        $this->dropTableIfExists(Table::SALE_PURCHASABLES);
        $this->dropTableIfExists(Table::SALE_CATEGORIES);
        $this->dropTableIfExists(Table::SALE_USERGROUPS);
        $this->dropTableIfExists(Table::SALES);
        $this->dropTableIfExists(Table::SHIPPINGCATEGORIES);
        $this->dropTableIfExists(Table::SHIPPINGMETHODS);
        $this->dropTableIfExists(Table::SHIPPINGRULE_CATEGORIES);
        $this->dropTableIfExists(Table::SHIPPINGRULES);
        $this->dropTableIfExists(Table::SHIPPINGZONE_COUNTRIES);
        $this->dropTableIfExists(Table::SHIPPINGZONE_STATES);
        $this->dropTableIfExists(Table::SHIPPINGZONES);
        $this->dropTableIfExists(Table::STATES);
        $this->dropTableIfExists(Table::SUBSCRIPTIONS);
        $this->dropTableIfExists(Table::TAXCATEGORIES);
        $this->dropTableIfExists(Table::TAXRATES);
        $this->dropTableIfExists(Table::TAXZONE_COUNTRIES);
        $this->dropTableIfExists(Table::TAXZONE_STATES);
        $this->dropTableIfExists(Table::TAXZONES);
        $this->dropTableIfExists(Table::TRANSACTIONS);
        $this->dropTableIfExists(Table::VARIANTS);

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
        $this->createIndex(null, Table::ADDRESSES, 'countryId', false);
        $this->createIndex(null, Table::ADDRESSES, 'stateId', false);
        $this->createIndex(null, Table::COUNTRIES, 'name', true);
        $this->createIndex(null, Table::COUNTRIES, 'iso', true);
        $this->createIndex(null, Table::EMAIL_DISCOUNTUSES, ['email', 'discountId'], true);
        $this->createIndex(null, Table::EMAIL_DISCOUNTUSES, ['discountId'], false);
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId', 'discountId'], true);
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, 'discountId', false);
        $this->createIndex(null, Table::CUSTOMERS, 'userId', false);
        $this->createIndex(null, Table::CUSTOMERS, 'primaryBillingAddressId', false);
        $this->createIndex(null, Table::CUSTOMERS, 'primaryShippingAddressId', false);
        $this->createIndex(null, Table::CUSTOMERS_ADDRESSES, ['customerId', 'addressId'], true);
        $this->createIndex(null, Table::DISCOUNT_PURCHASABLES, ['discountId', 'purchasableId'], true);
        $this->createIndex(null, Table::DISCOUNT_PURCHASABLES, 'purchasableId', false);
        $this->createIndex(null, Table::DISCOUNT_CATEGORIES, ['discountId', 'categoryId'], true);
        $this->createIndex(null, Table::DISCOUNT_CATEGORIES, 'categoryId', false);
        $this->createIndex(null, Table::DISCOUNT_USERGROUPS, ['discountId', 'userGroupId'], true);
        $this->createIndex(null, Table::DISCOUNT_USERGROUPS, 'userGroupId', false);
        $this->createIndex(null, Table::DISCOUNTS, 'code', true);
        $this->createIndex(null, Table::DISCOUNTS, 'dateFrom', false);
        $this->createIndex(null, Table::DISCOUNTS, 'dateTo', false);
        $this->createIndex(null, Table::GATEWAYS, 'handle', false);
        $this->createIndex(null, Table::GATEWAYS, 'isArchived', false);
        $this->createIndex(null, Table::LINEITEMS, ['orderId', 'purchasableId', 'optionsSignature'], true);
        $this->createIndex(null, Table::LINEITEMS, 'purchasableId', false);
        $this->createIndex(null, Table::LINEITEMS, 'taxCategoryId', false);
        $this->createIndex(null, Table::LINEITEMS, 'shippingCategoryId', false);
        $this->createIndex(null, Table::ORDERADJUSTMENTS, 'orderId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'orderId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'prevStatusId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'newStatusId', false);
        $this->createIndex(null, Table::ORDERHISTORIES, 'customerId', false);
        $this->createIndex(null, Table::ORDERS, 'number', true);
        $this->createIndex(null, Table::ORDERS, 'reference', false);
        $this->createIndex(null, Table::ORDERS, 'billingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'shippingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'gatewayId', false);
        $this->createIndex(null, Table::ORDERS, 'customerId', false);
        $this->createIndex(null, Table::ORDERS, 'orderStatusId', false);
        $this->createIndex(null, Table::ORDERSTATUS_EMAILS, 'orderStatusId', false);
        $this->createIndex(null, Table::ORDERSTATUS_EMAILS, 'emailId', false);
        $this->createIndex(null, Table::PAYMENTCURRENCIES, 'iso', true);
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
        $this->createIndex(null, Table::SHIPPINGCATEGORIES, 'handle', true);
        $this->createIndex(null, Table::SHIPPINGMETHODS, 'name', true);
        $this->createIndex(null, Table::SHIPPINGRULE_CATEGORIES, 'shippingRuleId', false);
        $this->createIndex(null, Table::SHIPPINGRULE_CATEGORIES, 'shippingCategoryId', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'name', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'methodId', false);
        $this->createIndex(null, Table::SHIPPINGRULES, 'shippingZoneId', false);
        $this->createIndex(null, Table::SHIPPINGZONE_COUNTRIES, ['shippingZoneId', 'countryId'], true);
        $this->createIndex(null, Table::SHIPPINGZONE_COUNTRIES, 'shippingZoneId', false);
        $this->createIndex(null, Table::SHIPPINGZONE_COUNTRIES, 'countryId', false);
        $this->createIndex(null, Table::SHIPPINGZONE_STATES, ['shippingZoneId', 'stateId'], true);
        $this->createIndex(null, Table::SHIPPINGZONE_STATES, 'shippingZoneId', false);
        $this->createIndex(null, Table::SHIPPINGZONE_STATES, 'stateId', false);
        $this->createIndex(null, Table::SHIPPINGZONES, 'name', true);
        $this->createIndex(null, Table::STATES, 'countryId', false);
        $this->createIndex(null, Table::STATES, ['countryId', 'abbreviation'], true);
        $this->createIndex(null, Table::STATES, ['countryId', 'name'], true);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'userId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'planId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'gatewayId', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'reference', true);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'nextPaymentDate', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'dateCreated', false);
        $this->createIndex(null, Table::SUBSCRIPTIONS, 'dateExpired', false);
        $this->createIndex(null, Table::TAXCATEGORIES, 'handle', true);
        $this->createIndex(null, Table::TAXRATES, 'taxZoneId', false);
        $this->createIndex(null, Table::TAXRATES, 'taxCategoryId', false);
        $this->createIndex(null, Table::TAXZONE_COUNTRIES, ['taxZoneId', 'countryId'], true);
        $this->createIndex(null, Table::TAXZONE_COUNTRIES, 'taxZoneId', false);
        $this->createIndex(null, Table::TAXZONE_COUNTRIES, 'countryId', false);
        $this->createIndex(null, Table::TAXZONE_STATES, ['taxZoneId', 'stateId'], true);
        $this->createIndex(null, Table::TAXZONE_STATES, 'taxZoneId', false);
        $this->createIndex(null, Table::TAXZONE_STATES, 'stateId', false);
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
    public function addForeignKeys()
    {
        $this->addForeignKey(null, Table::ADDRESSES, ['countryId'], Table::COUNTRIES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ADDRESSES, ['stateId'], Table::STATES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId'], Table::CUSTOMERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::EMAIL_DISCOUNTUSES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMERS, ['userId'], '{{%users}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryBillingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryShippingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS_ADDRESSES, ['addressId'], Table::ADDRESSES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMERS_ADDRESSES, ['customerId'], Table::CUSTOMERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_PURCHASABLES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_PURCHASABLES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_USERGROUPS, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_USERGROUPS, ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DONATIONS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['purchasableId'], '{{%elements}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::LINEITEMS, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::ORDERADJUSTMENTS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['customerId'], Table::CUSTOMERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['newStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['orderId'], Table::ORDERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['prevStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['billingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['customerId'], Table::CUSTOMERS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['orderStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERS, ['gatewayId'], Table::GATEWAYS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['paymentSourceId'], Table::PAYMENTSOURCES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['shippingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['estimatedShippingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERS, ['estimatedBillingAddressId'], Table::ADDRESSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::ORDERSTATUS_EMAILS, ['emailId'], Table::EMAILS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::ORDERSTATUS_EMAILS, ['orderStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['gatewayId'], Table::GATEWAYS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['userId'], '{{%users}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLANS, ['gatewayId'], Table::GATEWAYS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLANS, ['planInformationId'], '{{%elements}}', 'id', 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTS, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::PRODUCTS, ['taxCategoryId'], Table::TAXCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::PRODUCTS, ['typeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES, ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTTYPES, ['variantFieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SITES, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SITES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_TAXCATEGORIES, ['productTypeId'], Table::PRODUCTTYPES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTTYPES_TAXCATEGORIES, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PURCHASABLES, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SALE_PURCHASABLES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_PURCHASABLES, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_USERGROUPS, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_USERGROUPS, ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGRULE_CATEGORIES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGRULE_CATEGORIES, ['shippingRuleId'], Table::SHIPPINGRULES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGRULES, ['methodId'], Table::SHIPPINGMETHODS, ['id']);
        $this->addForeignKey(null, Table::SHIPPINGRULES, ['shippingZoneId'], Table::SHIPPINGZONES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::SHIPPINGZONE_COUNTRIES, ['countryId'], Table::COUNTRIES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGZONE_COUNTRIES, ['shippingZoneId'], Table::SHIPPINGZONES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGZONE_STATES, ['shippingZoneId'], Table::SHIPPINGZONES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SHIPPINGZONE_STATES, ['stateId'], Table::STATES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::STATES, ['countryId'], Table::COUNTRIES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['userId'], '{{%users}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['planId'], Table::PLANS, ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['gatewayId'], Table::GATEWAYS, ['id'], 'RESTRICT');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['orderId'], Table::ORDERS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::TAXRATES, ['taxCategoryId'], Table::TAXCATEGORIES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TAXRATES, ['taxZoneId'], Table::TAXZONES, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TAXZONE_COUNTRIES, ['countryId'], Table::COUNTRIES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TAXZONE_COUNTRIES, ['taxZoneId'], Table::TAXZONES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TAXZONE_STATES, ['stateId'], Table::STATES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TAXZONE_STATES, ['taxZoneId'], Table::TAXZONES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['parentId'], Table::TRANSACTIONS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['gatewayId'], Table::GATEWAYS, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Table::TRANSACTIONS, ['userId'], '{{%users}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::VARIANTS, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::VARIANTS, ['productId'], Table::PRODUCTS, ['id'], 'SET NULL'); // Allow null so we can delete a product THEN the variants.
    }

    /**
     * Removes the foreign keys.
     */
    public function dropForeignKeys()
    {
        $tables = [
            Table::ADDRESSES,
            Table::CUSTOMER_DISCOUNTUSES,
            Table::EMAIL_DISCOUNTUSES,
            Table::CUSTOMERS,
            Table::CUSTOMERS_ADDRESSES,
            Table::DISCOUNT_PURCHASABLES,
            Table::DISCOUNT_CATEGORIES,
            Table::DISCOUNT_USERGROUPS,
            Table::DONATIONS,
            Table::LINEITEMS,
            Table::ORDERADJUSTMENTS,
            Table::ORDERHISTORIES,
            Table::ORDERS,
            Table::ORDERSTATUS_EMAILS,
            Table::PAYMENTSOURCES,
            Table::PLANS,
            Table::PRODUCTS,
            Table::PRODUCTTYPES,
            Table::PRODUCTTYPES_SITES,
            Table::PRODUCTTYPES_SHIPPINGCATEGORIES,
            Table::PRODUCTTYPES_TAXCATEGORIES,
            Table::PURCHASABLES,
            Table::SALE_PURCHASABLES,
            Table::SALE_CATEGORIES,
            Table::SALE_USERGROUPS,
            Table::SHIPPINGRULE_CATEGORIES,
            Table::SHIPPINGRULES,
            Table::SHIPPINGZONE_COUNTRIES,
            Table::SHIPPINGZONE_STATES,
            Table::STATES,
            Table::SUBSCRIPTIONS,
            Table::TAXRATES,
            Table::TAXZONE_COUNTRIES,
            Table::TAXZONE_STATES,
            Table::TRANSACTIONS,
            Table::VARIANTS
        ];

        foreach ($tables as $table) {
            $this->_dropForeignKeyToAndFromTable($table);
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

        $this->batchInsert(Table::COUNTRIES, ['iso', 'name'], $countries);
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
     * @throws Exception
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
    }

    /**
     * Set the default product types.
     *
     * @throws Exception
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
     * @throws Exception
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
            throw new RuntimeException('Cannot create the default products.');
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
    private function _dropForeignKeyToAndFromTable($tableName)
    {
        if ($this->_tableExists($tableName)) {
            MigrationHelper::dropAllForeignKeysToTable($tableName, $this);
            MigrationHelper::dropAllForeignKeysOnTable($tableName, $this);
        }
    }
}
