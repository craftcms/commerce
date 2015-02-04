<?php
namespace Craft;

/**
 * Class Market_PlansService
 *
 * @package Craft
 */
class Market_PlansService extends BaseApplicationComponent
{

	/**
	 *
	 * @return mixed
	 */
	public function getPlans()
	{
		$plans = \Market\Market::app()['stripe']->plans()->all();

		return $plans['data'];
	}
} 