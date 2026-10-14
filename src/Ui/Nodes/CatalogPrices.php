<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Ui\Nodes;

use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\NodePayload;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\UiHtmlRenderer;
use CraftCms\Cms\Ui\UiPayload;
use Illuminate\Support\Traits\Conditionable;

use function CraftCms\Cms\t;

/**
 * A collapsible {@see Table} of the catalog pricing rules applied to a purchasable, which opens
 * rules in a wide slideout and re-fetches its rows after a rule is saved and while pricing jobs run.
 *
 * Commerce-owned, registered by Commerce's own Vite bundle — see {@see UsageCounter} for why.
 */
class CatalogPrices implements Node
{
    use Conditionable;

    private ?string $createUrl = null;

    private bool $pricesChanged = false;

    /**
     * @param array{heading: string, rows: list<array<string, mixed>>, jobsRunning: bool} $data
     * @param array{purchasableId: int, storeId: int} $refreshParams
     */
    private function __construct(
        private readonly string $uid,
        private readonly array $data,
        private readonly array $refreshParams,
    ) {
    }

    /**
     * @param array{heading: string, rows: list<array<string, mixed>>, jobsRunning: bool} $data As returned by {@see \CraftCms\Commerce\CatalogPricing\PurchasableCatalogPrices::data()}
     * @param array{purchasableId: int, storeId: int} $refreshParams
     */
    public static function make(string $uid, array $data, array $refreshParams): self
    {
        return new self($uid, [...$data, 'rows' => self::tableRows($data['rows'], $refreshParams['purchasableId'])], $refreshParams);
    }

    /**
     * Turns {@see \CraftCms\Commerce\CatalogPricing\PurchasableCatalogPrices} rows into table rows.
     *
     * @param list<array{
     *     id: string,
     *     name: string,
     *     url: string|null,
     *     effect: string,
     *     basedOnPromotionalPrice: bool,
     *     price: string,
     *     isPromotionalPrice: bool,
     *     lowestForEveryone: bool,
     *     specificCustomers: bool,
     * }> $rows
     * @return list<array<string, mixed>>
     */
    public static function tableRows(array $rows, int $purchasableId): array
    {
        $secondary = fn(string $text): string => Html::tag('div', Html::encode($text), ['class' => 'catalog-prices__secondary']);

        $badge = function(string $id, string $fill, string $label, string $info): string {
            return Html::tag('craft-badge', Html::encode($label), ['id' => $id, 'fill' => $fill, 'size' => 'small', 'tabindex' => '0']) .
                Html::tag('craft-tooltip', Html::encode($info), ['for' => $id]);
        };

        return Table::prepareRows(array_map(function(array $row) use ($secondary, $badge, $purchasableId) {
            $idPrefix = "catalog-price-$purchasableId-{$row['id']}";
            $badges = ($row['lowestForEveryone'] ? $badge("$idPrefix-lowest", 'green', t('Lowest for everyone', category: 'commerce'), t('The price customers pay when no customer-specific rule applies to them.', category: 'commerce')) : '') .
                ($row['specificCustomers'] ? $badge("$idPrefix-customers", 'violet', t('Specific customers', category: 'commerce'), t('Only applies to customers matching this rule’s customer condition.', category: 'commerce')) : '');

            $name = $row['url'] !== null
                ? Html::a(Html::encode($row['name']), $row['url'], ['data-catalog-pricing-rule' => true])
                : Html::encode($row['name']);

            return [
                'id' => $row['id'],
                'rule' => ['html' => Html::tag('div', $name . $badges, ['class' => 'catalog-prices__rule'])],
                'effect' => ['html' => Html::encode($row['effect']) . ($row['basedOnPromotionalPrice'] ? $secondary(t('Based on the promotional price', category: 'commerce')) : '')],
                'price' => ['html' => ($row['lowestForEveryone'] ? Html::tag('strong', Html::encode($row['price'])) : Html::encode($row['price'])) .
                    ($row['isPromotionalPrice'] ? $secondary(t('Promotional price', category: 'commerce')) : ''), ],
            ];
        }, $rows));
    }

    public function createUrl(?string $createUrl): static
    {
        $this->createUrl = $createUrl;

        return $this;
    }

    public function pricesChanged(bool $pricesChanged = true): static
    {
        $this->pricesChanged = $pricesChanged;

        return $this;
    }

    public static function renderHtml(NodePayload $node, UiPayload $payload, UiHtmlRenderer $renderer): string
    {
        return Html::tag('details',
            Html::tag('summary', Html::encode($node->props['heading'])) . $renderer->renderNodes($node->children ?? [], $payload),
            ['data-ui-node' => $node->uid],
        );
    }

    public function component(): string
    {
        return 'commerce:catalog-prices';
    }

    public function uid(): ?string
    {
        return $this->uid;
    }

    public function props(): array
    {
        return [
            'heading' => $this->data['heading'],
            'jobsRunning' => $this->data['jobsRunning'],
            'refreshAction' => 'commerce/catalog-pricing/purchasable-prices',
            'refreshParams' => $this->refreshParams,
            'createUrl' => $this->createUrl,
            'pricesChanged' => $this->pricesChanged,
            'labels' => [
                'pricesChanged' => t('Prices will update after saving.', category: 'commerce'),
                'jobsRunning' => t('Updating prices…', category: 'commerce'),
            ],
        ];
    }

    public function getControl(): ?Control
    {
        return null;
    }

    public function children(): array
    {
        return [
            Table::make($this->uid . '-table')
                ->columns([
                    ['key' => 'rule', 'label' => t('Pricing rule', category: 'commerce')],
                    ['key' => 'effect', 'label' => t('Effect', category: 'commerce')],
                    ['key' => 'price', 'label' => t('Price', category: 'commerce')],
                ])
                ->rows($this->data['rows'])
                ->emptyMessage(t('No pricing rules currently apply.', category: 'commerce'))
                ->when($this->createUrl !== null, fn(Table $table) => $table->createAction(t('Add catalog price', category: 'commerce'), $this->createUrl)),
        ];
    }
}
