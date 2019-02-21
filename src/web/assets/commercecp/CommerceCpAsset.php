<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\commercecp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use yii\web\JqueryAsset;

/**
 * Asset bundle for the Control Panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CommerceCpAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            JqueryAsset::class,
        ];

        $this->css[] = 'css/charts-explorer.css';
        $this->css[] = 'css/commerce.css';
        $this->css[] = 'css/order.css';
        $this->css[] = 'css/subscriptions.css';

        $this->js[] = 'js/Commerce.js';
        $this->js[] = 'js/CommerceAddressBox.js';
        $this->js[] = 'js/CommerceEditAddressModal.js';
        $this->js[] = 'js/CommerceOrderEdit.js';
        $this->js[] = 'js/CommerceOrderIndex.js';
        $this->js[] = 'js/CommerceOrderTableView.js';
        $this->js[] = 'js/CommercePaymentModal.js';
        $this->js[] = 'js/CommerceShippingItemRatesValuesInput.js';
        $this->js[] = 'js/CommerceSubscriptionIndex.js';
        $this->js[] = 'js/CommerceUpdateOrderStatusModal.js';
        $this->js[] = 'js/CommerceVariantValuesInput.js';
        $this->js[] = 'js/TableRowAdditionalInfoIcon.js';

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('commerce', [
                'New {productType} product',
                'New product',
                'Update Order Status',
                'Message',
                'Status change message',
                'Update',
                'Cancel',
                'First Name',
                'Last Name',
                'Address Line 1',
                'Address Line 2',
                'City',
                'Zip Code',
                'Phone',
                'Alternative Phone',
                'Phone (Alt)',
                'Business Name',
                'Business Tax ID',
                'Country',
                'State',
                'Update Address',
                'New',
                'Edit',
                'Add Address',
                'Add',
                'Update',
                'No Address',
            ]);
        }
    }
}
