<?php

namespace craft\commerce\elements\conditions\addresses;

/** @deprecated use {@see \CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule} */
class_alias(\CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule::class, 'craft\commerce\elements\conditions\addresses\PostalCodeFormulaConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class PostalCodeFormulaConditionRule extends \CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule {}
}
