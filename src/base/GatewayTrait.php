<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * GatewayTrait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * @var bool Enabled on the frontend
     */
    public $isFrontendEnabled = true;

    /**
     * @var bool Archived
     */
    public $isArchived = false;

    /**
     * @var \DateTime Archived Date
     */
    public $dateArchived;

    /**
     * @var int|null Sort order
     */
    public $sortOrder;
}
