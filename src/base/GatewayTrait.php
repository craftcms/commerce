<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\base;

/**
 * GatewayTrait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
trait GatewayTrait
{
    // Properties
    // =========================================================================

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var string Payment Type
     */
    public $paymentType = 'purchase';

    /**
     * @var bool Whether the gateway can send cart info to payment processor
     */
    public $sendCartInfo = true;

    /**
     * @var bool Enabled on the frontend
     */
    public $frontendEnabled;

    /**
     * @var bool Archived
     */
    public $isArchived;

    /**
     * @var \DateTime Archived Date
     */
    public $dateArchived;

    /**
     * @var int|null Sort order
     */
    public $sortOrder;
}
