<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\commerce\base\Plan;
use craft\commerce\base\PlanInterface;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\db\SubscriptionQuery;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\Plugin;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\db\Query;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use DateInterval;
use DateTime;
use Exception;
use yii\base\InvalidConfigException;

/**
 * Subscription model.
 *
 * @property bool $isOnTrial whether the subscription is still on trial
 * @property string $nextPaymentAmount
 * @property SubscriptionGatewayInterface $gateway
 * @property PlanInterface $plan
 * @property string $name
 * @property Plan[] $alternativePlans
 * @property string $orderEditUrl
 * @property string $planName
 * @property SubscriptionPayment[] $allPayments
 * @property User $subscriber
 * @property string $eagerLoadedElements
 * @property DateTime $trialExpires datetime of trial expiry
 * @property array $subscriptionData
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @since 2.0
 */
class Subscription extends Element
{
    /**
     * @var string
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * @var string
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var string
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @var int|null User id
     */
    public ?int $userId = null;

    /**
     * @var int|null Plan id
     */
    public ?int $planId = null;

    /**
     * @var int|null Gateway id
     */
    public ?int $gatewayId = null;

    /**
     * @var int|null Order id
     */
    public ?int $orderId = null;

    /**
     * @var string Subscription reference on the gateway
     */
    public string $reference = '';

    /**
     * @var int Trial days granted
     */
    public int $trialDays = 0;

    /**
     * @var DateTime|null Date of next payment
     */
    public ?DateTime $nextPaymentDate = null;

    /**
     * @var bool Whether the subscription is canceled
     */
    public bool $isCanceled = false;

    /**
     * @var DateTime|null Time when subscription was canceled
     */
    public ?DateTime $dateCanceled = null;

    /**
     * @var bool Whether the subscription has expired
     */
    public bool $isExpired = false;

    /**
     * @var DateTime|null Time when subscription expired
     */
    public ?DateTime $dateExpired = null;

    /**
     * @var bool Whether the subscription has started
     */
    public bool $hasStarted = false;

    /**
     * @var bool Whether the subscription is on hold due to payment issues
     */
    public bool $isSuspended = false;

    /**
     * @var DateTime|null Time when subscription was put on hold
     */
    public ?DateTime $dateSuspended = null;

    /**
     * @var string|null The URL to return to after a subscription is created
     */
    public ?string $returnUrl = null;

    /**
     * @var SubscriptionGatewayInterface|null
     */
    private ?SubscriptionGatewayInterface $_gateway = null;

    /**
     * @var Plan|null
     */
    private ?Plan $_plan = null;

    /**
     * @var User|null
     */
    private ?User $_user = null;

    /**
     * @var Order|null
     */
    private ?Order $_order = null;

