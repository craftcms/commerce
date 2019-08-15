<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Category;
use craft\errors\CategoryGroupNotFoundException;
use craft\fields\Categories;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Throwable;

/**
 * m171010_170000_stock_location
 */
class m171202_180000_promotions_for_all_purchasables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $salesProducts = (new Query())
            ->select(['saleId', 'productId'])
            ->from(['{{%commerce_sale_products}}'])
            ->all();

        $salesProductIds = (new Query())
            ->select(['productId'])
            ->from(['{{%commerce_sale_products}}'])
            ->distinct()
            ->column();

        $salesProductTypes = (new Query())
            ->select(['saleId', 'productTypeId'])
            ->from(['{{%commerce_sale_producttypes}}'])
            ->all();

        $discountsProducts = (new Query())
            ->select(['discountId', 'productId'])
            ->from(['{{%commerce_discount_products}}'])
            ->all();

        $discountsProductIds = (new Query())
            ->select(['discountId'])
            ->from(['{{%commerce_discount_products}}'])
            ->distinct()
            ->column();

        $discountsProductTypes = (new Query())
            ->select(['discountId', 'productTypeId'])
            ->from(['{{%commerce_discount_producttypes}}'])
            ->all();

        // Get all Product Types
        $productTypes = (new Query())
            ->select(['id', 'name', 'handle'])
            ->from(['{{%commerce_producttypes}}'])
            ->all();

        // Create a category group for the product types sales and discounts to link to
        $group = $this->_createCategoryGroup();

        if (!$group) {
            return false;
        }

        $newCategoriesByProductTypId = [];

        // Create a category for each product type
        foreach ($productTypes as $productType) {

            $category = new Category();
            $category->groupId = $group->id;
            $category->slug = $productType['handle'];
            $category->enabled = true;
            $category->title = $productType['name'];

            if (!Craft::$app->getElements()->saveElement($category)) {
                return false;
            }

            // create a productTypeId -> categoryId map
            $newCategoriesByProductTypId[$productType['id']] = $category->id;
        }

        if ($productTypes) {

            $field = $this->_createCategoryFieldsOnProducts($group, $productTypes);
            $db = Craft::$app->getDb();

            // Update all product's `promotionCategories` field we created  with the product types category we created from the product type.
            $products = Product::find()->limit(null);

            foreach ($products->each() as $product) {
                $data = [
                    'fieldId' => $field->id,
                    'sourceId' => $product->id,
                    'targetId' => $newCategoriesByProductTypId[$product->getType()->id],
                    'sortOrder' => 1,
                    'sourceSiteId' => $product->siteId
                ];
                $db->createCommand()
                    ->insert('{{%relations}}', $data)
                    ->execute();
            }
        }


        // Replace discount_producttypes with discount_categories
        $newDiscountsCategories = [];
        foreach ($discountsProductTypes as $discountsProductType) {

            $discountExists = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_discounts}}'])
                ->where(['id' => $discountsProductType['discountId']])
                ->exists();

            if ($discountExists && $categoryId = ($newCategoriesByProductTypId[$discountsProductType['productTypeId']] ?? null)) {
                $newDiscountsCategories[] = [$discountsProductType['discountId'], $categoryId];
            }
        }

        $this->createTable('{{%commerce_discount_categories}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->batchInsert('{{%commerce_discount_categories}}', ['discountId', 'categoryId'], $newDiscountsCategories);

        $this->createIndex($this->db->getIndexName('{{%commerce_discount_categories}}', 'discountId,categoryId', true), '{{%commerce_discount_categories}}', 'discountId,categoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_categories}}', 'categoryId', false), '{{%commerce_discount_categories}}', 'categoryId', false);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_categories}}', 'categoryId'), '{{%commerce_discount_categories}}', 'categoryId', '{{%categories}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_categories}}', 'discountId'), '{{%commerce_discount_categories}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');

        // So long product type conditions on discounts
        MigrationHelper::dropTable('{{%commerce_discount_producttypes}}');


        //--------------------------------------------------------------------


        // Replace sale_producttype with sale_categories
        $newSalesCategories = [];
        foreach ($salesProductTypes as $salesProductType) {

            $saleExists = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_sales}}'])
                ->where(['id' => $salesProductType['saleId']])
                ->exists();

            if ($saleExists && $categoryId = ($newCategoriesByProductTypId[$salesProductType['productTypeId']] ?? null)) {
                $newSalesCategories[] = [$salesProductType['saleId'], $categoryId];
            }
        }

        $this->createTable('{{%commerce_sale_categories}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->batchInsert('{{%commerce_sale_categories}}', ['saleId', 'categoryId'], $newSalesCategories);

        $this->createIndex($this->db->getIndexName('{{%commerce_sale_categories}}', 'saleId,categoryId', true), '{{%commerce_sale_categories}}', 'saleId,categoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_categories}}', 'categoryId', false), '{{%commerce_sale_categories}}', 'categoryId', false);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_categories}}', 'categoryId'), '{{%commerce_sale_categories}}', 'categoryId', '{{%categories}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_categories}}', 'saleId'), '{{%commerce_sale_categories}}', 'saleId', '{{%commerce_sales}}', 'id', 'CASCADE', 'CASCADE');

        // So long product type condition on sales
        MigrationHelper::dropTable('{{%commerce_sale_producttypes}}');

        // Replace sales_products with sales_purchasables
        $this->createTable('{{%commerce_sale_purchasables}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Get all variant IDs with their product ID
        $variantIds = (new Query())
            ->select(['id', 'productId'])
            ->from(['{{%commerce_variants}}'])
            ->where(['in', 'productId', $salesProductIds])
            ->pairs();

        $newSalesPurchasables = [];
        // Loop through current sales, and for any product linked to a sale, create an array of product variantIds.
        foreach ($salesProducts as $salesProduct) {
            $saleExists = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_sales}}'])
                ->where(['id' => $salesProduct['saleId']])
                ->exists();

            // The variant is the purchasable, so link to the variant
            foreach ($variantIds as $variantId => $productId) {
                if ($salesProduct['productId'] == $productId && $saleExists) {
                    $newSalesPurchasables[] = ['saleId' => $salesProduct['saleId'], 'purchasableId' => $variantId, 'purchasableType' => Variant::class];
                }
            }
        }

        Craft::$app->getCache()->set('commerce_sale_purchasables_001', Json::encode($newSalesPurchasables));

        MigrationHelper::dropTable('{{%commerce_sale_products}}');

        $this->createIndex($this->db->getIndexName('{{%commerce_sale_purchasables}}', 'saleId,purchasableId', true), '{{%commerce_sale_purchasables}}', 'saleId,purchasableId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_purchasables}}', 'purchasableId', false), '{{%commerce_sale_purchasables}}', 'purchasableId', false);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_purchasables}}', 'purchasableId'), '{{%commerce_sale_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_purchasables}}', 'saleId'), '{{%commerce_sale_purchasables}}', 'saleId', '{{%commerce_sales}}', 'id', 'CASCADE', 'CASCADE');

        $this->renameColumn('{{%commerce_sales}}', 'allProducts', 'allPurchasables');
        $this->renameColumn('{{%commerce_sales}}', 'allProductTypes', 'allCategories');

        // Replace discounts_products with discounts_purchasables
        $this->createTable('{{%commerce_discount_purchasables}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Get all variant IDs with their product ID
        $variantIds = (new Query())
            ->select(['id', 'productId'])
            ->from(['{{%commerce_variants}}'])
            ->where(['in', 'productId', $discountsProductIds])
            ->pairs();

        $newDiscountsPurchasables = [];
        // Loop through current discounts, and for any product linked to a discount, create an array of product variantIds.
        foreach ($discountsProducts as $discountsProduct) {
            $discountExists = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_discounts}}'])
                ->where(['id' => $discountsProduct['discountId']])
                ->exists();

            // The variant is the purchasable, so link to the variant
            foreach ($variantIds as $variantId => $productId) {
                if ($discountsProduct['productId'] == $productId && $discountExists) {
                    $newDiscountsPurchasables[] = ['discountId' => $discountsProduct['discountId'], 'purchasableId' => $variantId, 'purchasableType' => Variant::class];
                }
            }
        }

        Craft::$app->getCache()->set('commerce_discount_purchasables_001', Json::encode($newDiscountsPurchasables));

        MigrationHelper::dropTable('{{%commerce_discount_products}}');

        $this->createIndex($this->db->getIndexName('{{%commerce_discount_purchasables}}', 'discountId,purchasableId', true), '{{%commerce_discount_purchasables}}', 'discountId,purchasableId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_purchasables}}', 'purchasableId', false), '{{%commerce_discount_purchasables}}', 'purchasableId', false);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_purchasables}}', 'purchasableId'), '{{%commerce_discount_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_purchasables}}', 'discountId'), '{{%commerce_discount_purchasables}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');

        $this->renameColumn('{{%commerce_discounts}}', 'allProducts', 'allPurchasables');
        $this->renameColumn('{{%commerce_discounts}}', 'allProductTypes', 'allCategories');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171010_170000_stock_location cannot be reverted.\n";

        return false;
    }

    /**
     * @return bool|CategoryGroup
     * @throws Throwable
     * @throws CategoryGroupNotFoundException
     */
    private function _createCategoryGroup()
    {
        $group = new CategoryGroup();

        $group->name = 'Promotions';
        $group->handle = 'promotions' . random_int(1, 10);
        $group->maxLevels = 0;

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {

            $siteSettings = new CategoryGroup_SiteSettings();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = false;
            $siteSettings->uriFormat = null;
            $siteSettings->template = null;

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $group->setSiteSettings($allSiteSettings);

        $fieldLayout = new FieldLayout();
        $fieldLayout->type = Category::class;
        $group->setFieldLayout($fieldLayout);

        if (Craft::$app->getCategories()->saveGroup($group)) {
            return $group;
        }

        return false;
    }

    /**
     * @param $categoryGroup
     * @param $productTypes
     * @return $field
     * @throws Throwable
     */
    private function _createCategoryFieldsOnProducts($categoryGroup, array $productTypes)
    {
        $fieldsService = Craft::$app->getFields();

        $group = new FieldGroup();
        $group->name = 'Commerce Promotion Categories';
        $fieldsService->saveGroup($group);

        $settings = ['source' => 'group:' . $categoryGroup->id, 'branchLimit' => '', 'selectionLabel' => 'Add a promotion category'];

        /** @var Categories $field */
        $field = $fieldsService->createField([
            'type' => Categories::class,
            'id' => null,
            'groupId' => $group->id,
            'name' => 'Promotion Categories',
            'handle' => 'promotionCategories',
            'instructions' => 'Categories used for sales and discount promotions.',
            'translationMethod' => Field::TRANSLATION_METHOD_NONE,
            'translationKeyFormat' => null,
            'settings' => $settings
        ]);

        $result = Craft::$app->getFields()->saveField($field);

        foreach ($productTypes as $productType) {
            $productTypeModel = Plugin::getInstance()->getProductTypes()->getProductTypeById($productType['id']);
            /** @var ProductType $productTypeModel */
            $fieldLayout = $productTypeModel->getProductFieldLayout();

            $tabs = $fieldLayout->getTabs();

            $newTab = new FieldLayoutTab([
                'name' => 'Promotions',
                'layoutId' => $fieldLayout->id,
                'sortOrder' => 99
            ]);

            $field->required = false;
            $field->sortOrder = 99;

            $newTab->setFields([$field]);

            $tabs[] = $newTab;

            $fieldLayout->setTabs($tabs);

            Craft::$app->getFields()->saveLayout($fieldLayout);
        }

        return $field;
    }
}
