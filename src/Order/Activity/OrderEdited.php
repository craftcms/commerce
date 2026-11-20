<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Data\ActivityChange;
use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
class OrderEdited extends OrderActivityEventType
{
    protected const string LABEL = 'Order edited';

    protected const string ICON = 'pencil';

    /**
     * @param list<string> $summaries What each change did, in the order they were made.
     * @param list<ActivityChange> $changes
     */
    public function __construct(
        Order $subject,
        private readonly array $summaries,
        array $changes = [],
    ) {
        parent::__construct(subject: $subject, changes: $changes);
    }

    public function data(): array
    {
        return ['summaries' => $this->summaries];
    }

    public static function format(ActivityEvent $event): string
    {
        $summaries = $event->data['summaries'];

        return $summaries === []
            ? t('Edited the order', category: 'commerce')
            : t('Edited the order: {changes}', ['changes' => implode('; ', $summaries)], category: 'commerce');
    }
}
