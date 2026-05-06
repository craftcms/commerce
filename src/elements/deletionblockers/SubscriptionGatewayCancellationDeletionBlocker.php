<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\deletionblockers;

use Craft;
use craft\commerce\elements\Subscription;
use craft\elements\deletionblockers\BaseDeletionBlocker;
use craft\helpers\Cp;
use craft\helpers\Html;
use Illuminate\Support\Collection;

/**
 * Subscription Gateway Cancellation Deletion Blocker.
 *
 * Forces the admin to decide what should happen at the payment gateway
 * before a subscription element can be deleted.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class SubscriptionGatewayCancellationDeletionBlocker extends BaseDeletionBlocker
{
    public int $gatewayId;
    public string $gatewayName;
    public Collection $subscriptions;

    public function isActive(): bool
    {
        return $this->subscriptions->isNotEmpty();
    }

    public function getSummary(): string
    {
        $num = $this->subscriptions->count();
        return Craft::t('commerce', '{num, number} active {num, plural, =1{subscription} other{subscriptions}} on {gateway}.', [
            'num' => $num,
            'gateway' => $this->gatewayName,
        ]);
    }

    public function getActions(): array
    {
        $subscriptionIds = $this->subscriptions->map(fn(Subscription $subscription) => $subscription->id)->all();

        return [
            [
                'icon' => 'circle-question',
                'label' => Craft::t('commerce', 'Choose gateway action'),
                'callback' => Html::jsWithVars(fn($subscriptionIds, $gatewayId) => <<<JS
                    new Craft.CpModal('commerce/subscriptions/cancel-subscriptions-modal', {
                      params: {
                        subscriptionIds: $subscriptionIds,
                        gatewayId: $gatewayId,
                      },
                      onSubmit: (ev) => {
                        resolve(ev.response.data.message);
                      },
                      onCancel: () => {
                        reject();
                      },
                    });
                    JS, [
                    $subscriptionIds,
                    $this->gatewayId,
                ]),
            ],
        ];
    }

    public function getDetails(): ?string
    {
        return Cp::elementIndexHtml(Subscription::class, [
            'context' => 'pane',
            'sources' => false,
            'jsSettings' => [
                'criteria' => [
                    'id' => $this->subscriptions->map(fn(Subscription $subscription) => $subscription->id)->all(),
                    'status' => null,
                ],
            ],
        ]);
    }
}
