<?php

namespace craft\commerce\fields;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Fields\Variants} */
class_alias(\CraftCms\Commerce\Catalog\Fields\Variants::class, 'craft\commerce\fields\Variants');

/** @phpstan-ignore-next-line */
if (false) {
    class Variants extends \CraftCms\Commerce\Catalog\Fields\Variants {}
}
