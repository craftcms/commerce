<?php

namespace Commerce\Seed;

use Craft\Commerce_CurrencyRecord;
use Craft\Commerce_OrderSettingsModel;
use Craft\Commerce_OrderStatusModel;
use Craft\Commerce_PaymentMethodModel;
use Craft\Commerce_ProductModel;
use Craft\Commerce_ProductTypeModel;
use Craft\Commerce_SettingsModel;
use Craft\Commerce_ShippingMethodRecord;
use Craft\Commerce_ShippingRuleRecord;
use Craft\Commerce_TaxCategoryModel;
use Craft\Commerce_VariantModel;
use Craft\DateTime;
use Craft\FieldLayoutModel;

/**
 * Class Commerce_InstallSeeder
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Seed
 * @since     1.0
 */
class Commerce_InstallSeeder implements Commerce_SeederInterface
{

    public function seed()
    {
        $this->defaultCurrency();
        $this->defaultShippingMethod();
        $this->defaultTaxCategories();
        $this->defaultOrderSettings();
        $this->defaultProductTypes();
        $this->defaultProducts();
        $this->paymentMethods();
        $this->defaultSettings();
    }

    public function defaultCurrency()
    {
        $method = new Commerce_CurrencyRecord();
        $method->name = 'Default Currency';
        $method->iso = 'USD';
        $method->rate = 1;
        $method->default = true;
        $method->save();
    }

    /**
     * Shipping Methods
     */
    private function defaultShippingMethod()
    {
        $method = new Commerce_ShippingMethodRecord();
        $method->name = 'Free Shipping';
        $method->handle = 'freeShipping';
        $method->enabled = true;
        $method->save();

        $rule = new Commerce_ShippingRuleRecord();
        $rule->methodId = $method->id;
        $rule->description = "All Countries, free shipping.";
        $rule->name = "Free Everywhere ";
        $rule->enabled = true;
        $rule->save();
    }

    /**
     * @throws \Craft\Exception
     */
    private function defaultTaxCategories()
    {
        $category = Commerce_TaxCategoryModel::populateModel([
            'name' => 'General',
            'handle' => 'general',
            'default' => 1,
        ]);

        \Craft\craft()->commerce_taxCategories->saveTaxCategory($category);
    }

    /**
     * @throws \Exception
     */
    private function defaultOrderSettings()
    {

        $orderSettings = new Commerce_OrderSettingsModel;
        $orderSettings->name = 'Order';
        $orderSettings->handle = 'order';

        // Set the field layout
        $fieldLayout = \Craft\craft()->fields->assembleLayout([], []);
        $fieldLayout->type = 'Commerce_Order';
        $orderSettings->setFieldLayout($fieldLayout);

        \Craft\craft()->commerce_orderSettings->saveOrderSetting($orderSettings);

        $data = [
            'name' => 'Processing',
            'handle' => 'processing',
            'color' => 'green',
            'default' => true
        ];
        $defaultStatus = Commerce_OrderStatusModel::populateModel($data);
        \Craft\craft()->commerce_orderStatuses->saveOrderStatus($defaultStatus, []);

        $data = [
            'name' => 'Shipped',
            'handle' => 'shipped',
            'color' => 'blue',
            'default' => false
        ];

        $status = Commerce_OrderStatusModel::populateModel($data);

        \Craft\craft()->commerce_orderStatuses->saveOrderStatus($status, []);
    }

    /**
     * @throws \Craft\Exception
     * @throws \Exception
     */
    private function defaultProductTypes()
    {
        $productType = new Commerce_ProductTypeModel;
        $productType->name = 'Clothing';
        $productType->handle = 'clothing';
        $productType->hasDimensions = true;
        $productType->hasUrls = true;
        $productType->hasVariants = false;
        $productType->hasVariantTitleField = false;
        $productType->titleFormat = "{product.title}";
        $productType->template = 'commerce/products/_product';

        $fieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Product']);
        \Craft\craft()->fields->saveLayout($fieldLayout);
        $productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

        $variantFieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Variant']);
        \Craft\craft()->fields->saveLayout($variantFieldLayout);
        $productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

        \Craft\craft()->commerce_productTypes->saveProductType($productType);

        $productTypeLocales = \Craft\craft()->i18n->getSiteLocaleIds();

        foreach ($productTypeLocales as $locale) {
            \Craft\craft()->db->createCommand()->insert('commerce_producttypes_i18n', [
                'productTypeId' => $productType->id,
                'locale' => $locale,
                'urlFormat' => 'commerce/products/{slug}'
            ]);
        }
    }

    /**
     * @throws \Craft\Exception
     * @throws \Craft\HttpException
     * @throws \Exception
     */
    private function defaultProducts()
    {
        $productTypes = \Craft\craft()->commerce_productTypes->getAllProductTypes();

        $products = [
            'A New Toga',
            'Parka with Stripes on Back',
            'Romper for a Red Eye',
            'The Fleece Awakens'
        ];

        $count = 1;

        foreach ($products as $productName) {
            /** @var Commerce_VariantModel $variant */
            $variant = Commerce_VariantModel::populateModel([
                'sku' => $productName,
                'price' => (10 * $count++),
                'unlimitedStock' => 1,
                'isDefault' => 1,
            ]);

            /** @var Commerce_ProductModel $product */
            $product = Commerce_ProductModel::populateModel([
                'typeId' => $productTypes[0]->id,
                'enabled' => 1,
                'postDate' => new DateTime(),
                'expiryDate' => null,
                'promotable' => 1,
                'taxCategoryId' => \Craft\craft()->commerce_taxCategories->getDefaultTaxCategoryId(),
            ]);

            $product->getContent()->title = $productName;
            $variant->setProduct($product);
            $product->setVariants([$variant]);

            \Craft\craft()->commerce_products->saveProduct($product);
        }
    }

    private function paymentMethods()
    {
        /** @var Dummy_GatewayAdapter $adapter */
        $adapter = \Craft\craft()->commerce_gateways->getAllGateways()['Dummy'];

        $model = new Commerce_PaymentMethodModel;
        $model->class = $adapter->handle();
        $model->name = $adapter->displayName();
        $model->settings = $adapter->getGateway()->getDefaultParameters();
        $model->frontendEnabled = true;

        \Craft\craft()->commerce_paymentMethods->savePaymentMethod($model);
    }


    private function defaultSettings()
    {
        $settings = new Commerce_SettingsModel();
        $settings->orderPdfPath = 'commerce/_pdf/order';
        \Craft\craft()->commerce_settings->saveSettings($settings);
    }

}
