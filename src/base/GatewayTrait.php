<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use DateTime;

/**
 * GatewayTrait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait GatewayTrait
{
    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var string Payment Type
     */
    public string $paymentType = 'purchase';

    /**
     * @var bool Enabled on the frontend
     */
    public bool $isFrontendEnabled = true;

    /**
     * @var bool Archived
     */
    public bool $isArchived = false;

    /**
     * @var DateTime|null Archived Date
     */
    public ?DateTime $dateArchived = null;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder;

    /**
     * @var string|null UID
     */
    public ?string $uid;
}
