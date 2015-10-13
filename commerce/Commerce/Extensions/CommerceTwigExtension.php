<?php
namespace Commerce\Extensions;

/**
 * Class CommerceTwigExtension
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   Commerce\Extensions
 * @since     1.0
 */
class CommerceTwigExtension extends \Twig_Extension
{

    /**
     * @return string
     */
    public function getName()
    {
        return 'Craft Commerce Twig Extension';
    }

    /**
     * @return mixed
     */
    public function getFilters()
    {
        $returnArray['commerceCurrency'] = new \Twig_Filter_Method($this, 'currency');
        $returnArray['commerceDecimal'] = new \Twig_Filter_Method($this, 'decimal');
        $returnArray['commercePercent'] = new \Twig_Filter_Method($this, 'percent');

        return $returnArray;
    }

    /**
     * @param            $string
     *
     * @return mixed
     */
    public function percent($string)
    {
        $localeData = \Craft\craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        return $this->decimal($string) . "" . $percentSign;
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
    public function currency($content, $stripZeroCents = false)
    {
        $code = \Craft\craft()->commerce_settings->getOption('defaultCurrency');

        return \Craft\craft()->numberFormatter->formatCurrency($content, strtoupper($code), $stripZeroCents);
    }
}