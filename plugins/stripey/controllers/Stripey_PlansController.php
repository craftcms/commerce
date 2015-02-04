<?php

namespace Craft;

class Stripey_PlansController extends Stripey_BaseController
{

	public function init()
	{
		parent::init();
	}

	public function actionIndex()
	{
		$plans = craft()->stripey_plans->getPlans();
		$this->renderTemplate('stripey/plans/index', compact('plans'));
	}
} 