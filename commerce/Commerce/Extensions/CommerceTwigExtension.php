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

        return $returnArray;
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
