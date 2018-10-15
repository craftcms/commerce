<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * Plan trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait PlanTrait
{
    // Properties
    // =========================================================================

    /**
     * @var int Plan ID
     */
    public $id;

    /**
     * @var int The gateway ID.
     */
    public $gatewayId;

    /**
     * @var string plan name
     */
    public $name;

    /**
     * @var string plan handle
     */
    public $handle;

    /**
     * @var int ID of the entry containing plan information
     */
    public $planInformationId;

    /**
     * @var string plan reference on the gateway
     */
    public $reference;

    /**
     * @var bool whether the plan is enabled on site
     */
    public $enabled;

    /**
     * @var bool whether the plan is archived
     */
    public $isArchived;

    /**
     * @var \DateTime when the plan was archived
     */
    public $dateArchived;

    /**
     * @var string gateway response
     */
    public $planData;

    /**
     * @var string plan uid
     */
    public $uid;
}
