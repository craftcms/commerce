<?php

namespace craft\commerce\base;

use yii\base\Event;

/**
 * Triggers a legacy Yii event for an element whose `craft\commerce\*` class name is an alias of its new class.
 *
 * Yii matches class-level handlers by class-name string, so handlers registered with the alias name
 * (e.g. `Event::on(\craft\commerce\elements\Order::class, ...)`) are triggered separately from the
 * element's own instance and class handlers.
 *
 * @internal
 */
final class LegacyElementEvents
{
    /**
     * @template T of Event
     * @param T|null $event
     * @return T|Event
     */
    public static function trigger(object $element, string $aliasClass, string $name, ?Event $event = null): Event
    {
        $event ??= new Event();
        $event->sender = $element;

        /** @phpstan-ignore-next-line method.notFound (provided to elements by craftcms/yii2-adapter) */
        if ($element->hasEventHandlers($name)) {
            /** @phpstan-ignore-next-line method.notFound */
            $element->trigger($name, $event);
        }

        if (!$event->handled && Event::hasHandlers($aliasClass, $name)) {
            Event::trigger($aliasClass, $name, $event);
        }

        return $event;
    }
}
