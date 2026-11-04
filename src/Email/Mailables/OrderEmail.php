<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Mailables;

use CraftCms\Cms\Email\Data\MailSettings;
use CraftCms\Cms\Email\Mailables\CraftMailable;
use Override;

/**
 * A Commerce email for an order, sent with the mailer configured for its site.
 */
class OrderEmail extends CraftMailable
{
    #[Override]
    protected function applyEmailSettings(): MailSettings
    {
        $from = $this->from;
        $replyTo = $this->replyTo;

        $settings = parent::applyEmailSettings();

        // The Commerce email's own sender and reply-to take precedence over the site's email settings.
        if ($from !== []) {
            $this->from = $from;
        }

        if ($replyTo !== []) {
            $this->replyTo = $replyTo;
        }

        return $settings;
    }
}
