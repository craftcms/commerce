<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use yii\web\HttpException;

/**
 * Class BaseCp
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class BaseCpController extends BaseController
{
    protected $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc BaseController::init()
     *
     * @throws HttpException
     * @return null
     */
    public function init()
    {
        // All system setting actions require access to commerce
        $this->requirePermission('accessPlugin-commerce');

        $view = Craft::$app->getView();

        $view->registerAssetBundle(CommerceCpAsset::class);
        $view->registerTranslations('commerce',
            [
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
                'No Address'
            ]
        );
    }
}
