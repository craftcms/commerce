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
 * Subscription Customers Deletion Blocker.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class SubscriptionCustomersDeletionBlocker extends BaseDeletionBlocker
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
        return Craft::t('commerce', '{num, number} {num, plural, =1{subscription} other{subscriptions}} via {gateway}.', [
            'num' => $num,
            'gateway' => $this->gatewayName,
        ]);
    }

    public function getActions(): array
    {
        $numSubscriptions = $this->subscriptions->count();
        $subscriptionIds = $this->subscriptions->map(fn(Subscription $subscription) => $subscription->id)->all();

        return [
            [
                'icon' => 'trash',
                'label' => Craft::t('app', 'Delete {type}', [
                    'type' => $numSubscriptions === 1 ? Subscription::lowerDisplayName() : Subscription::pluralLowerDisplayName(),
                ]),
                'destructive' => true,
                'callback' => Html::jsWithVars(fn($subscriptionIds, $gatewayId) => <<<JS
                    new Craft.CpModal('commerce/subscriptions/delete-subscriptions-modal', {
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
