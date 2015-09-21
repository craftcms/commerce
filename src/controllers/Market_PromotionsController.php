<?php
namespace Craft;

/**
 * Class Market_PromotionsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_PromotionsController extends Market_BaseController
{
	public function actionIndex ()
	{
		$this->redirect('market/promotions/sales');
	}
}