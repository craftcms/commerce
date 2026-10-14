<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\ElementSources;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Shared\Enums\DateRangeType;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Order\Actions\CopyLoadCartUrl;
use CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction;
use CraftCms\Commerce\Order\Actions\UpdateOrderStatus;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * The Inertia payload for the order index (`commerce::orders/Index`).
 *
 * The site menu picks the store: sources, criteria and actions all follow the
 * selected site's store.
 */
class OrderIndexViewModel extends ContentIndexViewModel
{
    public const string UPDATE_ORDER_STATUS_EVENT = 'commerce:update-order-status';

    public const string COPY_LOAD_CART_URL_EVENT = 'commerce:copy-load-cart-url';

    public const string DOWNLOAD_ORDER_PDF_EVENT = 'commerce:download-order-pdf';

    /**
     * The date range presets offered alongside a custom range.
     */
    private const array DATE_RANGE_PRESETS = [
        DateRangeType::Today,
        DateRangeType::ThisWeek,
        DateRangeType::ThisMonth,
        DateRangeType::ThisYear,
        DateRangeType::Past7Days,
        DateRangeType::Past30Days,
        DateRangeType::Past90Days,
        DateRangeType::PastYear,
    ];

    private ?Store $resolvedStore = null;

    public function __construct(
        ElementIndexRequest $request,
        private readonly ?string $orderStatusHandle = null,
    ) {
        parent::__construct(Order::class, $request);
    }

    public function orderStatusHandle(): string
    {
        return $this->orderStatusHandle ?? '';
    }

