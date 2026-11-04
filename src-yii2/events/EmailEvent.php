<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Email\Data\Email;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class EmailEvent extends Event
{
    /**
     * @var Email The email
     */
    public Email $email;

    /**
     * @var bool Whether the email is new
     */
    public bool $isNew = false;
}
