<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Elements;

use CraftCms\Cms\Element\ElementCollection;

/**
 * VariantCollection represents a collection of Variant elements.
 *
 * @template TKey of array-key
 * @template TElement of Variant
 * @extends ElementCollection<TKey,TElement>
 */
class VariantCollection extends ElementCollection
{
    /**
     * Creates a VariantCollection from an array of Variant attributes.
     *
     * @return static
     */
    public static function make($items = [], mixed ...$args): static
    {
        foreach ($items as &$item) {
            if ($item instanceof Variant) {
                continue;
            }

            $class = $item['class'] ?? Variant::class;
            unset($item['class']);

            /** @var Variant $variant */
            $variant = new $class();
            $variant->setAttributes($item);
            $item = $variant;
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
        return $this->reduce(fn(?Variant $cheapest, Variant $variant) => !$cheapest || $variant->getSalePrice() < $cheapest->getSalePrice() ? $variant : $cheapest);
    }
}
