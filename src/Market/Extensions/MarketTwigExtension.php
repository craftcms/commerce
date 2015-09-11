<?php
namespace Market\Extensions;

use CNumberFormatter;

class MarketTwigExtension extends \Twig_Extension
{

	public function getName()
	{
		return 'Craft Commerce Twig Extension';
	}

	public function getFilters()
	{
		$returnArray['marketCurrency'] = new \Twig_Filter_Method($this, 'currency');
		$returnArray['marketDecimal'] = new \Twig_Filter_Method($this, 'decimal');

		return $returnArray;
	}

	/**
	 * @param            $string
	 * @param bool|false $withGroupSymbol
	 *
	 * @return mixed
	 */
	public function decimal($string, $withGroupSymbol = false)
	{
		return \Craft\craft()->numberFormatter->formatDecimal($string, $withGroupSymbol);
	}


	/**
	 * @param           $content
	 * @param bool|true $stripZeroCents
	 *
	 * @return mixed
	 */
	public function currency($content, $stripZeroCents = true)
	{
		$code = \Craft\craft()->market_settings->getOption('defaultCurrency');
		return \Craft\craft()->numberFormatter->formatCurrency($content, strtoupper($code), $stripZeroCents);
	}
}