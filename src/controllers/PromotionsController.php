<?php

namespace craft\commerce\controllers;

/**
 * Class Promotions Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class PromotionsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $this->redirect('commerce/promotions/sales');
    }
}
