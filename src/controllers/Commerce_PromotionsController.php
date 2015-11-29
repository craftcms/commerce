<?php
namespace Craft;

/**
 * Class Commerce_PromotionsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PromotionsController extends Commerce_BaseCpController
{
    public function actionIndex()
    {
        $this->redirect('commerce/promotions/sales');
    }
}
