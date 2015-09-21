<?php
namespace Craft;

/**
 * Class Commerce_PromotionsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PromotionsController extends Commerce_BaseController
{
	public function actionIndex ()
	{
		$this->redirect('commerce/promotions/sales');
	}
}