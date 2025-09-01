<?php

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use yii\db\QueryInterface;

/**
 * Payment Gateway condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class PaymentGatewayConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Payment Gateway');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['gatewayId'];
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        return Plugin::getInstance()->getGateways()->getAllGateways()->mapWithKeys(fn($gateway) => [$gateway->uid => $gateway->name])->all();
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(QueryInterface $query): void
    {
        $gatewayIds = [];
        $gateways = Plugin::getInstance()->getGateways()->getAllGateways();
        
        foreach ($this->getValues() as $uid) {
            $gateway = $gateways->firstWhere('uid', $uid);
            if ($gateway) {
                $gatewayIds[] = $gateway->id;
            }
        }
        
        if (!empty($gatewayIds)) {
            /** @var OrderQuery $query */
            $query->gatewayId($this->paramValue(fn($uid) => $gateways->firstWhere('uid', $uid)?->id));
        }
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $gatewayUid = $element->getGateway()?->uid;
        return $this->matchValue($gatewayUid);
    }
}
