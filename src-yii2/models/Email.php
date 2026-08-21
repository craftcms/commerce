<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Email\Models\Email} */
class_alias(\CraftCms\Commerce\Email\Models\Email::class, 'craft\commerce\models\Email');

/** @phpstan-ignore-next-line */
if (false) {
    class Email extends \CraftCms\Commerce\Email\Models\Email {}
}
