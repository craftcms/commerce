<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\PaymentCurrency;
use craft\helpers\ArrayHelper;

/**
 * Adds order notice getters and setters.
 *
 * @property Order $this
 * @since 3.3
 */
trait OrderNoticesTrait
{
    private $_notices;

    /**
     * Returns the notices for all types/attributes or a single type/attributes.
     *
     * @param string $type type name. Use null to retrieve notices for all types.
     * @param string $attribute attribute name. Use null to retrieve notices for all attributes.
     * @return OrderNotice[] notices for all types or the specified type / attribute. Empty array is returned if no notice.
     * @since 3.3
     */
    public function getNotices($type = null, $attribute = null)
    {
        // We want all
        if ($type === null && $attribute === null) {
            return $this->_notices ?? [];
        }

        // Filter by type
        if ($type !== null && $attribute === null) {
            return ArrayHelper::where($this->_notices, 'type', $type);
        }

        // Filter by attribute
        if ($type === null && $attribute !== null) {
            return ArrayHelper::where($this->_notices, 'attribute', $attribute);
        }

        // Filter by both
        if ($type !== null && $attribute !== null) {
            return ArrayHelper::where($this->_notices, function(OrderNotice $notice) use ($attribute, $type) {
                return $notice->attribute == $attribute && $notice->type == $type;
            }, true, true, true);
        }

        return [];
    }

    /**
     * Adds a new notice
     *
     * @param OrderNotice $notice
     * @since 3.3
     */
    public function addNotice(OrderNotice $notice)
    {
        $notice->setOrder($this);
        $this->_notices[] = $notice;
    }

    /**
     * Returns the first error of the specified type or attribute
     *
     * @return OrderNotice|null
     * @since 3.3
     */
    public function getFirstNotice($type = null, $attribute = null)
    {
        return ArrayHelper::firstValue($this->getNotices($type, $attribute));
    }

    /**
     * Adds a list of notices.
     *
     * @param OrderNotice[] $notice an array of notices.
     * @since 3.3
     */
    public function addNotices(array $notices)
    {
        foreach ($notices as $notice) {
            $this->addNotice($notice);
        }
    }

    /**
     * Removes notices for all types or a single type.
     *
     * @param string $type type name. Use null to remove notices for all types.
     * @since 3.3
     */
    public function clearNotices($type = null, $attribute = null)
    {
        if ($type === null && $attribute === null) {
            $this->_notices = [];
        } elseif ($type !== null && $attribute === null) {
            $this->_notices = ArrayHelper::where($this->_notices, function(OrderNotice $notice) use ($type, $attribute) {
                return $notice->type != $type;
            }, true, true, true);
        } elseif ($type === null && $attribute !== null) {
            $this->_notices = ArrayHelper::where($this->_notices, function(OrderNotice $notice) use ($type, $attribute) {
                return $notice->attribute != $attribute;
            }, true, true, true);
        } elseif ($type !== null && $attribute !== null) {
            $this->_notices = ArrayHelper::where($this->_notices, function(OrderNotice $notice) use ($type, $attribute) {
                return $notice->type == $type && $notice->attribute == $attribute;
            }, false, true, true);
        }
    }

    /**
     * Returns a value indicating whether there is any notices.
     *
     * @param string|null $type type name. Use null to check all types.
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return bool whether there is any notices.
     * @since 3.3
     */
    public function hasNotices($type = null, $attribute = null): bool
    {
        $hasNotices = !empty($this->getNotices($type, $attribute));
        return $hasNotices;
    }
}
