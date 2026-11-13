<?php

declare(strict_types=1);

namespace CraftCms\Commerce\License\Elements;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\License\Licenses;
use CraftCms\Commerce\License\Models\License as LicenseRecord;
use CraftCms\Commerce\License\Queries\LicenseQuery;
use CraftCms\Commerce\License\Validation\LicenseRules;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use DateTime;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

/**
 * A license for one unit of a purchased {@see Variant} that has “Issues License” turned on.
 *
 * @property-read Variant|null $variant
 * @property-read Order|null $order
 * @property-read User|null $owner
 */
#[Ruleset(LicenseRules::class)]
class License extends Element
{
    public ?int $variantId = null;

    public ?int $orderId = null;

    public ?string $licenseKey = null;

    public ?string $ownerName = null;

    public ?string $ownerEmail = null;

    public ?int $userId = null;

    private Variant|false|null $_variant = null;

    private ?int $_variantFetchedForId = null;

    private User|false|null $_owner = null;

    private Order|false|null $_order = null;

    #[Override]
    public function __toString(): string
    {
        if (!$this->variantId) {
            return t('New license', category: 'commerce');
        }

        return t('License for “{product}”', ['product' => $this->getVariantName()], category: 'commerce');
    }

    #[Override]
    public static function displayName(): string
    {
        return t('License', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('license', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Licenses', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('licenses', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'license';
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function find(): LicenseQuery
    {
        return new LicenseQuery();
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $types = array_filter(app(ProductTypes::class)->getAllProductTypes(), fn(ProductType $type) => $type->hasLicenses);

        $sources = [
            [
                'key' => '*',
                'label' => t('All licenses', category: 'commerce'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        if (!empty($types)) {
            $sources[] = ['heading' => t('Product Types', category: 'commerce')];
        }

        foreach ($types as $type) {
            $key = 'productType:' . $type->uid;
            $sources[$key] = [
                'key' => $key,
                'label' => t($type->name, category: 'site'),
                'data' => ['handle' => $type->handle],
                'criteria' => ['typeId' => $type->id],
                'defaultSort' => ['dateCreated', 'desc'],
            ];
        }

        return $sources;
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        return [
            ElementActions::createAction([
                'type' => Delete::class,
                'confirmationMessage' => t('Are you sure you want to delete the selected licenses?', category: 'commerce'),
                'successMessage' => t('Licenses deleted.', category: 'commerce'),
            ], static::class),
        ];
    }

    #[Override]
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'variant' => ['label' => t('Licensed Variant', category: 'commerce')],
            'productType' => ['label' => t('Product Type', category: 'commerce')],
            'licenseKey' => ['label' => t('License Key', category: 'commerce')],
            'dateCreated' => ['label' => t('License Issue Date', category: 'commerce')],
            'licensedTo' => ['label' => t('Licensed To', category: 'commerce')],
            'orderLink' => ['label' => t('Associated Order', category: 'commerce')],
            'id' => ['label' => t('ID', category: 'commerce')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            ...($source === '*' ? ['productType'] : []),
            'variant',
            'dateCreated',
            'licensedTo',
            'orderLink',
        ];
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            'ownerEmail' => t('Owner', category: 'commerce'),
            [
                'label' => t('License Issue Date', category: 'commerce'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['licenseKey', 'licensedTo', 'ownerName', 'variantName'];
    }

    #[Override]
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        match ($attribute) {
            'variant', 'productType' => $elementQuery->andWith('variant'),
            'licensedTo' => $elementQuery->andWith('owner'),
            default => parent::prepElementQueryForTableAttribute($elementQuery, $attribute),
        };
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        $column = match ($handle) {
            'variant' => 'variantId',
            'order' => 'orderId',
            'owner' => 'userId',
            default => null,
        };

        if ($column === null) {
            return parent::eagerLoadingMap($sourceElements, $handle);
        }

        $map = DB::table(Table::LICENSES)
            ->select(['id as source', "$column as target"])
            ->whereIn('id', array_filter(array_map(fn(ElementInterface $element) => $element->id, $sourceElements)))
            ->whereNotNull($column)
            ->get()
            ->map(fn(object $row) => (array)$row)
            ->all();

        return [
            'elementType' => match ($handle) {
                'variant' => Variant::class,
                'order' => Order::class,
                default => User::class,
            },
            'map' => $map,
            'criteria' => $handle === 'variant' ? ['status' => null] : [],
        ];
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        match ($handle) {
            'variant' => [$this->_variant, $this->_variantFetchedForId] = [$elements[0] ?? false, $this->variantId],
            'order' => $this->_order = $elements[0] ?? false,
            'owner' => $this->_owner = $elements[0] ?? false,
            default => parent::setEagerLoadedElements($handle, $elements, $plan),
        };
    }

    public function getVariant(): ?Variant
    {
        if (!$this->variantId) {
            return null;
        }

        if ($this->_variant === null || $this->_variantFetchedForId !== $this->variantId) {
            /** @var Variant|null $variant */
            $variant = Variant::find()->id($this->variantId)->status(null)->one();
            $this->_variant = $variant ?? false;
            $this->_variantFetchedForId = $this->variantId;
        }

        return $this->_variant ?: null;
    }

    public function getProduct(): ?Product
    {
        return $this->getVariant()?->getOwner();
    }

    public function getProductType(): ?ProductType
    {
        return $this->getProduct()?->getType();
    }

    /**
     * The licensed variant's description, such as “Product - Variant”.
     */
    public function getVariantName(): string
    {
        $variant = $this->getVariant();

        return $variant ? $variant->getDescription() : '';
    }

    public function getOrder(): ?Order
    {
        if ($this->_order === null) {
            $this->_order = ($this->orderId ? app(Orders::class)->getOrderById($this->orderId) : null) ?? false;
        }

        return $this->_order ?: null;
    }

    public function getOwner(): ?User
    {
        if ($this->_owner === null) {
            $this->_owner = ($this->userId ? Users::getUserById($this->userId) : null) ?? false;
        }

        return $this->_owner ?: null;
    }

    /**
     * Returns the email of the license's owner: the user's email when the license is assigned to one, else the owner email.
     */
    public function getLicensedTo(): string
    {
        return (string)($this->getOwner()->email ?? $this->ownerEmail);
    }

    public function getOrderEditUrl(): string
    {
        return $this->orderId ? Url::cpUrl('commerce/orders/' . $this->orderId) : '';
    }

    /**
     * The URL that downloads the licensed variant's file, or a specific asset when one is given.
     */
    public function getDownloadUrl(?int $assetId = null): string
    {
        return Url::actionUrl('commerce/licenses/download', array_filter([
            'licenseKey' => $this->licenseKey,
            'assetId' => $assetId,
        ]));
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return app(Licenses::class)->getFieldLayout();
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return 'commerce/licenses/' . $this->getCanonicalId();
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('commerce/licenses');
    }

    /**
     * @return list<ActionItem>
     */
    #[Override]
    protected function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            new ActionItem()->label(static::pluralDisplayName())->href(Url::cpUrl('commerce/licenses')),
        ];
    }

    #[Override]
    protected function metadata(): array
    {
        if (!$this->licenseKey || !$this->id) {
            return [];
        }

        return [
            t('License Key', category: 'commerce') => Html::tag('code', Html::encode($this->licenseKey)),
        ];
    }

    /**
     * Declared alongside {@see metaFieldsNodes()}, which the element editor only uses when both are declared by the same class.
     */
    #[Override]
    protected function metaFieldsHtml(bool $static): string|\Stringable
    {
        return parent::metaFieldsHtml($static);
    }

    /**
     * @return list<Node>
     */
    #[Override]
    protected function metaFieldsNodes(bool $static): array
    {
        $mode = $static ? ControlMode::Disabled : ControlMode::Editable;
        // Fresh drafts already have an ID and a generated key, but haven't been issued yet
        $isSaved = $this->id && $this->licenseKey && !$this->getIsUnpublishedDraft();

        $nodes = [
            $isSaved && !$this->variantId
                ? Field::make(t('Variant', category: 'commerce'))
                    ->instructions(t('A deleted variant', category: 'commerce'))
                    ->control(Text::make('variantName')->value($this->getVariantName())->mode(ControlMode::Disabled))
                : Field::make(t('Variant', category: 'commerce'))
                    ->required()
                    ->control(
                        ElementSelect::make('variant')
                            ->elementType(Variant::class)
                            ->criteria(['status' => null, 'issuesLicense' => true])
                            ->limit(1)
                            ->value(array_filter([$this->variantId]))
                            // The variant can't change once a license has been issued for it
                            ->mode($isSaved ? ControlMode::Disabled : $mode),
                    ),
        ];

        if (!$isSaved) {
            $nodes[] = Field::make(t('License Key', category: 'commerce'))
                ->instructions(t('Leave blank to generate automatically.', category: 'commerce'))
                ->control(Text::make('licenseKey')->value($this->licenseKey)->monospace()->mode($mode));
        }

        $nodes[] = Field::make(t('Owner', category: 'commerce'))
            ->instructions(t('Required if Owner Email is blank.', category: 'commerce'))
            ->control(
                ElementSelect::make('owner')
                    ->elementType(User::class)
                    ->limit(1)
                    ->value(array_filter([$this->userId]))
                    ->mode($mode),
            );

        $nodes[] = Field::make(t('Owner Name', category: 'commerce'))
            ->control(Text::make('ownerName')->value($this->ownerName)->mode($mode));

        if (!$this->userId) {
            $nodes[] = Field::make(t('Owner Email', category: 'commerce'))
                ->instructions(t('Required if Owner is blank.', category: 'commerce'))
                ->control(Text::make('ownerEmail')->value($this->ownerEmail)->mode($mode));
        }

        return $nodes;
    }

    #[Override]
    public function setAttributesFromRequest(array $values): void
    {
        // Element selects post arrays of IDs
        foreach (['variant' => 'variantId', 'owner' => 'userId'] as $param => $attribute) {
            if (array_key_exists($param, $values)) {
                $ids = (array)$values[$param];
                $this->$attribute = ($id = reset($ids)) ? (int)$id : null;
                unset($values[$param]);
            }
        }

        foreach (['licenseKey', 'ownerName', 'ownerEmail'] as $attribute) {
            if (array_key_exists($attribute, $values)) {
                $this->$attribute = $values[$attribute] !== '' ? $values[$attribute] : null;
                unset($values[$attribute]);
            }
        }

        $this->_owner = null;

        parent::setAttributesFromRequest($values);
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'variant' => Html::encode($this->getVariantName()),
            'productType' => ($type = $this->getProductType()) ? t(Html::encode($type->name), category: 'site') : '',
            'licenseKey' => Html::tag('code', Html::encode((string)$this->licenseKey)),
            'licensedTo' => Html::encode($this->getLicensedTo()),
            'orderLink' => $this->orderId
                ? Html::a(t('View order', category: 'commerce'), $this->getOrderEditUrl())
                : '',
            default => parent::attributeHtml($attribute),
        };
    }

    #[Override]
    protected function searchKeywords(string $attribute): string
    {
        return match ($attribute) {
            'licensedTo' => $this->getLicensedTo(),
            'variantName' => $this->getVariantName(),
            default => parent::searchKeywords($attribute),
        };
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $record = $isNew ? null : LicenseRecord::query()->find($this->id);

            if (!$record) {
                $record = new LicenseRecord();
                $record->id = $this->id;
                $record->dateCreated = Query::prepareDateForDb($this->dateCreated ?? new DateTime());
            }

            $user = match (true) {
                $this->userId !== null => Users::getUserById($this->userId),
                $this->ownerEmail !== null => Users::getUserByUsernameOrEmail($this->ownerEmail),
                default => null,
            };

            // Assign the license to the user whose email matches the owner email
            if ($this->userId === null && $user && app(Plugin::class)->getSettings()->autoAssignLicensesOnPurchase) {
                $this->userId = $user->id;
            }

            $record->userId = $this->userId;
            $record->ownerName = $this->userId && $user ? $user->getName() : $this->ownerName;
            $record->ownerEmail = $this->userId && $user ? $user->email : $this->ownerEmail;

            // The order, variant and key are fixed once the license is issued
            if ($isNew) {
                $record->orderId = $this->orderId;
            }

            if ($isNew || $record->variantId === null) {
                $record->variantId = $this->variantId;
                $record->licenseKey = $this->licenseKey ?: app(Licenses::class)->generateLicenseKey($this);
            }

            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated ?? new DateTime());
            $record->save();

            $this->licenseKey = $record->licenseKey;
            $this->ownerName = $record->ownerName;
            $this->ownerEmail = $record->ownerEmail;
            $this->_owner = null;
        }

        parent::afterSave($isNew);
    }
}
