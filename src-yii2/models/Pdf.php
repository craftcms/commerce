<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Pdf\Data\Pdf} */
class_alias(\CraftCms\Commerce\Pdf\Data\Pdf::class, 'craft\commerce\models\Pdf');

/** @phpstan-ignore-next-line */
if (false) {
    class Pdf extends \CraftCms\Commerce\Pdf\Data\Pdf {}
}
