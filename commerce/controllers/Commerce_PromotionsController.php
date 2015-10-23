<?php
namespace Craft;

/**
 * Class Commerce_PromotionsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PromotionsController extends Commerce_BaseAdminController
{
    public function actionIndex()
    {
        $this->redirect('commerce/promotions/sales');
    }
}