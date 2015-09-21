<?php
namespace Commerce\Exception;

use Craft\Exception;
use Craft\CommercePlugin;

class BaseException Extends Exception
{

	// Public Methods
	// =========================================================================

	/**
	 * @param string $message
	 * @param int    $code
	 */
	public function __construct ($message, $code = 0)
	{
		CommercePlugin::log($message, LogLevel::Error);
		parent::__construct($message, $code);
	}
} 