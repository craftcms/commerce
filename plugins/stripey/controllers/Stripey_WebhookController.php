<?php

namespace Craft;

class Stripey_WebhookController extends BaseController
{
	protected $allowAnonymous = true;

	public function actionPost()
	{
		$this->requirePostRequest();
		$this->returnJson(array('hello', 'hi'));
		$data = craft()->request->getRawBody();
		StripeyPlugin::log($data);
		craft()->end(200);
	}
}