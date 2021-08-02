<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use DateTime;

/**
 * Plan trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait PlanTrait
{
    /**
     * @var int Plan ID
     */
    public int $id;

    /**
     * @var int The gateway ID.
     */
    public int $gatewayId;

    /**
     * @var string plan name
     */
    public string $name;

    /**
     * @var string plan handle
     */
    public string $handle;

    /**
     * @var int ID of the entry containing plan information
     */
    public int $planInformationId;

    /**
     * @var string plan reference on the gateway
     */
    public string $reference;

    /**
     * @var bool whether the plan is enabled on site
     */
    public bool $enabled;

    /**
     * @var bool whether the plan is archived
     */
    public bool $isArchived;

    /**
     * @var DateTime when the plan was archived
     */
    public DateTime $dateArchived;

    /**
     * @var string gateway response
     */
    public string $planData;

    /**
     * @var string plan uid
     */
    public string $uid;

    /**
     * @var int sort order
     */
    public int $sortOrder;
}
