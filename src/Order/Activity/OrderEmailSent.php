<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
class OrderEmailSent extends OrderActivityEventType
{
    protected const string LABEL = 'Email sent';

    protected const string ICON = 'paper-plane';

    public function __construct(
        Order $subject,
        private readonly string $email,
    ) {
        parent::__construct(subject: $subject);
    }

    public function data(): array
    {
        return ['email' => $this->email];
    }

    public static function format(ActivityEvent $event): string
    {
        return t('Sent the “{email}” email', ['email' => $event->data['email']], category: 'commerce');
    }
}
