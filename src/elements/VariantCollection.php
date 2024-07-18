<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use craft\elements\ElementCollection;
use Illuminate\Support\Collection;

/**
 * VariantCollection represents a collection of Variant elements.
 *
 * @template TKey of array-key
 * @template TElement of Variant
 * @extends ElementCollection<TKey,TElement>
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

        /** @var static $collection */
        $collection = parent::make($items);
        return $collection;
    }

    /**
     * Returns the cheapest variant in the collection.
     *
     * @return Variant|null The cheapest variant in the collection, or null if there aren't any
     */
    public function cheapest(): ?Variant
    {
        return $this->reduce(function(?Variant $cheapest, Variant $variant) {
            return !$cheapest || $variant->getSalePrice() < $cheapest->getSalePrice() ? $variant : $cheapest;
        });
    }
}
