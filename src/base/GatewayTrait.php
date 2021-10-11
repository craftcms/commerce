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
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

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
    public ?int $sortOrder = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;
}
