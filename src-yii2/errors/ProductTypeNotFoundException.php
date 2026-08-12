<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\ProductType\Exceptions\ProductTypeNotFoundException} */
class_alias(\CraftCms\Commerce\Catalog\ProductType\Exceptions\ProductTypeNotFoundException::class, 'craft\commerce\errors\ProductTypeNotFoundException');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductTypeNotFoundException extends \CraftCms\Commerce\Catalog\ProductType\Exceptions\ProductTypeNotFoundException {}
}
