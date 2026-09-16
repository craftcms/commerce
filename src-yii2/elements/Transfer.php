<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Elements\Transfer} */
class_alias(\CraftCms\Commerce\Transfer\Elements\Transfer::class, 'craft\commerce\elements\Transfer');

/** @phpstan-ignore-next-line */
if (false) {
    class Transfer extends \CraftCms\Commerce\Transfer\Elements\Transfer {}
}
