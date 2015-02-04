<?php

namespace Craft;

class Market_DashboardController extends Market_BaseController
{

	public function actionIndex()
	{
		$variables = array();
		$this->renderTemplate('market/index', $variables);
	}

	public function actionSetup()
	{
		$variables = array();
		$this->renderTemplate('market/setup', $variables);
	}
} 