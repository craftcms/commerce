<?php
namespace Stripey;

use Craft\Craft;
use Pimple\Container;

class Stripey extends Container
{

	public function __get($memberName)
	{
		$craftName = 'stripey_' . strtolower($memberName);

		return Craft::app()->$craftName;
	}

	/**
	 * Return singleton instance of the Stripey Container
	 *
	 * @return Stripey
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
 * Returns the Stripey Plugin equivalent to webApp class that serves up all the services.
 *
 * @return Stripey
 */
function stripey()
{
	return \Stripey\Stripey::app();
}