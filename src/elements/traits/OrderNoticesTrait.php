<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use craft\commerce\elements\Order;


/**
 * @property Order $this
 */
trait OrderNoticesTrait
{
    private $_notices;

    /**
     * Returns the notices for all attributes or a single attribute.
     *
     * @param string $attribute attribute name. Use null to retrieve notices for all attributes.
     * @return array notices for all attributes or the specified attribute. Empty array is returned if no notice.
     * Note that when returning notices for all attributes, the result is a two-dimensional array, like the following:
     *
     * ```php
     * [
     *     'customerId' => [
     *         'Customer changed.',
     *     ],
     *     'shippingMethodHandle' => [
     *         'Shipping method has been removed since it is not longer available.',
     *     ]
     * ]
     * ```
     *
     * @property array An array of notices for all attributes. Empty array is returned if no notice.
     * The result is a two-dimensional array. See [[getNotices()]] for detailed description.
     * @see getFirstNotices()
     * @see getFirstNotice()
     */
    public function getNotices($attribute = null)
    {
        if ($attribute === null) {
            return $this->_notices ?? [];
        }

        return $this->_notices[$attribute] ?? [];
    }

    /**
     * Adds a new notice to the specified attribute.
     *
     * @param string $attribute attribute name
     * @param string $notice new notice message
     */
    public function addNotice($attribute, $notice = '')
    {
        $this->_notices[$attribute][] = $notice;
    }

    /**
     * Returns the notices for all attributes as a one-dimensional array.
     *
     * @param bool $showAllNotices boolean, if set to true every notice message for each attribute will be shown otherwise
     * only the first notice message for each attribute will be shown.
     * @return array notices for all attributes as a one-dimensional array. Empty array is returned if no notice.
     * @see getNotices()
     * @see getFirstNotices()
     * @since 3.x
     */
    public function getNoticeSummary($showAllNotices)
    {
        $lines = [];
        $notices = $showAllNotices ? $this->getNotices() : $this->getFirstNotice();
        foreach ($notices as $es) {
            $lines = array_merge((array)$es, $lines);
        }
        return $lines;
    }

    /**
     * Adds a list of notices.
     *
     * @param array $items a list of notices. The array keys must be attribute names.
     * The array values should be notice messages. If an attribute has multiple notices,
     * these notices must be given in terms of an array.
     * You may use the result of [[getNotices()]] as the value for this parameter.
     * @since 3.x
     */
    public function addNotices(array $items)
    {
        foreach ($items as $attribute => $notices) {
            if (is_array($notices)) {
                foreach ($notices as $notice) {
                    $this->addNotice($attribute, $notice);
                }
            } else {
                $this->addNotice($attribute, $notices);
            }
        }
    }

    /**
     * Removes notices for all attributes or a single attribute.
     *
     * @param string $attribute attribute name. Use null to remove notices for all attributes.
     */
    public function clearNotices($attribute = null)
    {
        if ($attribute === null) {
            $this->_notices = [];
        } else {
            unset($this->_notices[$attribute]);
        }
    }

    /**
     * Returns the first notice of every attribute in the model.
     *
     * @return array the first notices. The array keys are the attribute names, and the array
     * values are the corresponding notice messages. An empty array will be returned if there is no notice.
     * @see getNotices()
     * @see getFirstNotice()
     */
    public function getFirstNotices(): array
    {
        if (empty($this->_notices)) {
            return [];
        }

        $notices = [];
        foreach ($this->_notices as $name => $es) {
            if (!empty($es)) {
                $notices[$name] = reset($es);
            }
        }

        return $notices;
    }

    /**
     * Returns a value indicating whether there is any notice.
     *
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return bool whether there is any notice.
     */
    public function hasNotices($attribute = null): bool
    {
        return $attribute === null ? !empty($this->_notices) : isset($this->_notices[$attribute]);
    }
}
