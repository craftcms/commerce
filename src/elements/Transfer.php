<?php

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\commerce\elements\conditions\transfers\TransferCondition;
use craft\commerce\elements\db\TransferQuery;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\InventoryLocation;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\commerce\records\Transfer as TransferRecord;
use craft\commerce\records\TransferDetail as TransferDetailRecord;
use craft\commerce\web\assets\transfers\TransfersAsset;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\CpScreenResponseBehavior;
use yii\web\Response;

/**
 * Transfer element type
 *
 * @property TransferStatusType $transferStatus
 * @property ?int $originLocationId
 * @property ?int $destinationLocationId
 */
class Transfer extends Element
{
    /**
     * The status of the transfer status
     *
     * @var TransferStatusType
     */
    public TransferStatusType $transferStatus = TransferStatusType::DRAFT;

    /**
     * The origin location ID of the transfer
     *
     * @var ?int
     */
    public ?int $originLocationId = null;

    /**
     * The destination location ID of the transfer
     *
     * @var ?int
     */
    public ?int $destinationLocationId = null;

    /**
     * The transfer detail lines
     *
     * @var TransferDetail[]
     */
    public ?array $_details = null;

    /**
     * Returns the string representation of the element.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->getOriginLocation() === null && $this->getDestinationLocation() === null) {
            return Craft::t('commerce', 'Transfer');
        }

        return (string)$this->getOriginLocation()->name . ' to ' . $this->getDestinationLocation()->name;
    }

    public static function hasDrafts(): bool
    {
        return false;
    }

    protected function metadata(): array
    {
        $additionalMeta = [];

        $additionalMeta[] = [
            Craft::t('commerce', 'Transfer Status') => \craft\helpers\Cp::statusIndicatorHtml($this->getTransferStatus()->label(), [
                    'color' => $this->getTransferStatus()->color(),
                ]) . ' ' . Html::tag('span', $this->getTransferStatus()->label()),
        ];

        if ($this->getIsDraft() && !$this->isProvisionalDraft) {
            $additionalMeta[] = [
                Craft::t('app', 'Status') => function() {
                    $icon = Html::tag('span', '', [
                        'data' => ['icon' => 'draft'],
                        'aria' => ['hidden' => 'true'],
                    ]);
                    $label = Craft::t('app', 'Draft');
                    return $icon . Html::tag('span', $label);
                },
            ];
        }

        $additionalMeta[] = [
            Craft::t('commerce', 'Transfer Status') => \craft\helpers\Cp::statusIndicatorHtml($this->getTransferStatus()->label(), [
                    'color' => $this->getTransferStatus()->color(),
                ]) . ' ' . Html::tag('span', $this->getTransferStatus()->label()),
        ];

        return ArrayHelper::merge(parent::metadata(), ...$additionalMeta); // TODO: Change the autogenerated stub
    }


    /**
     * @return ?InventoryLocation
     * @throws \yii\base\InvalidConfigException
     */
    public function getOriginLocation(): ?InventoryLocation
    {
        if (!$this->originLocationId) {
            return null;
        }

        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->originLocationId);
    }

    /**
     * @return ?InventoryLocation
     * @throws \yii\base\InvalidConfigException
     */
    public function getDestinationLocation(): ?InventoryLocation
    {
        if (!$this->destinationLocationId) {
            return null;
        }

        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->destinationLocationId);
    }

    /**
     * @return TransferStatusType
     */
    public function getTransferStatus(): TransferStatusType
    {
        return $this->transferStatus;
    }

    /**
     * @param TransferStatusType|string $status
     * @return void
     */
    public function setTransferStatus(TransferStatusType|string $status): void
    {
        if (is_string($status)) {
            $status = TransferStatusType::from($status);
        }

        $this->transferStatus = $status;
    }

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Transfer');
    }

    /**
     * @inheritDoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'transfer');
    }

    /**
     * @inheritDoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Transfers');
    }

    /**
     * @inheritDoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'transfers');
    }

    /**
     * @inheritDoc
     */
    public static function refHandle(): ?string
    {
        return 'transfer';
    }

    /**
     * @inheritDoc
     */
    public static function trackChanges(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function hasUris(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @return TransferQuery
     * @inheritDoc
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(TransferQuery::class, [static::class]);
    }

    /**
     * @inheritDoc
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(TransferCondition::class, [static::class]);
    }

    /**
     * @inheritDoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return false;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'slug' => Craft::t('app', 'Slug'),
            'uri' => Craft::t('app', 'URI'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            // ...
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'dateCreated',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        if ($this->scenario == static::SCENARIO_LIVE) {
            $rules = ArrayHelper::merge($rules, [
                [['originLocationId', 'destinationLocationId'], 'number', 'integerOnly' => true],
                [['originLocationId', 'destinationLocationId'], 'required'],
            ]);

            $rules[] = [['originLocationId', 'destinationLocationId'], 'validateLocations'];
            $rules[] = [['details'], 'validateDetails'];
        }

        return $rules;
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     * @return void
     */
    public function validateDetails($attribute, $params, $validator)
    {
        foreach ($this->getDetails() as $detail) {
            $this->addModelErrors($detail, 'detail');
        }
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     * @return void
     */
    public function validateLocations($attribute, $params, $validator)
    {
        if ($this->originLocationId == $this->destinationLocationId) {
            $this->addError($attribute, Craft::t('commerce', 'Origin and destination cannot be the same.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function getUriFormat(): ?string
    {
        return null;
    }

    /**
     * Define the sources for the transfer element index
     *
     * @param string|null $context
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $transferStatuses = TransferStatusType::cases();
        $transferStatusSources = [];
        foreach ($transferStatuses as $status) {
            $transferStatusSources[] = [
                'key' => $status->value,
                'status' => $status->color(),
                'label' => Craft::t('commerce', $status->label()),
                'badgeCount' => Transfer::find()->transferStatus($status->value)->count(),
                'criteria' => [
                    'transferStatus' => $status->value,
                ],
                // Define a default sort attribute and direction:
//                'defaultSort' => ['price', 'desc'],
            ];
        }

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        foreach ($inventoryLocations as $location) {
            $incomingTransferSources[] = [
                'key' => $location->id,
                'label' => Craft::t('commerce', $location->name),
                'badgeCount' => Transfer::find()->destinationLocation($location)->count(),
                'criteria' => [
                    'destinationLocationId' => $location->id,
                ],
            ];

            $outgoingTransferSources[] = [
                'key' => $location->id,
                'label' => Craft::t('commerce', $location->name),
                'badgeCount' => Transfer::find()->originLocation($location)->count(),
                'criteria' => [
                    'originLocationId' => $location->id,
                ],
            ];
        }

        return [
            [
                'key' => '*',
                'label' => Craft::t('commerce', 'All Transfers'),
                'criteria' => [],
            ],
            [
                // Optional: Divide your source list into groups!
                'heading' => Craft::t('commerce', 'Transfer Status'),
            ],
            ...$transferStatusSources,
            [
                // Optional: Divide your source list into groups!
                'heading' => Craft::t('commerce', 'Incoming Location'),
            ],
            ...$incomingTransferSources ?? [],
            [
                // Optional: Divide your source list into groups!
                'heading' => Craft::t('commerce', 'Outgoing Location'),
            ],
            ...$outgoingTransferSources ?? [],
        ];
    }

    /**
     *
     * @inheritDoc
     */
    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::lowerDisplayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    /**
     * @inheritDoc
     */
    protected function route(): array|string|null
    {
        // Define how transfers should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['transfer' => $this],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        return $user->can('commerce-manageTransfers');
    }

    /**
     * @inheritDoc
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        return $user->can('commerce-manageTransfers');
    }

    /**
     * @inheritDoc
     */
    public function canDuplicate(User $user): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canDelete(User $user): bool
    {
        $canDelete = false;

        if (parent::canSave($user)) {
            $canDelete = true;
        }

        if ($this->getTransferStatus() === TransferStatusType::DRAFT) {
            $canDelete = true;
        }

        return $canDelete && $user->can('commerce-manageTransfers');
    }

    /**
     * @inheritDoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("commerce/inventory/transfers/{$this->getCanonicalId()}");
    }

    /**
     * @inheritDoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/inventory/transfers');
    }

    /**
     * @inheritDoc
     */
    public function prepareEditScreen(Response $response, string $containerId): void
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(TransfersAsset::class);

        $view->registerJsWithVars(fn($containerId, $settingsJs) => <<<JS
new Craft.Commerce.TransferEdit($('#' + $containerId), $settingsJs);
JS, [
            $containerId,
            [],
        ]);


        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => Craft::t('commerce', 'Inventory'),
                'url' => UrlHelper::cpUrl('commerce/inventory'),
            ],
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('commerce/inventory/transfers'),
            ],
        ]);

        if ($this->getIsDraft() && !$this->isProvisionalDraft) {
            $response->title(Craft::t('commerce', 'Save draft'));
        } else {
            if ($this->transferStatus == TransferStatusType::DRAFT) {
                $response->submitButtonLabel(Craft::t('commerce', 'Save draft'));

                $response->additionalButtonsHtml(Html::button(Craft::t('commerce', 'Mark as Pending'), [
                    'class' => 'btn',
                    'data-action' => 'commerce/transfers/mark-as-pending',
                    'data-confirm' => Craft::t('commerce', 'Are you sure you want to mark this transfer as pending? This will show as incoming at the destination.'),
                    'data-redirect' => Craft::$app->getSecurity()->hashData('commerce/inventory/transfers/' . $this->id),
                ]));
            }
        }
    }

    /**
     * @return array
     */
    public function getDetails(): array
    {
        if ($this->_details === null) {
            $this->_details = Plugin::getInstance()->getTransfers()->getTransferDetailsByTransferId($this->id);
        }

        return $this->_details;
    }

    /**
     * @param TransferDetail[]|array $value
     *
     * @return void
     */
    public function setDetails(array $value): void
    {
        foreach ($value as $key => $detail) {
            if (!$detail instanceof TransferDetail) {
                $value[$key] = new TransferDetail($detail);
            }

            $detail->transferId = $this->id;
        }

        $this->_details = $value;
    }

    /**
     * @inheritDoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return Plugin::getInstance()->getTransfers()->getFieldLayout();
    }

    /**
     * @inheritDoc
     */
    public function beforeValidate()
    {
        if ($this->transferStatus === null) {
            $this->transferStatus = TransferStatusType::DRAFT;
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritDoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $transferId = $this->getCanonicalId();
            $transferRecord = TransferRecord::findOne($transferId);

            if (!$transferRecord) {
                $transferRecord = new TransferRecord();
            }

            $transferRecord->id = $this->id;
            $transferRecord->originLocationId = $this->originLocationId;
            $transferRecord->destinationLocationId = $this->destinationLocationId;
            $transferRecord->transferStatus = $this->getTransferStatus()->value ?? TransferStatusType::DRAFT->value;

            $transferRecord->save(false);

            foreach ($this->getDetails() as $detail) {
                if ($detail->id) {
                    $detailRecord = TransferDetailRecord::findOne($detail->id);
                } else {
                    $detailRecord = new TransferDetailRecord();
                }
                $detailRecord->transferId = $this->id;
                $detailRecord->inventoryItemId = $detail->inventoryItemId;
                $inventoryItem = $detail->inventoryItemId ? Plugin::getInstance()->getInventory()->getInventoryItemById($detail->inventoryItemId) : null;
                $detailRecord->inventoryItemDescription = $inventoryItem?->sku ?? '';
                $detailRecord->quantity = $detail->quantity;
                $detailRecord->quantityAccepted = $detail->quantityAccepted;
                $detailRecord->quantityRejected = $detail->quantityRejected;

                $detailRecord->save();
            }
        }

        parent::afterSave($isNew);
    }
}
