<?php

namespace Craft;

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