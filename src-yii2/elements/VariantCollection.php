<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Product\Variant\Elements\VariantCollection} */
class_alias(\CraftCms\Commerce\Product\Variant\Elements\VariantCollection::class, 'craft\commerce\elements\VariantCollection');

/** @phpstan-ignore-next-line */
if (false) {
    class VariantCollection extends \CraftCms\Commerce\Product\Variant\Elements\VariantCollection {}
}
