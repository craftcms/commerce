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
class OrderEditTakenOver extends OrderActivityEventType
{
    protected const string LABEL = 'Edit taken over';

    protected const string ICON = 'user-pen';

    public function __construct(
        Order $subject,
        private readonly string $previousEditor,
        private readonly int $discardedChanges,
        CraftUser|ActivityActor|null $actor = null,
    ) {
        parent::__construct(subject: $subject, actor: $actor);
    }

    public function data(): array
    {
        return [
            'previousEditor' => $this->previousEditor,
            'discardedChanges' => $this->discardedChanges,
        ];
    }

    public static function format(ActivityEvent $event): string
    {
        $params = [
            'name' => $event->data['previousEditor'],
            'count' => $event->data['discardedChanges'],
        ];

        return $event->data['discardedChanges'] > 0
            ? t('Took over editing the order from {name}, discarding {count, number} staged {count, plural, =1{change} other{changes}}', $params, category: 'commerce')
            : t('Took over editing the order from {name}', $params, category: 'commerce');
    }
}
