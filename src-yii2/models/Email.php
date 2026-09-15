<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Email\Data\Email} */
class_alias(\CraftCms\Commerce\Email\Data\Email::class, 'craft\commerce\models\Email');

/** @phpstan-ignore-next-line */
if (false) {
    class Email extends \CraftCms\Commerce\Email\Data\Email {}
}
