<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\helpers\App;
use DateTime;

/**
 * GatewayTrait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait GatewayTrait
{
    use StoreTrait;

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
     * @var bool|string|null Enabled on the frontend
     */
    public bool|string|null $_isFrontendEnabled = true;

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

    /**
     * @param bool|string|null $isFrontendEnabled
     * @return void
     * @since 4.2.0
     */
    public function setIsFrontendEnabled(bool|string|null $isFrontendEnabled): void
    {
        $this->_isFrontendEnabled = $isFrontendEnabled;
    }

    /**
     * @param bool $parse
     * @return bool|string|null
     * @since 4.2.0
     */
    public function getIsFrontendEnabled(bool $parse = true): bool|string|null
    {
        return $parse ? App::parseBooleanEnv($this->_isFrontendEnabled) : $this->_isFrontendEnabled;
    }
}
