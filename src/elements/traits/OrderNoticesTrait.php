<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use craft\commerce\elements\Order;
use craft\commerce\enums\OrderNoticeType;
use craft\commerce\models\OrderNotice;
use craft\helpers\ArrayHelper;

/**
 * Adds order notice getters and setters.
 *
 * @since 3.3
 */
trait OrderNoticesTrait
{
    /**
     * @var array
     */
    private array $_notices = [];

    /**
     * Returns non-admin notices. Admin notices are excluded by default.
     *
     * @param string|null $type type name. Use null to retrieve notices for all types.
     * @param string|null $attribute attribute name. Use null to retrieve notices for all attributes.
     * @return OrderNotice[] notices for all types or the specified type / attribute. Empty array is returned if no notice.
     * @since 3.3
     */
    public function getNotices(?string $type = null, ?string $attribute = null): array
    {
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNoticeType::Customer));
        return $this->_filterNotices($notices, $type, $attribute);
    }

    /**
     * Returns admin-only notices, optionally filtered by type and/or attribute.
     *
     * @param string|null $type
     * @param string|null $attribute
     * @return OrderNotice[]
     * @since 5.x
     */
    public function getAdminNotices(?string $type = null, ?string $attribute = null): array
    {
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNoticeType::Admin));
        return $this->_filterNotices($notices, $type, $attribute);
    }

    /**
     * Adds a new notice
     *
     * @since 3.3
     */
    public function addNotice(OrderNotice $notice): void
    {
        $notice->setOrder($this);
        $this->_notices[] = $notice;
    }

    /**
     * Returns the first non-admin notice matching the specified type or attribute.
     *
     * @param null $type
     * @param null $attribute
     * @since 3.3
     */
    public function getFirstNotice($type = null, $attribute = null): ?OrderNotice
    {
        return ArrayHelper::firstValue($this->getNotices($type, $attribute));
    }

    /**
     * Adds a list of notices.
     *
     * @param OrderNotice[] $notices an array of notices.
     * @since 3.3
     */
    public function addNotices(array $notices): void
    {
        foreach ($notices as $notice) {
            $this->addNotice($notice);
        }
    }

    /**
     * Removes notices matching the given criteria, scoped to the specified notice types.
     *
     * By default only customer notices are cleared, preserving admin notices for backwards compatibility.
     * Pass one or more {@see OrderNoticeType} values to control which notice types are affected.
     *
     * @param string|null $type type name. Use null to remove notices for all types.
     * @param string|null $attribute attribute name. Use null to remove notices for all attributes.
     * @param OrderNoticeType|OrderNoticeType[]|null $noticeTypes Notice type(s) to clear. Defaults to customer notices only.
     * @since 3.3
     */
    public function clearNotices(?string $type = null, ?string $attribute = null, array|OrderNoticeType|null $noticeTypes = null): void
    {
        if ($noticeTypes === null) {
            $noticeTypes = [OrderNoticeType::Customer];
        } elseif ($noticeTypes instanceof OrderNoticeType) {
            $noticeTypes = [$noticeTypes];
        }

        $targetNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => in_array($n->noticeType, $noticeTypes)));
        $preservedNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => !in_array($n->noticeType, $noticeTypes)));

        if ($type === null && $attribute === null) {
            $remaining = [];
        } elseif ($type !== null && $attribute === null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->type !== $type));
        } elseif ($type === null && $attribute !== null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->attribute !== $attribute));
        } else {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => !($n->type === $type && $n->attribute === $attribute)));
        }

        $this->_notices = array_merge($preservedNotices, $remaining);
    }

    /**
     * Returns a value indicating whether there are any non-admin notices.
     *
     * @param string|null $type type name. Use null to check all types.
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return bool whether there is any notices.
     * @since 3.3
     */
    public function hasNotices(?string $type = null, ?string $attribute = null): bool
    {
        return !empty($this->getNotices($type, $attribute));
    }

    /**
     * Returns whether there are any admin notices.
     *
     * @since 5.x
     */
    public function hasAdminNotices(): bool
    {
        return !empty($this->getAdminNotices());
    }

    /**
     * Filters an array of notices by type and/or attribute.
     *
     * @param OrderNotice[] $notices
     * @param string|null $type
     * @param string|null $attribute
     * @return OrderNotice[]
     */
    private function _filterNotices(array $notices, ?string $type, ?string $attribute): array
    {
        if ($type === null && $attribute === null) {
            return $notices;
        }

        if ($type !== null && $attribute === null) {
            return ArrayHelper::where($notices, 'type', $type);
        }

        if ($type === null && $attribute !== null) {
            return ArrayHelper::where($notices, 'attribute', $attribute);
        }

        return ArrayHelper::where($notices, fn(OrderNotice $n) => $n->attribute === $attribute && $n->type === $type, true, true, true);
    }
}
