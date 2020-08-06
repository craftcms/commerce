<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\twig;

use craft\commerce\helpers\Currency;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class CommerceTwigExtension
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Extension extends AbstractExtension
{
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
            new TwigFilter('commerceCurrency', [Currency::class, 'formatAsCurrency']),
        ];
    }

    /**
     * @param $input
     * @return string
     * @throws \craft\errors\DeprecationException
     * @deprecated in 3.1.11.
     */
    public function jsonEncodeFiltered($input): string
    {
        \Craft::$app->getDeprecator()->log('|json_encode_filtered', 'The json_encode_filtered twig filter has been deprecated. Use standard js encoding.');

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
