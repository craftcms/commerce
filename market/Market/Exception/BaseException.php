<?php
namespace Market\Exception;

use Craft\Exception;
use Craft\MarketPlugin;

class BaseException Extends Exception
{

	// Public Methods
	// =========================================================================

	/**
	 * @param     $message
	 * @param int $code
	 *
	 * @return Exception
	 */
	public function __construct($message, $code = 0)
	{
		MarketPlugin::log($message, LogLevel::Error);
		parent::__construct($message, $code);
	}
} 