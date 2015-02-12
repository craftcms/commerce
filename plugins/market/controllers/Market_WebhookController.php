<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_WebhookController extends Market_BaseController
{
	protected $allowAnonymous = true;

	public function actionPost()
	{
		$this->requirePostRequest();
		$this->returnJson(array('hello', 'hi'));
		$data = craft()->request->getRawBody();
		MarketPlugin::log($data);
		craft()->end(200);
	}
}