<?php
namespace Commerce\Extensions;

class CommerceTwigExtension extends \Twig_Extension
{

	/**
	 * @return string
	 */
	public function getName ()
	{
		return 'Craft Commerce Twig Extension';
	}

	/**
	 * @return mixed
	 */
	public function getFilters ()
	{
		$returnArray['commerceCurrency'] = new \Twig_Filter_Method($this, 'currency');
		$returnArray['commerceDecimal'] = new \Twig_Filter_Method($this, 'decimal');

		return $returnArray;
	}

	/**
	 * @param            $string
	 * @param bool|false $withGroupSymbol
	 *
	 * @return mixed
	 */
	public function decimal ($string, $withGroupSymbol = false)
	{
		return \Craft\craft()->numberFormatter->formatDecimal($string, $withGroupSymbol);
	}


	/**
	 * @param           $content
	 * @param bool|true $stripZeroCents
	 *
	 * @return mixed
	 */
	public function currency ($content, $stripZeroCents = false)
	{
		$code = \Craft\craft()->commerce_settings->getOption('defaultCurrency');

		return \Craft\craft()->numberFormatter->formatCurrency($content, strtoupper($code), $stripZeroCents);
	}
}