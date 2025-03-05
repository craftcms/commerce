<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\conditions\orders\OrderConditionRuleTrait;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;

/**
 * Enabled Gateway condition rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.5
 */
class EnabledGatewayConditionRule extends BaseConditionRule implements ElementConditionRuleInterface
{
    use OrderConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public string $operator = '=';

    /**
     * @var bool|null Whether the gateway is enabled
     */
    public ?bool $value = null;

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'value' => $this->value,
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Enabled');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function operators(): array
    {
        return [
            '=' => Craft::t('commerce', 'is'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml(): string
    {
        return Html::checkbox('value', $this->value ?? true);
    }

    /**
     * @inheritdoc
     */
    protected function matchOrder(Order $order): bool
    {
        // This is a simple "enabled" rule that always returns true if enabled
        // It's used to convert from the old isFrontendEnabled boolean flag
        return $this->value === null || (bool)$this->value;
    }
}