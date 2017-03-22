<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\OrderSettings;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\PaymentMethod;
use craft\commerce\models\ProductType;
use craft\commerce\models\Settings;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\Country;
use craft\commerce\records\State;
use craft\db\ActiveRecord;
use craft\db\Migration;
use craft\services\Config;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Install extends Migration
{

    /**
     * @var string|null The database driver to use
     */
    public $driver;

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->get('driver', Config::CATEGORY_DB);
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->insertDefaultData();
    }

    public function createTables()
    {
        $this->createTable('{{%commerce_addresses}}', [
            'id' => $this->primaryKey(),
            'attention' => $this->string(),
            'title' => $this->string(),
            'firstName' => $this->string()->notNull(),
            'lastName' => $this->string()->notNull(),
            'countryId' => $this->integer(),
            'stateId' => $this->integer(),
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
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_countries}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'iso' => $this->string(),
            'stateRequired' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customer_discountuses}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer(),
            'customerId' => $this->integer(),
            'uses' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customers}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'lastUsedBillingAddressId' => $this->integer(),
            'lastUsedShippingAddressId' => $this->integer(),
            'email' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_customers_addresses}}', [
            'id' => $this->primaryKey(),
            'customerId' => $this->integer(),
            'addressId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_discount_products}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer(),
            'productId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_discount_producttypes}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer(),
            'productTypeId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_discount_usergroups}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer(),
            'userGroupId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_discounts}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'description' => $this->string(),
            'code' => $this->string(),
            'perUserLimit' => $this->integer(),
            'perEmailLimit' => $this->integer(),
            'totalUseLimit' => $this->integer(),
            'totalUses' => $this->integer(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'purchaseTotal' => $this->integer(),
            'purchaseQty' => $this->integer(),
            'maxPurchaseQty' => $this->integer(),
            'baseDiscount' => $this->string(),
            'perItemDiscount' => $this->decimal(),
            'percentDiscount' => $this->decimal(),
            'excludeOnSale' => $this->boolean(),
            'freeShipping' => $this->boolean(),
            'allGroups' => $this->boolean(),
            'allProducts' => $this->boolean(),
            'allProductTypes' => $this->boolean(),
            'enabled' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_emails}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'subject' => $this->string(),
            'recipientType' => $this->string(),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'enabled' => $this->boolean(),
            'templatePath' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_lineitems}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'purchasableId' => $this->integer(),
            'taxCategoryId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'options' => $this->string(),
            'optionsSignature' => $this->string(),
            'price' => $this->decimal(),
            'saleAmount' => $this->decimal(),
            'salePrice' => $this->decimal(),
            'tax' => $this->decimal(),
            'taxIncluded' => $this->boolean(),
            'shippingCost' => $this->decimal(),
            'discount' => $this->decimal(),
            'weight' => $this->decimal(),
            'height' => $this->decimal(),
            'length' => $this->decimal(),
            'width' => $this->decimal(),
            'total' => $this->decimal(),
            'qty' => $this->integer(),
            'note' => $this->string(),
            'snapshot' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_orderadjustments}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'type' => $this->string(),
            'name' => $this->string(),
            'description' => $this->string(),
            'amount' => $this->decimal(),
            'included' => $this->boolean(),
            'optionsJson' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_orderhistories}}', [
            'id' => $this->primaryKey(),
            'prevStatusId' => $this->integer(),
            'newStatusId' => $this->integer(),
            'orderId' => $this->integer(),
            'customerId' => $this->integer(),
            'message' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_orders}}', [
            'id' => $this->primaryKey(),
            'billingAddressId' => $this->integer(),
            'shippingAddressId' => $this->integer(),
            'paymentMethodId' => $this->integer(),
            'customerId' => $this->integer(),
            'orderStatusId' => $this->integer(),
            'shippingMethod' => $this->string(),
            'number' => $this->string(),
            'couponCode' => $this->string(),
            'itemTotal' => $this->decimal(),
            'baseDiscount' => $this->decimal(),
            'baseShippingCost' => $this->decimal(),
            'baseTax' => $this->decimal(),
            'totalPrice' => $this->decimal(),
            'totalPaid' => $this->decimal(),
            'email' => $this->string(),
            'isCompleted' => $this->boolean(),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'lastIp' => $this->string(),
            'orderLocale' => $this->string(),
            'message' => $this->string(),
            'returnUrl' => $this->string(),
            'cancelUrl' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_ordersettings}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_orderstatus_emails}}', [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer(),
            'emailId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_orderstatuses}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'color' => $this->string(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_paymentcurrencies}}', [
            'id' => $this->primaryKey(),
            'integer' => $this->string(),
            'iso' => $this->string(),
            'primary' => $this->boolean(),
            'rate' => $this->decimal(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_paymentmethods}}', [
            'id' => $this->primaryKey(),
            'class' => $this->string(),
            'name' => $this->string(),
            'settings' => $this->text(),
            'paymentType' => $this->string(),
            'frontendEnabled' => $this->boolean(),
            'isArchived' => $this->boolean(),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_products}}', [
            'id' => $this->primaryKey(),
            'typeId' => $this->integer(),
            'taxCategoryId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'defaultVariantId' => $this->integer(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean(),
            'freeShipping' => $this->boolean(),
            'defaultSku' => $this->string(),
            'defaultPrice' => $this->decimal(),
            'defaultHeight' => $this->decimal(),
            'defaultLength' => $this->decimal(),
            'defaultWidth' => $this->decimal(),
            'defaultWeight' => $this->decimal(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_producttypes}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->string(),
            'variantFieldLayoutId' => $this->string(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'hasUrls' => $this->string(),
            'hasDimensions' => $this->string(),
            'hasVariants' => $this->string(),
            'hasVariantTitleField' => $this->string(),
            'titleFormat' => $this->text(),
            'skuFormat' => $this->text(),
            'descriptionFormat' => $this->text(),
            'template' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_producttypes_i18n}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer(),
            'locale' => $this->string(),
            'urlFormat' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_producttypes_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_producttypes_taxcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer(),
            'taxCategoryId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_purchasables}}', [
            'id' => $this->primaryKey(),
            'sku' => $this->string(),
            'price' => $this->decimal(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_sale_products}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer(),
            'productId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_sale_producttypes}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer(),
            'productTypeId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
        $this->createTable('{{%commerce_sale_usergroups}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer(),
            'userGroupId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_sales}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'description' => $this->string(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'discountType' => $this->string(),
            'discountAmount' => $this->decimal(),
            'allGroups' => $this->boolean(),
            'allProducts' => $this->boolean(),
            'allProductTypes' => $this->boolean(),
            'enabled' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_shippingmethods}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'enabled' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_shippingrule_categories}}', [
            'id' => $this->primaryKey(),
            'shippingRuleId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'condition' => $this->string(),
            'perItemRate' => $this->decimal(),
            'weightRate' => $this->decimal(),
            'percentageRate' => $this->decimal(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
        $this->createTable('{{%commerce_shippingrules}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'methodId' => $this->integer(),
            'name' => $this->string(),
            'description' => $this->string(),
            'priority' => $this->integer(),
            'enabled' => $this->boolean(),
            'minQty' => $this->integer(),
            'maxQty' => $this->integer(),
            'minTotal' => $this->decimal(),
            'maxTotal' => $this->decimal(),
            'minWeight' => $this->decimal(),
            'maxWeight' => $this->decimal(),
            'baseRate' => $this->decimal(),
            'perItemRate' => $this->decimal(),
            'weightRate' => $this->decimal(),
            'percentageRate' => $this->decimal(),
            'minRate' => $this->decimal(),
            'maxRate' => $this->decimal(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_shippingzone_countries}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'countryId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
        $this->createTable('{{%commerce_shippingzone_states}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'stateId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_shippingzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'description' => $this->string(),
            'countryBased' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_states}}', [
            'id' => $this->primaryKey(),
            'countryId' => $this->integer(),
            'name' => $this->string(),
            'abbreviation' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
        $this->createTable('{{%commerce_taxcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'handle' => $this->string(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_taxrates}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'taxCategoryId' => $this->integer(),
            'name' => $this->string(),
            'rate' => $this->decimal(),
            'include' => $this->boolean(),
            'isVat' => $this->boolean(),
            'taxable' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_taxzone_countries}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'countryId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_taxzone_states}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer(),
            'stateId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_taxzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'description' => $this->string(),
            'countryBased' => $this->boolean(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_transactions}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'parentId' => $this->integer(),
            'paymentMethodId' => $this->integer(),
            'userId' => $this->integer(),
            'hash' => $this->string(),
            'type' => $this->string(),
            'amount' => $this->decimal(),
            'paymentAmount' => $this->decimal(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'paymentRate' => $this->decimal(),
            'status' => $this->string(),
            'reference' => $this->string(),
            'code' => $this->string(),
            'message' => $this->text(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%commerce_variants}}', [
            'id' => $this->primaryKey(),
            'productId' => $this->string(),
            'sku' => $this->string(),
            'isDefault' => $this->boolean(),
            'price' => $this->decimal(),
            'sortOrder' => $this->integer(),
            'width' => $this->decimal(),
            'height' => $this->decimal(),
            'length' => $this->decimal(),
            'weight' => $this->decimal(),
            'stock' => $this->integer(),
            'unlimitedStock' => $this->boolean(),
            'minQty' => $this->boolean(),
            'maxQty' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
    }

    public function createIndexes()
    {
    }

    public function addForeignKeys()
    {
    }

    public function insertDefaultData()
    {
        $this->defaultCountries();
        $this->defaultStates();
        $this->defaultCurrency();
        $this->defaultShippingMethod();
        $this->defaultTaxCategories();
        $this->defaultShippingCategories();
        $this->defaultOrderSettings();
        $this->defaultProductTypes();
        $this->defaultProducts();
        $this->paymentMethods();
        $this->defaultSettings();
    }

    public function defaultCountries()
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

        // Not doing a bulk insert in case someone wants to repopulate deleted countries will all missing
        foreach ($countries as $country) {
            $keyCols = [];
            $columns = [];
            $keyCols['iso'] = $country[0];
            $columns['name'] = $country[1];
            $this->insert('{{%commerce_countries}}', $keyCols, $columns);
        }
    }

    public function defaultStates()
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

        $table = State::tableName();
        $this->batchInsert($table, ['countryId', 'abbreviation', 'name'], $rows);
    }

    public function defaultCurrency()
    {
        $method = new PaymentCurrency();
        $method->iso = 'USD';
        $method->rate = 1;
        $method->primary = true;
        $method->save();
    }

    /**
     * Shipping Methods
     */
    private function defaultShippingMethod()
    {
        $method = new ShippingMethod();
        $method->name = 'Free Shipping';
        $method->handle = 'freeShipping';
        $method->enabled = true;
        $method->save();

        $rule = new ShippingRule();
        $rule->methodId = $method->id;
        $rule->description = "All Countries, free shipping.";
        $rule->name = "Free Everywhere ";
        $rule->enabled = true;
        $rule->save();
    }

    /**
     * @throws Exception
     */
    private function defaultTaxCategories()
    {
        $category = new TaxCategory([
            'name' => 'General',
            'handle' => 'general',
            'default' => 1,
        ]);

        Plugin::getInstance()->getTaxCategories()->saveTaxCategory($category);
    }

    /**
     * @throws Exception
     */
    private function defaultShippingCategories()
    {
        $category = new ShippingCategory([
            'name' => 'General',
            'handle' => 'general',
            'default' => 1,
        ]);

        Plugin::getInstance()->getShippingCategories()->saveShippingCategory($category);
    }

    /**
     * @throws \Exception
     */
    private function defaultOrderSettings()
    {

        $orderSettings = new OrderSettings();
        $orderSettings->name = 'Order';
        $orderSettings->handle = 'order';

        // Set the field layout
        $fieldLayout = craft()->fields->assembleLayout([], []);
        $fieldLayout->type = 'Commerce_Order';
        $orderSettings->setFieldLayout($fieldLayout);

        Plugin::getInstance()->getOrderSettings->saveOrderSetting($orderSettings);

        $data = [
            'name' => 'Processing',
            'handle' => 'processing',
            'color' => 'green',
            'default' => true
        ];
        $defaultStatus = new OrderStatus($data);
        Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($defaultStatus, []);

        $data = [
            'name' => 'Shipped',
            'handle' => 'shipped',
            'color' => 'blue',
            'default' => false
        ];

        $status = new OrderStatus($data);

        Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($status, []);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    private function defaultProductTypes()
    {
        $productType = new ProductType();
        $productType->name = 'Clothing';
        $productType->handle = 'clothing';
        $productType->hasDimensions = true;
        $productType->hasUrls = true;
        $productType->hasVariants = false;
        $productType->hasVariantTitleField = false;
        $productType->titleFormat = "{product.title}";
        $productType->template = 'shop/products/_product';

        $fieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Product']);
        craft()->fields->saveLayout($fieldLayout);
        $productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

        $variantFieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Variant']);
        craft()->fields->saveLayout($variantFieldLayout);
        $productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

        Plugin::getInstance()->getProductTypes()->saveProductType($productType);

        $productTypeLocales = craft()->i18n->getSiteLocaleIds();

        foreach ($productTypeLocales as $locale) {
            Craft::$app->getDb()->createCommand()->insert('commerce_producttypes_i18n', [
                'productTypeId' => $productType->id,
                'locale' => $locale,
                'urlFormat' => 'shop/products/{slug}'
            ]);
        }
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    private function defaultProducts()
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        $products = [
            'A New Toga',
            'Parka with Stripes on Back',
            'Romper for a Red Eye',
            'The Fleece Awakens'
        ];

        $count = 1;

        foreach ($products as $productName) {
            /** @var Variant $variant */
            $variant = new Variant([
                'sku' => $productName,
                'price' => (10 * $count++),
                'unlimitedStock' => 1,
                'isDefault' => 1,
            ]);

            /** @var Product $product */
            $product = new Product([
                'typeId' => $productTypes[0]->id,
                'enabled' => 1,
                'postDate' => new DateTime(),
                'expiryDate' => null,
                'promotable' => 1,
                'taxCategoryId' => 1,
                'shippingCategoryId' => 1,
            ]);

            $product->getContent()->title = $productName;
            $variant->setProduct($product);
            $product->setVariants([$variant]);

            Plugin::getInstance()->getProducts()->saveProduct($product);
        }
    }

    private function paymentMethods()
    {
        /** @var Dummy_GatewayAdapter $adapter */
        $adapter = Plugin::getInstance()->getGateways()->getAllGateways()['Dummy'];

        $model = new PaymentMethod();
        $model->class = $adapter->handle();
        $model->name = $adapter->displayName();
        $model->settings = $adapter->getGateway()->getDefaultParameters();
        $model->frontendEnabled = true;

        Plugin::getInstance()->getPaymentMethods()->savePaymentMethod($model);
    }

    private function defaultSettings()
    {
        $settings = new Settings();
        $settings->orderPdfPath = 'shop/_pdf/order';
        $settings->orderPdfFilenameFormat = 'Order-{number}';
        Plugin::getInstance()->getSettings()->saveSettings($settings);
    }

    public function safeDown()
    {
        parent::safeDown();
    }

}