<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\twig;

use Craft;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use Twig\Error\Error;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig_Error;

/**
 * Class CommerceTwigExtension
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Extension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Craft Commerce Twig Extension';
    }

    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('json_encode_filtered', [$this, 'jsonEncodeFiltered']),
            new TwigFilter('commerceCurrency', [$this, 'commerceCurrency']),

        ];
    }

    /**
     * Formats and optionally converts a currency amount into the supplied valid payment currency as per the rate setup in payment currencies.
     *
     * @param      $amount
     * @param      $currency
     * @param bool $convert
     * @param bool $format
     * @param bool $stripZeros
     * @return string
     */
    public function commerceCurrency($amount, $currency, $convert = false, $format = true, $stripZeros = false): string
    {
        $this->_validatePaymentCurrency($currency);

        // return input if no currency passed, and both convert and format are false.
        if (!$convert && !$format) {
            return $amount;
        }

        if ($convert) {
            $amount = Plugin::getInstance()->getPaymentCurrencies()->convert($amount, $currency);
        }

        if ($format) {
            $amount = Craft::$app->getFormatter()->asCurrency($amount, $currency, [], [], $stripZeros);
        }

        return $amount;
    }

    /**
     * @param $input
     * @return string
     */
    public function jsonEncodeFiltered($input): string
    {
        $array = $this->_recursiveSanitizeArray($input);

        return json_encode($array);
    }

    /**
     * @param $input
     * @return int|mixed
     */
    public static function sanitize($input)
    {
        $sanitized = $input;

        if (!is_int($sanitized)) {
            $sanitized = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);
        } else {
            $newValue = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);

            if (is_numeric($newValue)) {
                $sanitized = (int)$newValue;
            } else {
                $sanitized = $newValue;
            }
        }

        return $sanitized;
    }

    // Private methods
    // =========================================================================

    /**
     * @param $currency
     * @throws Twig_Error
     */
    private function _validatePaymentCurrency($currency)
    {
        try {
            $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency);
        } catch (CurrencyException $exception) {
            throw new Error($exception->getMessage());
        }
    }

    /**
     * @param $array
     * @return array
     */
    private function _recursiveSanitizeArray($array): array
    {
        $finalArray = [];

        foreach ($array as $key => $value) {
            $newKey = self::sanitize($key);

            if (is_array($value)) {
                $finalArray[$newKey] = $this->_recursiveSanitizeArray($value);
            } else {
                $finalArray[$newKey] = self::sanitize($value);
            }
        }

        return $finalArray;
    }
}
