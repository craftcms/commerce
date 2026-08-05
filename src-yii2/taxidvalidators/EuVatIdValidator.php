<?php

namespace craft\commerce\taxidvalidators;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Models\EuVatIdValidator} */
class_alias(\CraftCms\Commerce\Tax\Models\EuVatIdValidator::class, 'craft\commerce\taxidvalidators\EuVatIdValidator');

/** @phpstan-ignore-next-line */
if (false) {
    class EuVatIdValidator extends \CraftCms\Commerce\Tax\Models\EuVatIdValidator {}
}
