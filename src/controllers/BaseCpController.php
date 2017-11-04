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
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        // All system setting actions require access to commerce
        $this->requirePermission('accessPlugin-commerce');

        $this->getView()->registerAssetBundle(CommerceCpAsset::class);

        parent::init();
    }
}
