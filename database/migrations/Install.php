<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Database\Migrations;

use Closure;
use CraftCms\Cms\Console\PromptTask;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingQueue;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Coupons;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Prompts\Support\Logger;
use ReflectionClass;

class Install extends Migration
{
    private function task(string $label, Closure $callback): void
    {
        if ($this->silent) {
            $callback();

            return;
        }

        PromptTask::run(
            label: str($label)->finish('...')->toString(),
            callback: function (Logger $logger) use ($callback, $label) {
                $callback($logger);
                $logger->label($label);
            },
            keepSummary: true,
            output: $this->output,
        );
    }

    public function up(): void
    {
        $this->task('Install Craft Commerce', function (?Logger $logger = null) {
            $logger?->subLabel('Creating tables...');
            $this->createTables();
            $logger?->success('Tables created.');

            $logger?->subLabel('Creating indexes...');
            $this->createIndexes();
            $logger?->success('Indexes created.');

            $logger?->subLabel('Adding foreign keys...');
            $this->addForeignKeys();
            $logger?->success('Foreign keys added.');
        });

        $this->task('Seed default Craft Commerce data', function (?Logger $logger = null) {
            $this->insertDefaultData();
            $logger?->success('Default data seeded.');
        });
    }

    /**
     * Creates the tables for Craft Commerce.
     */
    public function createTables(): void
    {
        Schema::create(Table::CATALOG_PRICING_RULES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('storeId');
            $table->dateTime('dateFrom')->nullable();
            $table->dateTime('dateTo')->nullable();
            $table->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat']);
            $table->decimal('applyAmount', 14, 4);
            $table->enum('applyPriceType', [CatalogPricingRule::APPLY_PRICE_TYPE_PRICE, CatalogPricingRule::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE]);
            $table->text('productCondition')->nullable();
            $table->text('variantCondition')->nullable();
            $table->text('purchasableCondition')->nullable();
            $table->text('customerCondition')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('isPromotionalPrice')->default(false);
            $table->text('metadata')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::CATALOG_PRICING_RULES_USERS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('catalogPricingRuleId');
            $table->integer('userId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::CATALOG_PRICING, function (Blueprint $table) {
            $table->integer('id', true);
            $table->decimal('price', 14, 4)->nullable(); // @TODO Consider storing as string to avoid float-precision issues
            $table->integer('purchasableId');
            $table->integer('storeId')->nullable();
            $table->integer('catalogPricingRuleId')->nullable();
            $table->integer('userId')->nullable();
            $table->dateTime('dateFrom')->nullable();
            $table->dateTime('dateTo')->nullable();
            $table->boolean('isPromotionalPrice')->default(false)->nullable();
            $table->boolean('hasUpdatePending')->default(false)->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::CATALOG_PRICING_QUEUE, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->enum('type', [CatalogPricingQueue::TYPE_PURCHASABLE, CatalogPricingQueue::TYPE_RULE]);
            $table->mediumText('ids')->nullable();
            $table->boolean('reserved')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::CUSTOMERS, function (Blueprint $table) {
            $table->integer('id', true); // Not used in v4 but is the old customerId
            $table->integer('customerId'); // This is the User element ID
            $table->integer('primaryBillingAddressId')->nullable();
            $table->integer('primaryShippingAddressId')->nullable();
            $table->integer('primaryPaymentSourceId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::COUPONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('code')->nullable();
            $table->integer('discountId');
            $table->integer('uses')->default(0);
            $table->integer('maxUses')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::CUSTOMER_DISCOUNTUSES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('discountId');
            $table->integer('customerId');
            $table->unsignedInteger('uses');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::EMAIL_DISCOUNTUSES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('discountId');
            $table->string('email');
            $table->unsignedInteger('uses');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::DISCOUNT_PURCHASABLES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('discountId');
            $table->integer('purchasableId');
            $table->string('purchasableType');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        // @TODO Rename to `discount_entries` table in Commerce 6.0, or remove if the purchasable condition builder fully replaces it
        Schema::create(Table::DISCOUNT_CATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('discountId');
            $table->integer('categoryId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::DISCOUNTS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('couponFormat', 20)->default(Coupons::DEFAULT_COUPON_FORMAT);
            $table->text('orderCondition')->nullable();
            $table->text('customerCondition')->nullable();
            $table->text('shippingAddressCondition')->nullable();
            $table->text('billingAddressCondition')->nullable();
            $table->boolean('requireCouponCode')->default(false);
            $table->unsignedInteger('perUserLimit')->default(0);
            $table->unsignedInteger('perEmailLimit')->default(0);
            $table->unsignedInteger('totalDiscountUses')->default(0);
            $table->unsignedInteger('totalDiscountUseLimit')->default(0);
            $table->dateTime('dateFrom')->nullable();
            $table->dateTime('dateTo')->nullable();
            $table->integer('purchaseQty')->default(0);
            $table->decimal('purchaseTotal', 14, 4)->default(0);
            $table->integer('maxPurchaseQty')->default(0);
            $table->decimal('baseDiscount', 14, 4)->default(0);
            $table->decimal('perItemDiscount', 14, 4)->default(0);
            $table->decimal('percentDiscount', 14, 4)->default(0);
            $table->enum('percentageOffSubject', ['original', 'discounted']);
            $table->boolean('excludeOnPromotion')->default(false);
            $table->boolean('hasFreeShippingForMatchingItems')->default(false);
            $table->boolean('hasFreeShippingForOrder')->default(false);
            $table->boolean('allPurchasables')->default(false);
            $table->text('purchasableIds')->nullable();
            $table->boolean('allCategories')->default(false);
            $table->text('categoryIds')->nullable();
            $table->enum('appliedTo', ['matchingLineItems', 'allLineItems'])->default('matchingLineItems');
            $table->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->default('element');
            $table->text('orderConditionFormula')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('stopProcessing')->default(false);
            $table->boolean('ignorePromotions')->default(false);
            $table->integer('sortOrder')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::DONATIONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('sku');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::EMAILS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->string('name');
            $table->string('senderAddress')->nullable();
            $table->string('senderName')->nullable();
            $table->string('subject');
            $table->enum('recipientType', ['customer', 'custom'])->default('custom')->nullable();
            $table->string('to')->nullable();
            $table->string('bcc')->nullable();
            $table->string('cc')->nullable();
            $table->string('replyTo')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('templatePath');
            $table->string('plainTextTemplatePath')->nullable();
            $table->integer('pdfId')->nullable();
            $table->string('language')->nullable();
            $table->integer('renderSiteId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PDFS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->string('description')->nullable();
            $table->string('templatePath');
            $table->string('fileNameFormat')->nullable();
            $table->string('paperOrientation')->default('portrait')->nullable();
            $table->string('paperSize')->default('letter')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('isDefault')->default(false);
            $table->integer('sortOrder')->nullable();
            $table->string('language')->nullable();
            $table->integer('linkExpiry')->default(86400);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::GATEWAYS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('type');
            $table->string('name');
            $table->string('handle');
            $table->text('settings')->nullable();
            $table->enum('paymentType', ['authorize', 'purchase'])->default('purchase');
            $table->string('isFrontendEnabled', 500)->default('1');
            $table->text('orderCondition')->nullable();
            $table->text('shippingAddressCondition')->nullable();
            $table->text('billingAddressCondition')->nullable();
            $table->boolean('isArchived')->default(false);
            $table->dateTime('dateArchived')->nullable();
            $table->integer('sortOrder')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::INVENTORYITEMS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('purchasableId');
            $table->string('countryCodeOfOrigin')->nullable();
            $table->string('administrativeAreaCodeOfOrigin')->nullable();
            $table->string('harmonizedSystemCode')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::INVENTORYLOCATIONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('handle');
            $table->string('name');
            $table->integer('addressId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->dateTime('dateDeleted')->nullable();
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::INVENTORYLOCATIONS_STORES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('inventoryLocationId');
            $table->integer('storeId');
            $table->integer('sortOrder')->nullable(); // per store
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::INVENTORYTRANSACTIONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('inventoryLocationId');
            $table->integer('inventoryItemId');
            $table->string('movementHash');
            $table->integer('quantity');
            $table->enum('type', [
                'incoming',
                'available',
                'committed',
                'reserved',
                'damaged',
                'safety',
                'fulfilled',
                'qualityControl',
            ]);
            $table->string('note')->nullable();
            $table->integer('transferId')->nullable(); // Can be null
            $table->integer('lineItemId')->nullable(); // Can be null
            $table->integer('userId')->nullable(); // Can be null
            $table->dateTime('dateCreated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::LINEITEMS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->enum('type', ['purchasable', 'custom'])->default('purchasable');
            $table->integer('purchasableId')->nullable();
            $table->integer('taxCategoryId');
            $table->integer('shippingCategoryId');
            $table->text('description')->nullable();
            $table->text('options')->nullable();
            $table->string('optionsSignature');
            $table->decimal('price', 14, 4)->unsigned();
            $table->decimal('promotionalPrice', 14, 4)->unsigned()->nullable();
            $table->decimal('promotionalAmount', 14, 4)->default(0);
            $table->decimal('salePrice', 14, 4)->default(0);
            $table->string('sku')->nullable();
            $table->decimal('weight', 14, 4)->default(0)->unsigned();
            $table->decimal('height', 14, 4)->default(0)->unsigned();
            $table->decimal('length', 14, 4)->default(0)->unsigned();
            $table->decimal('width', 14, 4)->default(0)->unsigned();
            $table->decimal('subtotal', 14, 4)->default(0)->unsigned();
            $table->decimal('total', 14, 4)->default(0);
            $table->unsignedInteger('qty');
            $table->text('note')->nullable();
            $table->text('privateNote')->nullable();
            $table->boolean('hasFreeShipping')->nullable();
            $table->boolean('isPromotable')->nullable();
            $table->boolean('isShippable')->nullable();
            $table->boolean('isTaxable')->nullable();
            $table->longText('snapshot')->nullable();
            $table->integer('lineItemStatusId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::LINEITEMSTATUSES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->default('green');
            $table->boolean('isArchived')->default(false);
            $table->dateTime('dateArchived')->nullable();
            $table->integer('sortOrder')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::ORDERADJUSTMENTS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->integer('lineItemId')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 4);
            $table->boolean('included')->default(false);
            $table->boolean('isEstimated')->default(false);
            $table->longText('sourceSnapshot')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::ORDERNOTICES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->string('type')->nullable();
            $table->string('attribute')->nullable();
            $table->text('message')->nullable();
            $table->string('noticeType')->default('customer');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::ORDERHISTORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->integer('userId')->nullable();
            $table->string('userName')->nullable();
            $table->integer('prevStatusId')->nullable();
            $table->integer('newStatusId')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::ORDERS, function (Blueprint $table) {
            $table->integer('id');
            $table->integer('storeId');
            $table->integer('billingAddressId')->nullable();
            $table->integer('shippingAddressId')->nullable();
            $table->integer('estimatedBillingAddressId')->nullable();
            $table->integer('estimatedShippingAddressId')->nullable();
            $table->integer('sourceShippingAddressId')->nullable();
            $table->integer('sourceBillingAddressId')->nullable();
            $table->integer('gatewayId')->nullable();
            $table->integer('paymentSourceId')->nullable();
            $table->integer('customerId')->nullable(); // Customer ID is a User element ID
            $table->boolean('customerDeleted')->default(false);
            $table->integer('orderStatusId')->nullable();
            $table->string('number', 32)->nullable();
            $table->string('reference')->nullable();
            $table->string('couponCode')->nullable();
            $table->decimal('itemTotal', 14, 4)->default(0)->nullable();
            $table->decimal('itemSubtotal', 14, 4)->default(0)->nullable();
            $table->unsignedInteger('totalQty')->nullable();
            $table->decimal('totalWeight', 14, 4)->default(0)->unsigned()->nullable();
            $table->decimal('total', 14, 4)->default(0)->nullable();
            $table->decimal('totalPrice', 14, 4)->default(0)->nullable();
            $table->decimal('totalPaid', 14, 4)->default(0)->nullable();
            $table->decimal('totalDiscount', 14, 4)->default(0)->nullable();
            $table->decimal('totalTax', 14, 4)->default(0)->nullable();
            $table->decimal('totalTaxIncluded', 14, 4)->default(0)->nullable();
            $table->decimal('totalShippingCost', 14, 4)->default(0)->nullable();
            $table->enum('paidStatus', ['paid', 'partial', 'unpaid', 'overPaid'])->nullable();
            $table->string('email')->nullable();
            $table->string('orderCompletedEmail')->nullable();
            $table->boolean('isCompleted')->default(false);
            $table->dateTime('dateOrdered')->nullable();
            $table->dateTime('datePaid')->nullable();
            $table->dateTime('dateFirstPaid')->nullable();
            $table->dateTime('dateAuthorized')->nullable();
            $table->string('currency')->nullable();
            $table->string('paymentCurrency')->nullable();
            $table->string('lastIp')->nullable();
            $table->string('orderLanguage', 12);
            $table->enum('origin', ['web', 'cp', 'remote'])->default('web');
            $table->text('message')->nullable();
            $table->boolean('registerUserOnOrderComplete')->default(false);
            $table->boolean('saveBillingAddressOnOrderComplete')->default(false);
            $table->boolean('makePrimaryBillingAddress')->default(false);
            $table->boolean('saveShippingAddressOnOrderComplete')->default(false);
            $table->boolean('makePrimaryShippingAddress')->default(false);
            $table->enum('recalculationMode', ['all', 'none', 'adjustmentsOnly'])->default('all');
            $table->text('returnUrl')->nullable();
            $table->text('cancelUrl')->nullable();
            $table->string('shippingMethodHandle')->default('');
            $table->string('shippingMethodName')->default('');
            $table->integer('orderSiteId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('id');
        });

        Schema::create(Table::ORDERSTATUS_EMAILS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderStatusId');
            $table->integer('emailId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::ORDERSTATUSES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->default('green');
            $table->string('description')->nullable();
            $table->dateTime('dateDeleted')->nullable();
            $table->integer('sortOrder')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PAYMENTCURRENCIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->string('iso', 3);
            $table->boolean('primary')->default(false);
            $table->decimal('rate', 14, 4)->default(0);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PAYMENTSOURCES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('customerId');
            $table->integer('gatewayId');
            $table->string('token');
            $table->string('description')->nullable();
            $table->text('response')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PLANS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('gatewayId')->nullable();
            $table->integer('planInformationId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->string('reference');
            $table->boolean('enabled')->default(false);
            $table->text('planData')->nullable();
            $table->boolean('isArchived')->default(false);
            $table->dateTime('dateArchived')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->integer('sortOrder')->nullable();
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PRODUCTS, function (Blueprint $table) {
            $table->integer('id');
            $table->integer('typeId')->nullable();
            $table->integer('defaultVariantId')->nullable();
            $table->dateTime('postDate')->nullable();
            $table->dateTime('expiryDate')->nullable();
            $table->string('defaultSku')->nullable();
            $table->decimal('defaultPrice', 14, 4)->nullable();
            $table->decimal('defaultHeight', 14, 4)->nullable();
            $table->decimal('defaultLength', 14, 4)->nullable();
            $table->decimal('defaultWidth', 14, 4)->nullable();
            $table->decimal('defaultWeight', 14, 4)->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('id');
        });

        Schema::create(Table::PRODUCTTYPES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->boolean('isStructure')->default(false);
            $table->unsignedSmallInteger('maxLevels')->nullable();
            $table->enum('defaultPlacement', [ProductType::DEFAULT_PLACEMENT_BEGINNING, ProductType::DEFAULT_PLACEMENT_END])->default('end');
            $table->integer('structureId')->nullable();
            $table->integer('fieldLayoutId')->nullable();
            $table->integer('variantFieldLayoutId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->boolean('enableVersioning')->default(false);
            $table->integer('maxVariants')->nullable();
            $table->boolean('hasDimensions')->default(false);

            // Variant title stuff
            $table->boolean('hasVariantTitleField')->default(true);
            $table->string('variantTitleFormat');
            $table->string('variantTitleTranslationMethod')->default('site');
            $table->string('variantTitleTranslationKeyFormat')->nullable();
            $table->string('variantUiLabelFormat')->default('{title}');

            // Product title stuff
            $table->boolean('hasProductTitleField')->default(true);
            $table->string('productTitleFormat')->nullable();
            $table->string('productTitleTranslationMethod')->default('site');
            $table->string('productTitleTranslationKeyFormat')->nullable();
            $table->string('productUiLabelFormat')->default('{title}');

            // Slug stuff
            $table->boolean('showSlugField')->default(true);
            $table->string('slugTranslationMethod')->default('site');
            $table->string('slugTranslationKeyFormat')->nullable();

            $table->string('propagationMethod')->default(PropagationMethod::All->value);
            $table->json('previewTargets')->nullable();

            $table->string('skuFormat')->nullable();
            $table->string('descriptionFormat')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PRODUCTTYPES_SITES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('productTypeId');
            $table->integer('siteId');
            $table->text('uriFormat')->nullable();
            $table->string('template', 500)->nullable();
            $table->boolean('hasUrls')->default(false);
            $table->boolean('enabledByDefault')->default(true);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('productTypeId');
            $table->integer('shippingCategoryId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PRODUCTTYPES_TAXCATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('productTypeId');
            $table->integer('taxCategoryId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PURCHASABLES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('sku');
            $table->text('description')->nullable();
            $table->decimal('width', 14, 4)->nullable();
            $table->decimal('height', 14, 4)->nullable();
            $table->decimal('length', 14, 4)->nullable();
            $table->decimal('weight', 14, 4)->nullable();
            $table->integer('taxCategoryId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::PURCHASABLES_STORES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('purchasableId');
            $table->integer('storeId');
            $table->decimal('basePrice', 14, 4)->nullable(); // @TODO Consider storing as string to avoid float-precision issues
            $table->decimal('basePromotionalPrice', 14, 4)->nullable(); // @TODO Consider storing as string to avoid float-precision issues
            $table->boolean('promotable')->default(false);
            $table->boolean('availableForPurchase')->default(true);
            $table->boolean('freeShipping')->default(true);
            $table->boolean('inventoryTracked')->default(true);
            $table->boolean('allowOutOfStockPurchases')->default(false);
            $table->integer('stock')->nullable(); // This is a summary value used for searching and sorting
            $table->boolean('tracked')->default(false);
            $table->integer('minQty')->nullable();
            $table->integer('maxQty')->nullable();
            $table->integer('shippingCategoryId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SALE_PURCHASABLES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('saleId');
            $table->integer('purchasableId');
            $table->string('purchasableType');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        // @TODO Rename to `sale_entries` table in Commerce 6.0, or remove if the purchasable condition builder fully replaces it
        Schema::create(Table::SALE_CATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('saleId');
            $table->integer('categoryId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SALE_USERGROUPS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('saleId');
            $table->integer('userGroupId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SALES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('dateFrom')->nullable();
            $table->dateTime('dateTo')->nullable();
            $table->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat']);
            $table->decimal('applyAmount', 14, 4);
            $table->boolean('allGroups')->default(false);
            $table->boolean('allPurchasables')->default(false);
            $table->boolean('allCategories')->default(false);
            $table->enum('categoryRelationshipType', ['element', 'sourceElement', 'targetElement'])->default('element');
            $table->boolean('enabled')->default(true);
            $table->boolean('ignorePrevious')->default(false);
            $table->boolean('stopProcessing')->default(false);
            $table->integer('sortOrder')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SHIPPINGCATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->string('name');
            $table->string('handle');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('description')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateDeleted')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SHIPPINGMETHODS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->string('name');
            $table->string('handle');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->text('orderCondition')->nullable();
            $table->text('customerCondition')->nullable();
            $table->boolean('enabled')->default(true);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SHIPPINGRULE_CATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('shippingRuleId')->nullable();
            $table->integer('shippingCategoryId')->nullable();
            $table->enum('condition', ['allow', 'disallow', 'require']);
            $table->decimal('perItemRate', 14, 4)->nullable();
            $table->decimal('weightRate', 14, 4)->nullable();
            $table->decimal('percentageRate', 14, 4)->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SHIPPINGRULES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('methodId');
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->text('orderConditionFormula')->nullable();
            $table->text('orderCondition')->nullable();
            $table->text('customerCondition')->nullable();
            $table->decimal('baseRate', 14, 4)->default(0);
            $table->decimal('perItemRate', 14, 4)->default(0);
            $table->decimal('weightRate', 14, 4)->default(0);
            $table->decimal('percentageRate', 14, 4)->default(0);
            $table->decimal('minRate', 14, 4)->default(0);
            $table->decimal('maxRate', 14, 4)->default(0);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SHIPPINGZONES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->text('condition')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::SITESTORES, function (Blueprint $table) {
            $table->integer('siteId');
            $table->integer('storeId')->nullable(); // defaults to primary store in app
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('siteId');
        });

        Schema::create(Table::STORES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->string('handle');
            $table->boolean('primary');
            $table->string('currency')->default('USD');
            $table->string('autoSetCartShippingMethodOption')->default('false');
            $table->string('autoSetNewCartAddresses')->default('false');
            $table->string('autoSetPaymentSource')->default('false');
            $table->string('allowEmptyCartOnCheckout')->default('false');
            $table->string('allowCheckoutWithoutPayment')->default('false');
            $table->string('allowPartialPaymentOnCheckout')->default('false');
            $table->string('requireShippingAddressAtCheckout')->default('false');
            $table->string('requireBillingAddressAtCheckout')->default('false');
            $table->string('requireShippingMethodSelectionAtCheckout')->default('false');
            $table->string('useBillingAddressForTax')->default('false');
            $table->string('validateOrganizationTaxIdAsVatId')->default('false');
            $table->string('orderReferenceFormat')->nullable();
            $table->string('freeOrderPaymentStrategy')->default('complete')->nullable();
            $table->string('minimumTotalPriceStrategy')->default('default')->nullable();
            $table->integer('sortOrder')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::STORESETTINGS, function (Blueprint $table) {
            $table->integer('id');
            $table->integer('locationAddressId')->nullable();
            $table->text('countries')->nullable();
            $table->text('marketAddressCondition')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('id');
        });

        Schema::create(Table::SUBSCRIPTIONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('userId');
            $table->integer('planId')->nullable();
            $table->integer('gatewayId')->nullable();
            $table->integer('orderId')->nullable();
            $table->string('reference');
            $table->text('subscriptionData')->nullable();
            $table->integer('trialDays');
            $table->dateTime('nextPaymentDate')->nullable();
            $table->boolean('hasStarted')->default(true);
            $table->boolean('isSuspended')->default(false);
            $table->dateTime('dateSuspended')->nullable();
            $table->boolean('isCanceled')->default(false);
            $table->dateTime('dateCanceled')->nullable();
            $table->boolean('isExpired')->default(false);
            $table->text('returnUrl')->nullable();
            $table->dateTime('dateExpired')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TAXCATEGORIES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->string('handle');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('description')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateDeleted')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TAXRATES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->integer('taxZoneId')->nullable();
            $table->boolean('isEverywhere')->default(true);
            $table->integer('taxCategoryId')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('rate', 14, 10);
            $table->boolean('include')->default(false);
            $table->boolean('isVat')->default(false); // @TODO Remove in Commerce 6.0
            $table->text('taxIdValidators')->nullable();
            $table->boolean('removeIncluded')->default(false);
            $table->boolean('removeVatIncluded')->default(false);
            $table->enum('taxable', ['purchasable', 'price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price']);
            $table->boolean('enabled')->default(true);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TAXZONES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('storeId');
            $table->string('name');
            $table->string('description')->nullable();
            $table->text('condition')->nullable();
            $table->boolean('default')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TRANSACTIONS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->integer('parentId')->nullable();
            $table->integer('gatewayId')->nullable();
            $table->integer('userId')->nullable(); // Stays as userId since it could be a logged-in user or store administrator. So not just a customer.
            $table->string('hash', 32)->nullable();
            $table->enum('type', ['authorize', 'capture', 'purchase', 'refund']);
            $table->decimal('amount', 14, 4)->nullable();
            $table->decimal('paymentAmount', 14, 4)->nullable();
            $table->string('currency')->nullable();
            $table->string('paymentCurrency')->nullable();
            $table->decimal('paymentRate', 14, 4)->nullable();
            $table->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing']);
            $table->string('reference')->nullable();
            $table->string('code')->nullable();
            $table->text('message')->nullable();
            $table->mediumText('note')->nullable();
            $table->text('response')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TRANSFERS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('transferStatus', [
                'draft',
                'pending',
                'partial',
                'received',
            ]);
            $table->integer('originLocationId')->nullable();
            $table->integer('destinationLocationId')->nullable();
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::TRANSFERDETAILS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('transferId');
            $table->integer('inventoryItemId')->nullable();
            $table->string('inventoryItemDescription');
            $table->integer('quantity');
            $table->integer('quantityAccepted');
            $table->integer('quantityRejected');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::VARIANTS, function (Blueprint $table) {
            $table->integer('id');
            $table->integer('primaryOwnerId')->nullable();
            $table->boolean('isDefault')->default(false);
            $table->boolean('deletedWithProduct')->default(false); // @TODO Remove in Commerce 6.0
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('id');
        });
    }

    /**
     * Creates the indexes.
     */
    public function createIndexes(): void
    {
        Schema::createIndex(Table::CATALOG_PRICING, ['catalogPricingRuleId']);
        Schema::createIndex(Table::CATALOG_PRICING, ['isPromotionalPrice']);
        Schema::createIndex(Table::CATALOG_PRICING, ['purchasableId']);
        Schema::createIndex(Table::CATALOG_PRICING, ['storeId']);
        Schema::createIndex(Table::CATALOG_PRICING, ['userId']);
        Schema::createIndex(Table::CATALOG_PRICING, ['purchasableId', 'storeId', 'isPromotionalPrice', 'price', 'catalogPricingRuleId', 'dateFrom', 'dateTo']);
        Schema::createIndex(Table::CATALOG_PRICING, ['purchasableId', 'storeId', 'isPromotionalPrice', 'price']);
        Schema::createIndex(Table::CATALOG_PRICING, ['purchasableId', 'storeId']);
        Schema::createIndex(Table::CATALOG_PRICING_QUEUE, ['reserved']);
        Schema::createIndex(Table::CATALOG_PRICING_QUEUE, ['storeId', 'type', 'reserved']);
        Schema::createIndex(Table::CATALOG_PRICING_RULES, ['storeId']);
        Schema::createIndex(Table::CATALOG_PRICING_RULES_USERS, ['catalogPricingRuleId']);
        Schema::createIndex(Table::CATALOG_PRICING_RULES_USERS, ['userId']);
        Schema::createIndex(Table::COUPONS, ['code']);
        Schema::createIndex(Table::COUPONS, ['discountId']);
        Schema::createIndex(Table::CUSTOMERS, ['customerId'], unique: true);
        Schema::createIndex(Table::CUSTOMERS, ['primaryBillingAddressId']);
        Schema::createIndex(Table::CUSTOMERS, ['primaryPaymentSourceId']);
        Schema::createIndex(Table::CUSTOMERS, ['primaryShippingAddressId']);
        Schema::createIndex(Table::CUSTOMER_DISCOUNTUSES, ['discountId']);
        Schema::createIndex(Table::CUSTOMER_DISCOUNTUSES, ['customerId', 'discountId'], unique: true);
        Schema::createIndex(Table::DISCOUNTS, ['dateFrom']);
        Schema::createIndex(Table::DISCOUNTS, ['dateTo']);
        Schema::createIndex(Table::DISCOUNT_CATEGORIES, ['categoryId']);
        Schema::createIndex(Table::DISCOUNT_CATEGORIES, ['discountId', 'categoryId'], unique: true);
        Schema::createIndex(Table::DISCOUNT_PURCHASABLES, ['purchasableId']);
        Schema::createIndex(Table::DISCOUNT_PURCHASABLES, ['discountId', 'purchasableId'], unique: true);
        Schema::createIndex(Table::EMAILS, ['storeId']);
        Schema::createIndex(Table::EMAIL_DISCOUNTUSES, ['discountId']);
        Schema::createIndex(Table::EMAIL_DISCOUNTUSES, ['email', 'discountId'], unique: true);
        Schema::createIndex(Table::GATEWAYS, ['handle']);
        Schema::createIndex(Table::GATEWAYS, ['isArchived']);
        Schema::createIndex(Table::INVENTORYITEMS, ['purchasableId'], unique: true);
        Schema::createIndex(Table::INVENTORYTRANSACTIONS, ['inventoryItemId']);
        Schema::createIndex(Table::INVENTORYTRANSACTIONS, ['lineItemId']);
        Schema::createIndex(Table::INVENTORYTRANSACTIONS, ['transferId']);
        Schema::createIndex(Table::INVENTORYTRANSACTIONS, ['userId']);
        Schema::createIndex(Table::LINEITEMS, ['purchasableId']);
        Schema::createIndex(Table::LINEITEMS, ['shippingCategoryId']);
        Schema::createIndex(Table::LINEITEMS, ['taxCategoryId']);
        Schema::createIndex(Table::LINEITEMS, ['orderId', 'purchasableId', 'optionsSignature'], unique: true);
        Schema::createIndex(Table::LINEITEMSTATUSES, ['storeId']);
        Schema::createIndex(Table::ORDERADJUSTMENTS, ['orderId']);
        Schema::createIndex(Table::ORDERHISTORIES, ['newStatusId']);
        Schema::createIndex(Table::ORDERHISTORIES, ['orderId']);
        Schema::createIndex(Table::ORDERHISTORIES, ['prevStatusId']);
        Schema::createIndex(Table::ORDERHISTORIES, ['userId']);
        Schema::createIndex(Table::ORDERNOTICES, ['orderId']);
        Schema::createIndex(Table::ORDERS, ['billingAddressId']);
        Schema::createIndex(Table::ORDERS, ['customerId']);
        Schema::createIndex(Table::ORDERS, ['email']);
        Schema::createIndex(Table::ORDERS, ['estimatedBillingAddressId']);
        Schema::createIndex(Table::ORDERS, ['estimatedShippingAddressId']);
        Schema::createIndex(Table::ORDERS, ['gatewayId']);
        Schema::createIndex(Table::ORDERS, ['number'], unique: true);
        Schema::createIndex(Table::ORDERS, ['orderStatusId']);
        Schema::createIndex(Table::ORDERS, ['reference']);
        Schema::createIndex(Table::ORDERS, ['shippingAddressId']);
        Schema::createIndex(Table::ORDERS, ['sourceBillingAddressId']);
        Schema::createIndex(Table::ORDERS, ['sourceShippingAddressId']);
        Schema::createIndex(Table::ORDERS, ['storeId']);
        Schema::createIndex(Table::ORDERSTATUSES, ['storeId']);
        Schema::createIndex(Table::ORDERSTATUS_EMAILS, ['emailId']);
        Schema::createIndex(Table::ORDERSTATUS_EMAILS, ['orderStatusId']);
        Schema::createIndex(Table::PAYMENTCURRENCIES, ['iso']);
        Schema::createIndex(Table::PDFS, ['handle']);
        Schema::createIndex(Table::PDFS, ['storeId']);
        Schema::createIndex(Table::PLANS, ['gatewayId']);
        Schema::createIndex(Table::PLANS, ['handle'], unique: true);
        Schema::createIndex(Table::PLANS, ['reference']);
        Schema::createIndex(Table::PRODUCTS, ['expiryDate']);
        Schema::createIndex(Table::PRODUCTS, ['postDate']);
        Schema::createIndex(Table::PRODUCTS, ['typeId']);
        Schema::createIndex(Table::PRODUCTTYPES, ['structureId']);
        Schema::createIndex(Table::PRODUCTTYPES, ['fieldLayoutId']);
        Schema::createIndex(Table::PRODUCTTYPES, ['handle'], unique: true);
        Schema::createIndex(Table::PRODUCTTYPES, ['variantFieldLayoutId']);
        Schema::createIndex(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['shippingCategoryId']);
        Schema::createIndex(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['productTypeId', 'shippingCategoryId'], unique: true);
        Schema::createIndex(Table::PRODUCTTYPES_SITES, ['siteId']);
        Schema::createIndex(Table::PRODUCTTYPES_SITES, ['productTypeId', 'siteId'], unique: true);
        Schema::createIndex(Table::PRODUCTTYPES_TAXCATEGORIES, ['taxCategoryId']);
        Schema::createIndex(Table::PRODUCTTYPES_TAXCATEGORIES, ['productTypeId', 'taxCategoryId'], unique: true);
        Schema::createIndex(Table::PURCHASABLES, ['sku']); // Application layer enforces unique
        Schema::createIndex(Table::PURCHASABLES_STORES, ['purchasableId']); // Application layer enforces unique
        Schema::createIndex(Table::PURCHASABLES_STORES, ['storeId']); // Application layer enforces unique
        Schema::createIndex(Table::SALE_CATEGORIES, ['categoryId']);
        Schema::createIndex(Table::SALE_CATEGORIES, ['saleId', 'categoryId'], unique: true);
        Schema::createIndex(Table::SALE_PURCHASABLES, ['purchasableId']);
        Schema::createIndex(Table::SALE_PURCHASABLES, ['saleId', 'purchasableId'], unique: true);
        Schema::createIndex(Table::SALE_USERGROUPS, ['userGroupId']);
        Schema::createIndex(Table::SALE_USERGROUPS, ['saleId', 'userGroupId'], unique: true);
        Schema::createIndex(Table::SHIPPINGCATEGORIES, ['storeId']);
        Schema::createIndex(Table::SHIPPINGMETHODS, ['name']);
        Schema::createIndex(Table::SHIPPINGMETHODS, ['storeId']);
        Schema::createIndex(Table::SHIPPINGRULES, ['methodId']);
        Schema::createIndex(Table::SHIPPINGRULES, ['name']);
        Schema::createIndex(Table::SHIPPINGRULE_CATEGORIES, ['shippingCategoryId']);
        Schema::createIndex(Table::SHIPPINGRULE_CATEGORIES, ['shippingRuleId']);
        Schema::createIndex(Table::SHIPPINGZONES, ['name']);
        Schema::createIndex(Table::SHIPPINGZONES, ['storeId']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['dateCreated']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['dateExpired']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['gatewayId']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['nextPaymentDate']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['planId']);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['reference'], unique: true);
        Schema::createIndex(Table::SUBSCRIPTIONS, ['userId']);
        Schema::createIndex(Table::TAXRATES, ['storeId']);
        Schema::createIndex(Table::TAXRATES, ['taxCategoryId']);
        Schema::createIndex(Table::TAXRATES, ['taxZoneId']);
        Schema::createIndex(Table::TAXZONES, ['name']);
        Schema::createIndex(Table::TAXZONES, ['storeId']);
        Schema::createIndex(Table::TRANSACTIONS, ['gatewayId']);
        Schema::createIndex(Table::TRANSACTIONS, ['orderId']);
        Schema::createIndex(Table::TRANSACTIONS, ['parentId']);
        Schema::createIndex(Table::TRANSACTIONS, ['userId']);
        Schema::createIndex(Table::TRANSACTIONS, ['hash']);
        Schema::createIndex(Table::TRANSFERS, ['destinationLocationId']);
        Schema::createIndex(Table::TRANSFERS, ['originLocationId']);
        Schema::createIndex(Table::TRANSFERDETAILS, ['transferId']);
        Schema::createIndex(Table::TRANSFERDETAILS, ['inventoryItemId']);
        Schema::createIndex(Table::VARIANTS, ['primaryOwnerId']);
    }

    /**
     * Adds the foreign keys.
     */
    public function addForeignKeys(): void
    {
        Schema::table(Table::CATALOG_PRICING, fn (Blueprint $table) => $table->foreign('catalogPricingRuleId')->references('id')->on(Table::CATALOG_PRICING_RULES)->cascadeOnDelete());
        Schema::table(Table::CATALOG_PRICING, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(Table::PURCHASABLES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CATALOG_PRICING, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::CATALOG_PRICING, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->cascadeOnDelete());
        Schema::table(Table::CATALOG_PRICING_QUEUE, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CATALOG_PRICING_RULES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CATALOG_PRICING_RULES_USERS, fn (Blueprint $table) => $table->foreign('catalogPricingRuleId')->references('id')->on(Table::CATALOG_PRICING_RULES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CATALOG_PRICING_RULES_USERS, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::COUPONS, fn (Blueprint $table) => $table->foreign('discountId')->references('id')->on(Table::DISCOUNTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CUSTOMERS, fn (Blueprint $table) => $table->foreign('customerId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CUSTOMERS, fn (Blueprint $table) => $table->foreign('primaryBillingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::CUSTOMERS, fn (Blueprint $table) => $table->foreign('primaryPaymentSourceId')->references('id')->on(Table::PAYMENTSOURCES)->nullOnDelete());
        Schema::table(Table::CUSTOMERS, fn (Blueprint $table) => $table->foreign('primaryShippingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::CUSTOMER_DISCOUNTUSES, fn (Blueprint $table) => $table->foreign('customerId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::CUSTOMER_DISCOUNTUSES, fn (Blueprint $table) => $table->foreign('discountId')->references('id')->on(Table::DISCOUNTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DISCOUNTS, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DISCOUNT_CATEGORIES, fn (Blueprint $table) => $table->foreign('categoryId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DISCOUNT_CATEGORIES, fn (Blueprint $table) => $table->foreign('discountId')->references('id')->on(Table::DISCOUNTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DISCOUNT_PURCHASABLES, fn (Blueprint $table) => $table->foreign('discountId')->references('id')->on(Table::DISCOUNTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DISCOUNT_PURCHASABLES, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(Table::PURCHASABLES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::DONATIONS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::EMAILS, fn (Blueprint $table) => $table->foreign('pdfId')->references('id')->on(Table::PDFS)->nullOnDelete());
        Schema::table(Table::EMAILS, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::EMAILS, fn (Blueprint $table) => $table->foreign('renderSiteId')->references('id')->on(CraftTable::SITES)->nullOnDelete());
        Schema::table(Table::EMAIL_DISCOUNTUSES, fn (Blueprint $table) => $table->foreign('discountId')->references('id')->on(Table::DISCOUNTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::INVENTORYITEMS, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(Table::PURCHASABLES)->cascadeOnDelete());
        Schema::table(Table::INVENTORYLOCATIONS, fn (Blueprint $table) => $table->foreign('addressId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::INVENTORYLOCATIONS_STORES, fn (Blueprint $table) => $table->foreign('inventoryLocationId')->references('id')->on(Table::INVENTORYLOCATIONS)->cascadeOnDelete());
        Schema::table(Table::INVENTORYLOCATIONS_STORES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::INVENTORYTRANSACTIONS, fn (Blueprint $table) => $table->foreign('inventoryItemId')->references('id')->on(Table::INVENTORYITEMS)->cascadeOnDelete());
        Schema::table(Table::INVENTORYTRANSACTIONS, fn (Blueprint $table) => $table->foreign('inventoryLocationId')->references('id')->on(Table::INVENTORYLOCATIONS)->cascadeOnDelete());
        Schema::table(Table::INVENTORYTRANSACTIONS, fn (Blueprint $table) => $table->foreign('lineItemId')->references('id')->on(Table::LINEITEMS)->cascadeOnDelete());
        // NOTE: the legacy migration added this same FK twice (once here, once further down); only ported once.
        Schema::table(Table::INVENTORYTRANSACTIONS, fn (Blueprint $table) => $table->foreign('transferId')->references('id')->on(Table::TRANSFERS)->nullOnDelete());
        Schema::table(Table::INVENTORYTRANSACTIONS, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->nullOnDelete());
        Schema::table(Table::LINEITEMS, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete());
        Schema::table(Table::LINEITEMS, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete()->cascadeOnUpdate());
        Schema::table(Table::LINEITEMS, fn (Blueprint $table) => $table->foreign('shippingCategoryId')->references('id')->on(Table::SHIPPINGCATEGORIES)->cascadeOnUpdate());
        Schema::table(Table::LINEITEMS, fn (Blueprint $table) => $table->foreign('taxCategoryId')->references('id')->on(Table::TAXCATEGORIES)->cascadeOnUpdate());
        Schema::table(Table::LINEITEMSTATUSES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERADJUSTMENTS, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete());
        Schema::table(Table::ORDERHISTORIES, fn (Blueprint $table) => $table->foreign('newStatusId')->references('id')->on(Table::ORDERSTATUSES)->restrictOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERHISTORIES, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERHISTORIES, fn (Blueprint $table) => $table->foreign('prevStatusId')->references('id')->on(Table::ORDERSTATUSES)->restrictOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERHISTORIES, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERNOTICES, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('billingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('customerId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('estimatedBillingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('estimatedShippingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('gatewayId')->references('id')->on(Table::GATEWAYS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('orderStatusId')->references('id')->on(Table::ORDERSTATUSES)->restrictOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('paymentSourceId')->references('id')->on(Table::PAYMENTSOURCES)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('shippingAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::ORDERS, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERSTATUSES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERSTATUS_EMAILS, fn (Blueprint $table) => $table->foreign('emailId')->references('id')->on(Table::EMAILS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::ORDERSTATUS_EMAILS, fn (Blueprint $table) => $table->foreign('orderStatusId')->references('id')->on(Table::ORDERSTATUSES)->restrictOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PAYMENTCURRENCIES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PAYMENTSOURCES, fn (Blueprint $table) => $table->foreign('customerId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::PAYMENTSOURCES, fn (Blueprint $table) => $table->foreign('gatewayId')->references('id')->on(Table::GATEWAYS)->cascadeOnDelete());
        Schema::table(Table::PDFS, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::PLANS, fn (Blueprint $table) => $table->foreign('gatewayId')->references('id')->on(Table::GATEWAYS)->cascadeOnDelete());
        Schema::table(Table::PLANS, fn (Blueprint $table) => $table->foreign('planInformationId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('typeId')->references('id')->on(Table::PRODUCTTYPES)->cascadeOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('defaultVariantId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::PRODUCTTYPES, fn (Blueprint $table) => $table->foreign('fieldLayoutId')->references('id')->on(CraftTable::FIELDLAYOUTS)->nullOnDelete());
        Schema::table(Table::PRODUCTTYPES, fn (Blueprint $table) => $table->foreign('variantFieldLayoutId')->references('id')->on(CraftTable::FIELDLAYOUTS)->nullOnDelete());
        Schema::table(Table::PRODUCTTYPES, fn (Blueprint $table) => $table->foreign('structureId')->references('id')->on(CraftTable::STRUCTURES)->nullOnDelete());
        Schema::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, fn (Blueprint $table) => $table->foreign('productTypeId')->references('id')->on(Table::PRODUCTTYPES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, fn (Blueprint $table) => $table->foreign('shippingCategoryId', 'commerce_pts_shippingcategoryid_foreign')->references('id')->on(Table::SHIPPINGCATEGORIES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PRODUCTTYPES_SITES, fn (Blueprint $table) => $table->foreign('productTypeId')->references('id')->on(Table::PRODUCTTYPES)->cascadeOnDelete());
        Schema::table(Table::PRODUCTTYPES_SITES, fn (Blueprint $table) => $table->foreign('siteId')->references('id')->on(CraftTable::SITES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PRODUCTTYPES_TAXCATEGORIES, fn (Blueprint $table) => $table->foreign('productTypeId')->references('id')->on(Table::PRODUCTTYPES)->cascadeOnDelete());
        Schema::table(Table::PRODUCTTYPES_TAXCATEGORIES, fn (Blueprint $table) => $table->foreign('taxCategoryId')->references('id')->on(Table::TAXCATEGORIES)->cascadeOnDelete());
        Schema::table(Table::PURCHASABLES, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::PURCHASABLES, fn (Blueprint $table) => $table->foreign('taxCategoryId')->references('id')->on(Table::TAXCATEGORIES));
        // NOTE: the legacy migration added this same FK twice (once with just cascadeOnDelete, once with cascadeOnDelete+cascadeOnUpdate); only the more complete one is ported.
        Schema::table(Table::PURCHASABLES_STORES, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(Table::PURCHASABLES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PURCHASABLES_STORES, fn (Blueprint $table) => $table->foreign('shippingCategoryId')->references('id')->on(Table::SHIPPINGCATEGORIES)->nullOnDelete());
        Schema::table(Table::PURCHASABLES_STORES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::SALE_CATEGORIES, fn (Blueprint $table) => $table->foreign('categoryId')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SALE_CATEGORIES, fn (Blueprint $table) => $table->foreign('saleId')->references('id')->on(Table::SALES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SALE_PURCHASABLES, fn (Blueprint $table) => $table->foreign('purchasableId')->references('id')->on(Table::PURCHASABLES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SALE_PURCHASABLES, fn (Blueprint $table) => $table->foreign('saleId')->references('id')->on(Table::SALES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SALE_USERGROUPS, fn (Blueprint $table) => $table->foreign('saleId')->references('id')->on(Table::SALES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SALE_USERGROUPS, fn (Blueprint $table) => $table->foreign('userGroupId')->references('id')->on(CraftTable::USERGROUPS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::SHIPPINGCATEGORIES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::SHIPPINGMETHODS, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::SHIPPINGRULES, fn (Blueprint $table) => $table->foreign('methodId')->references('id')->on(Table::SHIPPINGMETHODS)->cascadeOnDelete());
        Schema::table(Table::SHIPPINGRULE_CATEGORIES, fn (Blueprint $table) => $table->foreign('shippingCategoryId')->references('id')->on(Table::SHIPPINGCATEGORIES)->cascadeOnDelete());
        Schema::table(Table::SHIPPINGRULE_CATEGORIES, fn (Blueprint $table) => $table->foreign('shippingRuleId')->references('id')->on(Table::SHIPPINGRULES)->cascadeOnDelete());
        Schema::table(Table::SHIPPINGZONES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::STORESETTINGS, fn (Blueprint $table) => $table->foreign('locationAddressId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::STORESETTINGS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::SUBSCRIPTIONS, fn (Blueprint $table) => $table->foreign('gatewayId')->references('id')->on(Table::GATEWAYS)->restrictOnDelete());
        Schema::table(Table::SUBSCRIPTIONS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::SUBSCRIPTIONS, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->nullOnDelete());
        Schema::table(Table::SUBSCRIPTIONS, fn (Blueprint $table) => $table->foreign('planId')->references('id')->on(Table::PLANS)->restrictOnDelete());
        Schema::table(Table::SUBSCRIPTIONS, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->cascadeOnDelete());
        Schema::table(Table::TAXRATES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::TAXRATES, fn (Blueprint $table) => $table->foreign('taxCategoryId')->references('id')->on(Table::TAXCATEGORIES)->cascadeOnUpdate());
        Schema::table(Table::TAXRATES, fn (Blueprint $table) => $table->foreign('taxZoneId')->references('id')->on(Table::TAXZONES)->cascadeOnUpdate());
        Schema::table(Table::TAXZONES, fn (Blueprint $table) => $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete());
        Schema::table(Table::TRANSACTIONS, fn (Blueprint $table) => $table->foreign('gatewayId')->references('id')->on(Table::GATEWAYS)->cascadeOnUpdate());
        Schema::table(Table::TRANSACTIONS, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete());
        Schema::table(Table::TRANSACTIONS, fn (Blueprint $table) => $table->foreign('parentId')->references('id')->on(Table::TRANSACTIONS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::TRANSACTIONS, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::ELEMENTS)->nullOnDelete());
        Schema::table(Table::TRANSFERS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::TRANSFERDETAILS, fn (Blueprint $table) => $table->foreign('transferId')->references('id')->on(Table::TRANSFERS)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::TRANSFERDETAILS, fn (Blueprint $table) => $table->foreign('inventoryItemId')->references('id')->on(Table::INVENTORYITEMS)->nullOnDelete()->cascadeOnUpdate());
        Schema::table(Table::VARIANTS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::VARIANTS, fn (Blueprint $table) => $table->foreign('primaryOwnerId')->references('id')->on(Table::PRODUCTS)->cascadeOnDelete());
    }

    /**
     * Inserts the default data.
     *
     * This is a fresh-install migration only (there is no upgrade path through this class), so — unlike the
     * legacy Yii2 version — there's no need to guard against a pre-existing project config, patch up an old
     * 5.0.0-beta.1 project config bug, or skip re-seeding a store/gateway that already exist. We also insert
     * rows directly rather than going through the Commerce services (e.g. Stores::saveStore()), since those
     * write through the project config system, which isn't guaranteed to apply synchronously in every context
     * this migration runs in (e.g. the Testbench-based test harness).
     */
    public function insertDefaultData(): void
    {
        $now = now()->toDateTimeString();

        // Default (primary) store
        $storeId = DB::table(Table::STORES)->insertGetId([
            'name' => 'Primary',
            'handle' => 'primary',
            'primary' => true,
            'currency' => 'USD',
            'orderReferenceFormat' => '{{number[:7]}}',
            'sortOrder' => 1,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => Str::uuid()->toString(),
        ]);

        // Map every existing site to the new store, using the site's own uid (there's only ever one
        // site-store mapping row per site, so it can safely share the site's uid).
        $sites = DB::table(CraftTable::SITES)->select(['id', 'uid'])->get();
        foreach ($sites as $site) {
            DB::table(Table::SITESTORES)->insert([
                'siteId' => $site->id,
                'storeId' => $storeId,
                'uid' => $site->uid,
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ]);
        }

        // Default payment currency for the store
        DB::table(Table::PAYMENTCURRENCIES)->insert([
            'storeId' => $storeId,
            'iso' => 'USD',
            'rate' => 1,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        // Default shipping category for the store
        DB::table(Table::SHIPPINGCATEGORIES)->insert([
            'storeId' => $storeId,
            'name' => 'General',
            'handle' => 'general',
            'default' => true,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        // Default order status for the store
        DB::table(Table::ORDERSTATUSES)->insert([
            'storeId' => $storeId,
            'name' => 'New',
            'handle' => 'new',
            'color' => 'green',
            'default' => true,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        // Default (Dummy) gateway
        DB::table(Table::GATEWAYS)->insert([
            // TODO: update to the CraftCms\Commerce\... FQCN once the Dummy gateway is migrated to src/
            'type' => 'craft\\commerce\\gateways\\Dummy',
            'name' => 'Dummy',
            'handle' => 'dummy',
            'isFrontendEnabled' => '1',
            'isArchived' => false,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        // Default tax category (global, not store-specific)
        DB::table(Table::TAXCATEGORIES)->insert([
            'name' => 'General',
            'handle' => 'general',
            'default' => true,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        // Default inventory location, assigned to the store
        $inventoryLocationId = DB::table(Table::INVENTORYLOCATIONS)->insertGetId([
            'handle' => 'default',
            'name' => 'Default',
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);

        DB::table(Table::INVENTORYLOCATIONS_STORES)->insert([
            'inventoryLocationId' => $inventoryLocationId,
            'storeId' => $storeId,
            'sortOrder' => 1,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ]);
    }

    public function down(): void
    {
        $this->task('Uninstall Craft Commerce', function (?Logger $logger = null) {
            $logger?->subLabel('Dropping tables...');
            $this->dropTables();
            $logger?->success('Tables dropped.');

            $logger?->subLabel('Removing field layouts...');
            $this->dropFieldLayouts();
            $logger?->success('Field layouts removed.');

            $logger?->subLabel('Removing project config...');
            ProjectConfig::remove('commerce');
            $logger?->success('Project config removed.');
        });
    }

    /**
     * Drops all of Commerce's tables.
     */
    public function dropTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->allTableNames() as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Deletes the field layouts belonging to Commerce's element types, including legacy
     * `craft\commerce\*` type strings left behind by installs that predate the Laravel port.
     */
    public function dropFieldLayouts(): void
    {
        DB::table(CraftTable::FIELDLAYOUTS)->whereIn('type', [
            Order::class,
            Product::class,
            Variant::class,
            'craft\\commerce\\elements\\Order',
            'craft\\commerce\\elements\\Product',
            'craft\\commerce\\elements\\Variant',
            // Subscription and Transfer field layouts predate/are pending the Laravel port —
            // these strings mirror the FQCNs `craft\commerce\elements\Subscription` (element
            // removed in 6.0) and `craft\commerce\elements\Transfer` (not yet ported to src/).
            'craft\\commerce\\elements\\Subscription',
            'craft\\commerce\\elements\\Transfer',
        ])->delete();
    }

    /** @return string[] */
    private function allTableNames(): array
    {
        return array_values((new ReflectionClass(Table::class))->getConstants());
    }
}
