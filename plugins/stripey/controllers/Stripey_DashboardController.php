<?php

namespace Craft;

class Stripey_DashboardController extends Stripey_BaseController
{

	public function actionIndex()
	{
		$variables = array();
		$this->renderTemplate('stripey/index', $variables);
	}

	public function actionSetup()
	{
		$variables = array();
		$this->renderTemplate('stripey/setup', $variables);
	}
} 