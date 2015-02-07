<?php

namespace Craft;

class Market_PlansController extends Market_BaseController
{

	public function init()
	{
		parent::init();
	}

	public function actionIndex()
	{
		$plans = craft()->market_plans->getPlans();
		$this->renderTemplate('market/plans/index', compact('plans'));
	}
} 