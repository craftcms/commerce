<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use craft\elements\ElementCollection;

/**
 * VariantCollection represents a collection of Variant elements.
 *
 * @extends ElementCollection<Variant>
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class VariantCollection extends ElementCollection
{
    /**
     * Creates a VariantCollection from an array of Variant attributes.
     *
     * @param array $items
     * @return static
     */
    public static function make($items = [])
    {
        foreach ($items as &$item) {
            if ($item instanceof Variant) {
                continue;
            }

            $item = \Craft::createObject(Variant::class, [
                'config' => ['attributes' => $item],
            ]);
        }

        return parent::make($items);
    }

    /**
     * Returns the cheapest variant in the collection.
     *
     * @param bool $includeDisabled Whether to include disabled variants in the comparison
     * @return Variant|null The cheapest variant in the collection, or null if there aren't any
     */
    public function cheapest(bool $includeDisabled = false): ?Variant
    {
        $cheapest = null;

        $this->each(function(Variant $variant) use (&$cheapest, $includeDisabled) {
            if ($includeDisabled || $variant->enabled) {
                if (!$cheapest || $variant->getSalePrice() < $cheapest->getSalePrice()) {
                    $cheapest = $variant;
                }
            }
        });

        return $cheapest;
    }
}
