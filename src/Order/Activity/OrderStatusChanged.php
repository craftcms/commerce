<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Data\ActivityActor;
use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
class OrderStatusChanged extends OrderActivityEventType
{
    protected const string LABEL = 'Status changed';

    protected const string ICON = 'circle-half-stroke';

    public function __construct(
        Order $subject,
        private readonly ?string $oldStatus,
        private readonly ?string $newStatus,
        private readonly ?string $message,
        CraftUser|ActivityActor|null $actor = null,
    ) {
        parent::__construct(subject: $subject, actor: $actor);
    }

    public function data(): array
    {
        return [
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
            'message' => $this->message,
        ];
    }

    public static function format(ActivityEvent $event): string
    {
        $params = [
            'oldStatus' => $event->data['oldStatus'],
            'newStatus' => $event->data['newStatus'],
        ];
        $text = $event->data['oldStatus']
            ? t('Changed the status from {oldStatus} to {newStatus}', $params, category: 'commerce')
            : t('Set the status to {newStatus}', $params, category: 'commerce');

        return $event->data['message']
            ? t('{status}: {message}', ['status' => $text, 'message' => $event->data['message']], category: 'commerce')
            : $text;
    }
}
