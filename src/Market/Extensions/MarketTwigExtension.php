<?php

namespace Market\Extensions;

/**
 * Adding custom filters to twig
 *
 * Class MarketTwigExtension
 *
 * @package Market\Extensions
 */
class MarketTwigExtension extends \Twig_Extension
{
	public function getName()
	{
		return __CLASS__;
	}

	public function getFilters()
	{
		return [
			'makeLabel' => new \Twig_Filter_Method($this, 'makeLabelFilter'),
		];
	}

	/**
	 * Copied from CModel->generateAttributeLabel
	 * Convert "someField1" => "Some Field1"
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function makeLabelFilter($string)
	{
		return ucwords(trim(strtolower(str_replace(['-', '_', '.'], ' ', preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $string)))));
	}
}