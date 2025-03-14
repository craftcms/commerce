<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\conditions\orders\GatewayOrderCondition;
use craft\elements\conditions\ElementConditionInterface;
use craft\helpers\App;
use craft\helpers\Json;
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
     * @var bool|string|null Enabled on the frontend
     */
    public bool|string|null $_isFrontendEnabled = true;


    /**
     * @var ElementConditionInterface|null
     */
    private ?ElementConditionInterface $_orderCondition = null;

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

    /**
     * Gets the order condition for this gateway
     *
     * @since 5.4.0
     */
    public function getOrderCondition(): ElementConditionInterface
    {
        /** @var DiscountOrderCondition $condition */
        $condition = $this->_orderCondition ?? new GatewayOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';

        return $condition;
    }

    /**
     * Sets the order condition for this gateway
     *
     * @since 5.4.0
     */
    public function setOrderCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof GatewayOrderCondition) {
            $condition['class'] = GatewayOrderCondition::class;
            /** @var GatewayOrderCondition $condition */
            $condition = \Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = true;

        $this->_orderCondition = $condition;
    }
}
