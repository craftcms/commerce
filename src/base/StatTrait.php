<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use DateTime;

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
    public bool $cache = false;

    /**
     * @var int How long to cache the data, in seconds.
     */
    public int $cacheDuration = 0;

    /**
     * @var string
     */
    public string $dateRange = StatInterface::DATE_RANGE_TODAY;

    /**
     * @var int
     */
    public int $weekStartDay = 1; // Monday

    /**
     * @var string
     */
    protected string $_handle;

    /**
     * @var null|DateTime
     */
    private ?DateTime $_startDate = null;

    /**
     * @var null|DateTime
     */
    private ?DateTime $_endDate = null;

    /**
     * @var string|null
     */
    private ?string $_cacheKey = null;
}