    /**
     * @var array|null The subscription data from gateway
     */
    public ?array $_subscriptionData = null;


    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Subscription');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'subscription');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Subscriptions');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'subscriptions');
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $plan = $this->getPlan();
        return Craft::t('commerce', 'Subscription to “{plan}”', ['plan' => $plan->name ?? '']);
    }

    public function canView(User $user): bool
    {
        return parent::canView($user) || $user->can('commerce-manageSubscriptions');
    }

    public function canSave(User $user): bool
    {
        return parent::canView($user) || $user->can('commerce-manageSubscriptions');
    }

    /**
     * Returns whether this subscription can be reactivated.
     *
     * @throws InvalidConfigException if gateway misconfigured
     */
    public function canReactivate(): bool
    {
        return $this->isCanceled && !$this->isExpired && $this->getGateway()->supportsReactivation();
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(static::class);
    }

    /**
     * Returns whether this subscription is on trial.
     *
     * @throws Exception
     */
    public function getIsOnTrial(): bool
    {
        if ($this->isExpired) {
            return false;
        }

        return $this->trialDays > 0 && time() <= $this->getTrialExpires()->getTimestamp();
    }

    /**
     * Returns the subscription plan for this subscription
     */
    public function getPlan(): ?Plan
    {
        if (!isset($this->_plan) && $this->planId) {
            $this->_plan = Plugin::getInstance()->getPlans()->getPlanById($this->planId);
        }

        return $this->_plan;
    }

    /**
     * Returns the User that is subscribed.
     */
    public function getSubscriber(): User
    {
        if (!isset($this->_user) && $this->userId) {
            $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->_user;
    }

    public function getSubscriptionData(): array
    {
        return $this->_subscriptionData ?? [];
    }

    public function setSubscriptionData(array|string $data): void
    {
        $data = Json::decodeIfJson($data);

        $this->_subscriptionData = $data;
    }

    /**
     * Returns the datetime of trial expiry.
     *
     * @throws Exception
     */
    public function getTrialExpires(): ?DateTIme
    {
        $created = clone $this->dateCreated;
        return $created->add(new DateInterval('P' . $this->trialDays . 'D'));
    }

    /**
     * Returns the next payment amount with currency code as a string.
     *
     * @throws InvalidConfigException
     */
    public function getNextPaymentAmount(): string
    {
        return $this->getGateway()->getNextPaymentAmount($this);
    }

    /**
     * Returns the order that included this subscription, if any.
     */
    public function getOrder(): ?Order
    {
        if ($this->_order) {
            return $this->_order;
        }

        if ($this->orderId) {
            return $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return null;
    }

    /**
     * Returns the product type for the product tied to the license.
     *
     * @throws InvalidConfigException if gateway misconfigured
     */
    public function getGateway(): ?SubscriptionGatewayInterface
    {
        if (!isset($this->_gateway) && $this->gatewayId) {
            $gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
            if (!$gateway instanceof SubscriptionGatewayInterface) {
                throw new InvalidConfigException('The gateway set for subscription does not support subscriptions.');
            }
            $this->_gateway = $gateway;
        }

        return $this->_gateway;
    }

    public function getPlanName(): string
    {
        return $this->getPlan()?->__toString() ?? '';
    }

    /**
     * Returns possible alternative plans for this subscription
     *
     * @return Plan[]
     */
    public function getAlternativePlans(): array
    {
        if ($this->gatewayId === null) {
            return [];
        }

        $plans = Plugin::getInstance()->getPlans()->getPlansByGatewayId($this->gatewayId);

        $currentPlan = $this->getPlan();

        $alternativePlans = [];

        foreach ($plans as $plan) {
            // For all plans that are not the current plan
            if ($currentPlan && $plan->id !== $currentPlan->id && $plan->canSwitchFrom($currentPlan)) {
                $alternativePlans[] = $plan;
            }
        }

        return $alternativePlans;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/subscriptions/' . $this->id);
    }

    /**
     * Returns the link for editing the order that purchased this license.
     */
    public function getOrderEditUrl(): string
    {
        if ($this->orderId) {
            return UrlHelper::cpUrl('commerce/orders/' . $this->orderId);
        }

        return '';
    }

    /**
     * Returns an array of all payments for this subscription.
     *
     * @return SubscriptionPayment[]
     * @throws InvalidConfigException
     */
    public function getAllPayments(): array
    {
        return $this->getGateway()->getSubscriptionPayments($this);
    }

    public function getName(): ?string
    {
        return Craft::t('commerce', 'Subscription to “{plan}”', ['plan' => $this->getPlanName()]);
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        if ($this->isExpired) {
            return self::STATUS_EXPIRED;
        }

        return $this->isSuspended ? self::STATUS_SUSPENDED : self::STATUS_ACTIVE;
    }


    /**
     * @inheritdoc
     */
    public static function defineSources(string $context = null): array
    {
        $plans = Plugin::getInstance()->getPlans()->getAllPlans();

        $planIds = [];

        foreach ($plans as $plan) {
            $planIds[] = $plan->id;
        }


        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('commerce', 'All active subscriptions'),
                'criteria' => ['planId' => $planIds],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Subscription plans')];

        foreach ($plans as $plan) {
            $key = 'plan:' . $plan->id;

            $sources[$key] = [
                'key' => $key,
                'label' => $plan->name,
                'data' => [
                    'handle' => $plan->handle,
                ],
                'criteria' => ['planId' => $plan->id],
            ];
        }

        $sources[] = ['heading' => Craft::t('commerce', 'Subscriptions on hold')];

        $criteriaFailedToStart = ['isSuspended' => true, 'hasStarted' => false];
        $sources[] = [
            'key' => 'carts:failed-to-start',
            'label' => Craft::t('commerce', 'Failed to start'),
            'criteria' => $criteriaFailedToStart,
            'defaultSort' => ['commerce_subscriptions.dateUpdated', 'desc'],
        ];

        $criteriaPaymentIssue = ['isSuspended' => true, 'hasStarted' => true];
        $sources[] = [
            'key' => 'carts:payment-issue',
            'label' => Craft::t('commerce', 'Payment method issue'),
            'criteria' => $criteriaPaymentIssue,
            'defaultSort' => ['commerce_subscriptions.dateUpdated', 'desc'],
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        if ($handle === 'subscriber') {
            $map = (new Query())
                ->select('id as source, userId as target')
                ->from(Table::SUBSCRIPTIONS)
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return [
                'elementType' => User::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'order') {
            $order = $elements[0] ?? null;
            $this->_order = $order instanceof Order ? $order : null;

            return;
        }

        if ($handle === 'subscriber') {
            $user = $elements[0] ?? null;
            $this->_user = $user instanceof User ? $user : null;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['userId', 'planId', 'gatewayId', 'reference', 'subscriptionData'], 'required'],
        ]);
    }

    /**
     * @inheritdocs
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => Craft::t('commerce', 'Active'),
            self::STATUS_EXPIRED => Craft::t('commerce', 'Expired'),
        ];
    }

    /**
     * @inheritdoc
     * @return SubscriptionQuery The newly created [[SubscriptionQuery]] instance.
     */
    public static function find(): SubscriptionQuery
    {
        return new SubscriptionQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $subscriptionRecord = SubscriptionRecord::findOne($this->id);

            if (!$subscriptionRecord) {
                throw new InvalidConfigException('Invalid subscription id: ' . $this->id);
            }
        } else {
            $subscriptionRecord = new SubscriptionRecord();
            $subscriptionRecord->id = $this->id;
        }

        $subscriptionRecord->planId = $this->planId;
        $subscriptionRecord->nextPaymentDate = $this->nextPaymentDate;
        $subscriptionRecord->subscriptionData = $this->subscriptionData;
        $subscriptionRecord->isCanceled = $this->isCanceled;
        $subscriptionRecord->dateCanceled = $this->dateCanceled;
        $subscriptionRecord->isExpired = $this->isExpired;
        $subscriptionRecord->dateExpired = $this->dateExpired;
        $subscriptionRecord->hasStarted = $this->hasStarted;
        $subscriptionRecord->isSuspended = $this->isSuspended;
        $subscriptionRecord->dateSuspended = $this->dateSuspended;
        $subscriptionRecord->returnUrl = $this->returnUrl;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
        $subscriptionRecord->dateUpdated = $this->dateUpdated;
        $subscriptionRecord->dateCreated = $this->dateCreated;

        // Some properties of the subscription are immutable
        if ($isNew) {
            $subscriptionRecord->gatewayId = $this->gatewayId;
            $subscriptionRecord->orderId = $this->orderId;
            $subscriptionRecord->reference = $this->reference;
            $subscriptionRecord->trialDays = $this->trialDays;
            $subscriptionRecord->userId = $this->userId;
        }

        $subscriptionRecord->save(false);

        parent::afterSave($isNew);
    }

    /**
     * Return a description of the billing issue (if any) with this subscription.
     *
     * @throws InvalidConfigException if not a subscription gateway anymore
     * @noinspection PhpUnused
     */
    public function getBillingIssueDescription(): string
    {
        return $this->getGateway()->getBillingIssueDescription($this);
    }

    /**
     * Return the form HTML for resolving the billing issue (if any) with this subscription.
     *
     * @throws InvalidConfigException if not a subscription gateway anymore
     * @noinspection PhpUnused
     */
    public function getBillingIssueResolveFormHtml(): string
    {
        return $this->getGateway()->getBillingIssueResolveFormHtml($this);
    }

    /**
     * Return whether this subscription has billing issues.
     *
     * @throws InvalidConfigException if not a subscription gateway anymore
     */
    public function getHasBillingIssues(): bool
    {
        return $this->getGateway()->getHasBillingIssues($this);
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce', 'Subscription plan')],
            'subscriber' => ['label' => Craft::t('commerce', 'Subscribing user')],
            'reference' => ['label' => Craft::t('commerce', 'Subscription reference')],
            'dateCanceled' => ['label' => Craft::t('commerce', 'Cancellation date')],
            'dateCreated' => ['label' => Craft::t('commerce', 'Subscription date')],
            'dateExpired' => ['label' => Craft::t('commerce', 'Expiry date')],
            'trialExpires' => ['label' => Craft::t('commerce', 'Trial expiry date')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'subscriber';
        $attributes[] = 'orderLink';
        $attributes[] = 'dateCreated';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'subscriber',
            'plan',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'plan':
                return $this->getPlanName();

            case 'subscriber':
                $subscriber = $this->getSubscriber();
                $url = $subscriber->getCpEditUrl();

                return '<a href="' . $url . '">' . Html::encode($subscriber) . '</a>';

            case 'orderLink':
                $url = $this->getOrderEditUrl();

                return $url ? '<a href="' . $url . '">' . Craft::t('commerce', 'View order') . '</a>' : '';

            default:
            {
                return parent::attributeHtml($attribute);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('commerce', 'Subscription date'),
                'orderBy' => 'commerce_subscriptions.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        switch ($attribute) {
            case 'subscriber':
                $elementQuery->andWith('subscriber');
                break;
            case 'orderLink':
                $elementQuery->andWith('order');
                break;
            default:
                parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }
}
