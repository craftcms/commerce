<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craft\commerce\base;

/**
 * Stat Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
trait StatTrait
{
    /**
     * @var bool
     */
    public $cache = false;

    /**
     * @var int How long to cache the data, in seconds.
     */
    public $cacheDuration = 0;

    /**
     * @var string
     */
    public $dateRange = StatInterface::DATE_RANGE_TODAY;

    /**
     * @var int
     */
    public $weekStartDay = 1; // Monday

    /**
     * @var string
     */
    protected $_handle;

    /**
     * @var null|\DateTime
     */
    private $_startDate = null;

    /**
     * @var null|\DateTime
     */
    private $_endDate = null;

    /**
     * @var string|null
     */
    private $_cacheKey;
}
