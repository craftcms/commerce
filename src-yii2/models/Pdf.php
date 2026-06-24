<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Pdf\Models\Pdf} */
class_alias(\CraftCms\Commerce\Pdf\Models\Pdf::class, 'craft\commerce\models\Pdf');

/** @phpstan-ignore-next-line */
if (false) {
    class Pdf extends \CraftCms\Commerce\Pdf\Models\Pdf {}
}
