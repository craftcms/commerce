<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use craft\commerce\elements\Order;
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
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNotice::NOTICE_TYPE_CUSTOMER));
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
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNotice::NOTICE_TYPE_ADMIN));
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
     * Removes non-admin notices matching the given criteria.
     * Admin notices are preserved unless $clearAdminNotices is true.
     *
     * @param string|null $type type name. Use null to remove notices for all types.
     * @param string|null $attribute attribute name. Use null to remove notices for all attributes.
     * @param bool $clearAdminNotices Whether to also clear admin notices. Defaults to false.
     * @since 3.3
     */
    public function clearNotices(?string $type = null, ?string $attribute = null, bool $clearAdminNotices = false): void
    {
        $adminNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNotice::NOTICE_TYPE_ADMIN));
        $regularNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNotice::NOTICE_TYPE_CUSTOMER));

        $targetNotices = $clearAdminNotices ? $this->_notices : $regularNotices;

        if ($type === null && $attribute === null) {
            $remaining = [];
        } elseif ($type !== null && $attribute === null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->type !== $type));
        } elseif ($type === null && $attribute !== null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->attribute !== $attribute));
        } else {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => !($n->type === $type && $n->attribute === $attribute)));
        }

        if ($clearAdminNotices) {
            $this->_notices = $remaining;
        } else {
            $this->_notices = array_merge($adminNotices, $remaining);
        }
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
