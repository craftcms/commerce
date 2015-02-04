<?php
namespace Market;

use Craft\Craft;
use Pimple\Container;

class Market extends Container
{

	public function __get($memberName)
	{
		$craftName = 'market_' . strtolower($memberName);

		return Craft::app()->$craftName;
	}

	/**
	 * Return singleton instance of the Market Container
	 *
	 * @return Market
	 */
	public static function app()
	{
		static $inst = NULL;
		if ($inst === NULL) {
			$inst = new self();
		}

		return $inst;
	}

}


/**
 * Returns the Market Plugin equivalent to webApp class that serves up all the services.
 *
 * @return Market
 */
function market()
{
	return \Market\Market::app();
}