    /**
     * Public so the page can build its index route from it.
     */
    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/orders');
    }

    /**
     * Orders aren't localized, but the site still picks which store's orders are listed.
     */
    #[Override]
    public function showSiteMenu(): bool
    {
        return Sites::isMultiSite();
    }

    public function newOrderUrl(): ?string
    {
        $store = $this->store();

        if ($store === null || !currentUser()?->can('commerce-editOrders')) {
            return null;
        }

        return Url::cpUrl("commerce/orders/$store->handle/create");
    }

    public function newOrderLabel(): string
    {
        return t('New Order', category: 'commerce');
    }

    /**
     * The requested date range, echoed back for the date range menu.
     *
     * @return array{type: string|null, from: string|null, to: string|null}
     */
    public function dateRange(): array
    {
        $type = $this->dateRangeType();

        return [
            'type' => $type?->value,
            'from' => $type === DateRangeType::Range ? $this->dateParam('dateFrom')?->format('Y-m-d') : null,
            'to' => $type === DateRangeType::Range ? $this->dateParam('dateTo')?->format('Y-m-d') : null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function dateRangeOptions(): array
    {
        return array_map(fn(DateRangeType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::DATE_RANGE_PRESETS);
    }

    /**
     * The store's order statuses, for the “Update Order Status” modal.
     *
     * @return list<array{id: int, handle: string, name: string, color: string}>|null
     */
    public function orderStatuses(): ?array
    {
        $store = $this->store();

        if ($store === null || !currentUser()?->can('commerce-editOrders')) {
            return null;
        }

        return app(OrderStatuses::class)->getAllOrderStatuses($store->id)
            ->map(fn(OrderStatus $orderStatus) => [
                'id' => (int)$orderStatus->id,
                'handle' => (string)$orderStatus->handle,
                'name' => t((string)$orderStatus->name, category: 'site'),
                'color' => $orderStatus->color,
            ])
            ->values()
            ->all();
    }

    /**
     * The store's enabled PDFs, for the “Download PDF” popover.
     *
     * @return list<array{id: int, name: string}>
     */
    public function pdfs(): array
    {
        $store = $this->store();

        if ($store === null || !currentUser()?->can('commerce-manageOrders')) {
            return [];
        }

        return app(Pdfs::class)->getAllEnabledPdfs($store->id)
            ->map(fn(Pdf $pdf) => [
                'id' => (int)$pdf->id,
                'name' => t((string)$pdf->name, category: 'site'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [
            'all' => t('All'),
            'apply' => t('Apply'),
            'cancel' => t('Cancel', category: 'commerce'),
            'collatedPdf' => t('Collated PDF', category: 'commerce'),
            'copyTheUrl' => t('Copy the URL', category: 'commerce'),
            'dateRange' => t('Date Range'),
            'download' => t('Download', category: 'commerce'),
            'downloadType' => t('Download Type', category: 'commerce'),
            'from' => t('From'),
            'message' => t('Message', category: 'commerce'),
            'orderStatus' => t('Order Status', category: 'commerce'),
            'pdf' => t('PDF', category: 'commerce'),
            'range' => t('Range…'),
            'statusChangeMessage' => t('Status change message', category: 'commerce'),
            'suppressEmails' => t('Suppress emails', category: 'commerce'),
            'to' => t('To'),
            'update' => t('Update', category: 'commerce'),
            'updateOrderStatus' => t('Update Order Status', category: 'commerce'),
            'zipFile' => t('ZIP file', category: 'commerce'),
        ];
    }

    /**
     * Swaps the order actions that need a settings step for events the page
     * handles, and scopes every action request to the selected site's store.
     *
     * @return list<array<string, mixed>>|null
     */
    #[Override]
    public function actions(): ?array
    {
        $actions = parent::actions();

        if ($actions === null) {
            return null;
        }

        $siteParams = ['site' => $this->site()->handle];
        $events = [
            UpdateOrderStatus::class => self::UPDATE_ORDER_STATUS_EVENT,
            CopyLoadCartUrl::class => self::COPY_LOAD_CART_URL_EVENT,
            DownloadOrderPdfAction::class => self::DOWNLOAD_ORDER_PDF_EVENT,
        ];

        return array_map(function(array $item) use ($siteParams, $events): array {
            if (!isset($item['action']['url'])) {
                return $item;
            }

            $url = Url::urlWithParams($item['action']['url'], $siteParams);
            $event = $events[$item['key']] ?? null;

            if ($event === null) {
                $item['action']['url'] = $url;

                return $item;
            }

            $item['action'] = [
                'type' => 'event',
                'name' => $event,
                'detail' => [
                    'url' => $url,
                    'body' => [
                        ...$item['action']['body'],
                        'source' => $this->sourceState()[0],
                        'context' => $this->context(),
                    ],
                ],
            ];

            return $item;
        }, $actions);
    }

    /**
     * @return list<ActionItem|array<string, mixed>>
     */
    #[Override]
    public function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            ...parent::crumbs(),
        ];
    }

    /**
     * Limits the orders to the selected site's store, and to the requested
     * date range on the source's date attribute.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function baseCriteria(): array
    {
        $criteria = parent::baseCriteria();
        $store = $this->store();

        if ($store !== null) {
            $criteria['storeId'] = $store->id;
        }

        $bounds = $this->dateRangeBounds();

        if ($bounds === []) {
            return $criteria;
        }

        $attribute = $this->sourceState()[1]['data']['date-attr'] ?? 'dateUpdated';
        $existing = $criteria[$attribute] ?? null;

        $criteria[$attribute] = match (true) {
            $existing === null => ['and', ...$bounds],
            is_array($existing) && ($existing[0] ?? null) === 'and' => [...$existing, ...$bounds],
            is_array($existing) && count($existing) === 1 => ['and', reset($existing), ...$bounds],
            default => ['and', $existing, ...$bounds],
        };

        return $criteria;
    }

    /**
     * Keeps the date range when switching sources.
     *
     * @param array<string, mixed> $source
     */
    #[Override]
    protected function sourceUrl(array $source): ?string
    {
        $uri = Order::sourceCpUri($source);
        $url = $uri === null ? parent::sourceUrl($source) : Url::cpUrl($uri);
        ['type' => $type, 'from' => $from, 'to' => $to] = $this->dateRange();
        $params = array_filter(['dateRange' => $type, 'dateFrom' => $from, 'dateTo' => $to]);

        return $url === null || $params === [] ? $url : Url::urlWithParams($url, $params);
    }

    /**
     * Maps the status or cart handle route segment (`commerce/orders/{handle}`) to its source key.
     */
    #[Override]
    protected function defaultSourceKey(): ?string
    {
        if ($this->orderStatusHandle === null || $this->orderStatusHandle === '') {
            return null;
        }

        foreach (Order::sources(ElementSources::CONTEXT_INDEX) as $source) {
            if (isset($source['key']) && ($source['data']['handle'] ?? null) === $this->orderStatusHandle) {
                return $source['key'];
            }
        }

        return null;
    }

    private function store(): ?Store
    {
        return $this->resolvedStore ??= app(Stores::class)->getStoreBySiteId($this->site()->id);
    }

    private function dateRangeType(): ?DateRangeType
    {
        $type = DateRangeType::tryFrom((string)$this->request->input('dateRange', ''));

        return $type === DateRangeType::Range || in_array($type, self::DATE_RANGE_PRESETS, true) ? $type : null;
    }

    /**
     * The date range as query param conditions: the start inclusive, the end exclusive.
     *
     * @return list<string>
     */
    private function dateRangeBounds(): array
    {
        $type = $this->dateRangeType();

        if ($type === null) {
            return [];
        }

        if ($type !== DateRangeType::Range) {
            [$start, $end] = $type->range();

            return [
                '>= ' . $this->utcDate($start),
                '< ' . $this->utcDate($end),
            ];
        }

        $from = $this->dateParam('dateFrom');
        $to = $this->dateParam('dateTo');

        return array_values(array_filter([
            $from ? '>= ' . $this->utcDate($from) : null,
            $to ? '< ' . $this->utcDate($to->addDay()) : null,
        ]));
    }

    /**
     * Dates are stored in UTC, and date params are compared as given.
     */
    private function utcDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->utc()->toIso8601String();
    }

    private function dateParam(string $name): ?Carbon
    {
        $value = $this->request->input($name);

        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $value) ?: null;
    }
}
