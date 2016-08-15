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
        $returnArray['json_encode_filtered'] = new \Twig_Filter_Method($this, 'jsonEncodeFiltered');
        
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

    public function jsonEncodeFiltered($input)
    {
        $array = $this->recursiveSanitizeArray($input);

        return json_encode($array);
    }

    public static function sanitize($input)
    {
        $sanitized = $input;

        if ( ! is_int($sanitized)) {
            $sanitized = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);
        } else {
            $newValue = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);

            if (is_numeric($newValue))
            {
                $sanitized = intval($newValue);
            } else {
                $sanitized = $newValue;
            }
        }

        return $sanitized;
    }

    public function recursiveSanitizeArray($array)
    {
        $finalArray = [];

        foreach ($array as $key => $value)
        {
            $newKey = self::sanitize($key);

            if (is_array($value))
            {
                $finalArray[$newKey] = $this->recursiveSanitizeArray($value);
            }
            else
            {
                $finalArray[$newKey] = self::sanitize($value);
            }
        }

        return $finalArray;
    }

}
