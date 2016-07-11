<?php
namespace Commerce\Extensions;

/**
 * Class CommerceTwigExtension
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
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
        $returnArray['commercePercent'] = new \Twig_Filter_Method($this, 'percent');
        $returnArray['commerceCurrencyCovert'] = new \Twig_Filter_Method($this, 'currencyCovert');
        $returnArray['cc'] = new \Twig_Filter_Method($this, 'currencyCovert');

        return $returnArray;
    }

    public function currencyCovert($amount, $currency)
    {
        $currency = \Craft\craft()->commerce_currencies->getCurrencyByIso($currency);

        if(!$currency)
        {
            throw new \Twig_Error(\Craft\Craft::t('Not a valid currency code for conversion'));
        }

        return $amount * $currency->rate;
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function percent($string)
    {
        $localeData = \Craft\craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        return $this->decimal($string) . "" . $percentSign;
    }

}
