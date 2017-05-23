<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\web\assets\commercecp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use yii\web\JqueryAsset;

/**
 * Asset bundle for the Control Panel
 */
class CommerceCpAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__.'/dist';

        $this->depends = [
            CpAsset::class,
            JqueryAsset::class,
        ];

        $this->css[] = 'css/CommerceRevenueWidget.css';
        $this->css[] = 'css/charts-explorer.css';
        $this->css[] = 'css/commerce.css';
        $this->css[] = 'css/order.css';
        $this->css[] = 'css/product.css';
        $this->css[] = 'css/registration.css';

        $this->js[] = 'js/Commerce.js';
        $this->js[] = 'js/CommerceAddressBox.js';
        $this->js[] = 'js/CommerceEditAddressModal.js';
        $this->js[] = 'js/CommerceOrderEdit.js';
        $this->js[] = 'js/CommerceOrderIndex.js';
        $this->js[] = 'js/CommerceOrderTableView.js';
        $this->js[] = 'js/CommerceOrdersWidgetSettings.js';
        $this->js[] = 'js/CommercePaymentModal.js';
        $this->js[] = 'js/CommerceProductIndex.js';
        $this->js[] = 'js/CommerceRegistrationForm.js';
        $this->js[] = 'js/CommerceRevenueWidget.js';
        $this->js[] = 'js/CommerceShippingItemRatesValuesInput.js';
        $this->js[] = 'js/CommerceUpdateOrderStatusModal.js';
        $this->js[] = 'js/CommerceVariantValuesInput.js';
        $this->js[] = 'js/TableRowAdditionalInfoIcon.js';
        $this->js[] = 'js/VariantMatrix.js';

        parent::init();
    }
}
