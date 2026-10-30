<?php

namespace craft\commerce\taxidvalidators;

/** @deprecated use {@see \CraftCms\Commerce\Tax\VatValidator\Eu} */
class_alias(\CraftCms\Commerce\Tax\VatValidator\Eu::class, 'craft\commerce\taxidvalidators\EuVatIdValidator');

/** @phpstan-ignore-next-line */
if (false) {
    class EuVatIdValidator extends \CraftCms\Commerce\Tax\VatValidator\Eu {}
}
