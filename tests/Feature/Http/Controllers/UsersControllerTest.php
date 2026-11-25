<?php

declare(strict_types=1);

use CraftCms\Cms\Element\ElementSources;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use CraftCms\Commerce\Tests\Support\StoresFixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->stores = StoresFixture::seed();
    $this->fixture = OrdersFixture::seed();
});

/**
 * @param array<int, mixed>|Collection<int, mixed> $nodes
 * @return list<array<string, mixed>>
 */
function findUiNodes(array|Collection $nodes, string $component): array
{
    $found = [];

    foreach ($nodes as $node) {
        if (data_get($node, 'component') === $component) {
            $found[] = $node;
        }

        $found = [...$found, ...findUiNodes(data_get($node, 'children', []), $component)];
    }

    return $found;
}

/**
 * @param array<string, mixed> $node
 * @return list<int>
 */
function listedOrderIds(array $node): array
{
    return collect($node['props']['initial']['data'])->pluck('id')->sort()->values()->all();
}

it('lists a customer\'s orders and carts in a tab per store', function() {
    $orders = array_values($this->fixture->orders);
    DB::table(Table::ORDERS)->where('id', $orders[0]->id)->update(['storeId' => $this->stores->ukStore->id]);

    get("/admin/users/{$this->fixture->customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) use ($orders) {
            $nodes = $page->toArray()['props']['ui']['nodes'];
            $primary = $this->stores->primaryStore->handle;

            expect(collect(findUiNodes($nodes, 'craft:tab'))->pluck('uid')->all())
                ->toBe([$primary, 'euStore', 'ukStore']);

            $indexes = collect(findUiNodes($nodes, 'craft:element-index'))->keyBy('uid');

            expect($indexes->keys()->all())->toBe([
                "$primary-completed-orders-index",
                "$primary-active-carts-index",
                "$primary-inactive-carts-index",
                'euStore-completed-orders-index',
                'euStore-active-carts-index',
                'euStore-inactive-carts-index',
                'ukStore-completed-orders-index',
                'ukStore-active-carts-index',
                'ukStore-inactive-carts-index',
            ])
                ->and(listedOrderIds($indexes["$primary-completed-orders-index"]))
                ->toBe(collect([$orders[1]->id, $orders[2]->id])->sort()->values()->all())
                ->and(listedOrderIds($indexes['ukStore-completed-orders-index']))->toBe([$orders[0]->id])
                ->and(listedOrderIds($indexes['euStore-completed-orders-index']))->toBe([])
                ->and(listedOrderIds($indexes["$primary-active-carts-index"]))->toBe([]);
        });
});

it('only shows the stores whose sites the viewer can edit', function() {
    $viewer = new User();
    $viewer->username = 'viewer';
    $viewer->email = 'viewer@crafttest.com';
    $viewer->active = true;
    Elements::saveElement($viewer);

    $permissions = ['accessCp', 'viewUsers', 'accessPlugin-commerce', 'commerce-manageOrders', 'editSite:' . $this->stores->ukSite->uid];
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $permission): bool => in_array($permission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);

    actingAs($viewer);

    get("/admin/users/{$this->fixture->customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) {
            $nodes = $page->toArray()['props']['ui']['nodes'];

            expect(collect(findUiNodes($nodes, 'craft:tab'))->pluck('uid')->all())->toBe(['ukStore']);
        });
});

it('downloads a PDF from the store\'s own PDFs in a store tab', function() {
    $orders = array_values($this->fixture->orders);
    DB::table(Table::ORDERS)->where('id', $orders[0]->id)->update(['storeId' => $this->stores->ukStore->id]);

    $pdf = new Pdf([
        'storeId' => $this->stores->ukStore->id,
        'name' => 'UK Receipt',
        'handle' => 'ukReceipt',
        'templatePath' => 'shop/pdf/order',
        'isDefault' => true,
        'enabled' => true,
    ]);
    expect(app(Pdfs::class)->savePdf($pdf))->toBeTrue();

    $ukIndex = null;

    get("/admin/users/{$this->fixture->customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) use ($pdf, &$ukIndex) {
            $indexes = collect(findUiNodes($page->toArray()['props']['ui']['nodes'], 'craft:element-index'))->keyBy('uid');
            $primary = $this->stores->primaryStore->handle;
            $downloadItem = fn(array $index): ?array => collect($index['props']['initial']['actions'])
                ->firstWhere('key', DownloadOrderPdfAction::class);

            $ukIndex = $indexes['ukStore-completed-orders-index'];
            $item = $downloadItem($ukIndex);

            expect($item['action']['name'])->toBe(DownloadOrderPdfAction::CLIENT_EVENT)
                ->and($item['action']['detail']['url'])->toContain('site=ukSite')
                ->and($item['action']['detail']['pdfs'])->toBe([['id' => $pdf->id, 'name' => 'UK Receipt']])
                ->and($downloadItem($indexes["$primary-completed-orders-index"]))->toBeNull();
        });

    $pdfs = Mockery::mock(Pdfs::class)->makePartial();
    $pdfs->shouldReceive('renderPdfForOrder')->andReturn('%PDF-1.4');
    app()->instance(Pdfs::class, $pdfs);

    $item = collect($ukIndex['props']['initial']['actions'])->firstWhere('key', DownloadOrderPdfAction::class);

    post($item['action']['detail']['url'], [
        'context' => ElementSources::CONTEXT_EMBEDDED_INDEX,
        'source' => '__IMP__',
        'elementType' => Order::class,
        'embeddedIndexToken' => $ukIndex['props']['token'],
        ...$item['action']['detail']['body'],
        'elementIds' => [$orders[0]->id],
        'pdfId' => $pdf->id,
        'downloadType' => DownloadOrderPdfAction::TYPE_PDF_COLLATED,
    ])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload("ukReceipt-{$orders[0]->number}.pdf");
});
