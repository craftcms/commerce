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
     * @var int|null Plan ID
     */
    public ?int $id = null;

    /**
     * @var int|null The gateway ID.
     */
    public ?int $gatewayId = null;

    /**
     * @var string|null plan name
     */
    public ?string $name = null;

    /**
     * @var string|null plan handle
     */
    public ?string $handle = null;

    /**
     * @var int|null ID of the entry containing plan information
     */
    public ?int $planInformationId = null;

    /**
     * @var string|null plan reference on the gateway
     */
    public ?string $reference = null;

    /**
     * @var bool whether the plan is enabled on site
     */
    public bool $enabled = false;

    /**
     * @var bool whether the plan is archived
     */
    public bool $isArchived = false;

    /**
     * @var DateTime|null when the plan was archived
     */
    public ?DateTime $dateArchived = null;

    /**
     * @var string|null gateway response
     */
    public ?string $planData = null;

    /**
     * @var string|null plan uid
     */
    public ?string $uid = null;

    /**
     * @var int|null sort order
     */
    public ?int $sortOrder = null;
}
