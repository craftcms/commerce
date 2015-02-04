<?php
namespace Craft;

/**
 * Class Stripey_PlansService
 *
 * @package Craft
 */
class Stripey_PlansService extends BaseApplicationComponent
{

	/**
	 *
	 * @return mixed
	 */
	public function getPlans()
	{
		$plans = \Stripey\Stripey::app()['stripe']->plans()->all();

		return $plans['data'];
	}
} 