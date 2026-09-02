<?php

namespace craft\commerce\fields;

/** @deprecated use {@see \CraftCms\Commerce\Product\Fields\Variants} */
class_alias(\CraftCms\Commerce\Product\Fields\Variants::class, 'craft\commerce\fields\Variants');

/** @phpstan-ignore-next-line */
if (false) {
    class Variants extends \CraftCms\Commerce\Product\Fields\Variants {}
}
