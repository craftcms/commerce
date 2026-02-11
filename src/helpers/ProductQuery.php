<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\base\Element;
use craft\commerce\elements\Product;
use craft\helpers\Db;
use DateTime;

/**
 * Product Query helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5.0
 */
class ProductQuery
{
    /**
     * @param string $status
     * @param string $tablePrefix
     * @return array|false
     */
    public static function statusCondition(string $status, string $tablePrefix = ''): array|false
    {
        // Always consider “now” to be the current time @ 59 seconds into the minute.
        // This makes entry queries more cacheable, since they only change once every minute (https://github.com/craftcms/cms/issues/5389),
        // while not excluding any entries that may have just been published in the past minute (https://github.com/craftcms/cms/issues/7853).
        $now = new DateTime();
        $now->setTime((int)$now->format('H'), (int)$now->format('i'), 59);
        $currentTimeDb = Db::prepareDateForDb($now);

        return match ($status) {
            Product::STATUS_LIVE => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['<=', 'commerce_products.postDate', $currentTimeDb],
                [
                    'or',
                    ['commerce_products.expiryDate' => null],
                    ['>', 'commerce_products.expiryDate', $currentTimeDb],
                ],
            ],
            Product::STATUS_PENDING => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['>', 'commerce_products.postDate', $currentTimeDb],
            ],
            Product::STATUS_EXPIRED => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['not', ['commerce_products.expiryDate' => null]],
                ['<=', 'commerce_products.expiryDate', $currentTimeDb],
            ],

            // Taken from base `ElementQuery::statusCondition()`
            Element::STATUS_ENABLED => [
                $tablePrefix . 'elements.enabled' => true,
                $tablePrefix . 'elements_sites.enabled' => true,
            ],
            Element::STATUS_DISABLED => [
                'or',
                [$tablePrefix . 'elements.enabled' => false],
                [$tablePrefix . 'elements_sites.enabled' => false],
            ],
            Element::STATUS_ARCHIVED => [$tablePrefix . 'elements.archived' => true],
            default => false,
        };
    }
}
