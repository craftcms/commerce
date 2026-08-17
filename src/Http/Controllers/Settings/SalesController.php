<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\elements\Category;
use CraftCms\Cms\Entry\Elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Promotion\Records\Sale as SaleRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class SalesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    private function guard(): void
    {
        abort_unless(Plugin::getInstance()->getSales()->canUseSales(), 403, 'Unable to use sales while using multi store or pricing rules.');
    }

    public function index(?string $storeHandle = null): Response|string
    {
        $this->guard();

        $store = $this->resolveStore($storeHandle);
        $sales = Plugin::getInstance()->getSales()->getAllSales();
        if (empty($sales)) {
            return redirect('commerce/store-management/' . $store->handle . '/pricing-rules');
        }

        return pageTemplate('commerce/promotions/sales/index', [
            'sales' => $sales,
            'storeHandle' => $store->handle,
            'storeSwitcher' => $this->getStoreSwitcher($store->handle),
            'storeSettingsNav' => $this->getStoreSettingsNav(),
        ], TemplateMode::Cp);
    }

    public function edit(?int $id = null, ?string $storeHandle = null): string
    {
        $this->guard();

        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createSales' : 'commerce-editSales'), 403);

        $store = $this->resolveStore($storeHandle);

        $isNewSale = false;
        if ($id) {
            $sale = Plugin::getInstance()->getSales()->getSaleById($id);
            abort_if($sale === null, 404);
        } else {
            $sale = new Sale();
            $isNewSale = true;
            $sale->allCategories = true;
            $sale->allPurchasables = true;
            $sale->allGroups = true;
        }

        $variables = $this->populateVariables($id, $sale, $store->handle);
        $variables['isNewSale'] = $isNewSale;

        return pageTemplate('commerce/promotions/sales/_edit', $variables, TemplateMode::Cp);
    }

    public function save(Request $request): Response
    {
        $sale = new Sale();

        abort_unless(currentUserElement()?->can($sale->id === null ? 'commerce-createSales' : 'commerce-editSales'), 403);

        $sale->id = $request->input('id');
        $sale->name = $request->input('name');
        $sale->description = $request->input('description');
        $sale->apply = $request->input('apply');
        $sale->enabled = (bool)$request->input('enabled');

        foreach (['dateFrom', 'dateTo'] as $field) {
            if (($date = $request->input($field)) !== null) {
                $sale->$field = DateTimeHelper::toDateTime($date) ?: null;
            }
        }

        $applyAmount = Localization::normalizeNumber($request->input('applyAmount'));
        $sale->sortOrder = (int)$request->input('sortOrder');
        $sale->ignorePrevious = (bool)$request->input('ignorePrevious');
        $sale->stopProcessing = (bool)$request->input('stopProcessing');
        $sale->categoryRelationshipType = $request->input('categoryRelationshipType', $sale->categoryRelationshipType);

        if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
            if ((float)$applyAmount >= 1) {
                $sale->applyAmount = (float)$applyAmount / -100;
            } else {
                $sale->applyAmount = -(float)$applyAmount;
            }
        } else {
            $sale->applyAmount = (float)$applyAmount * -1;
        }

        $allPurchasables = !$request->input('allPurchasables', false);
        if ($sale->allPurchasables = $allPurchasables) {
            $sale->setPurchasableIds([]);
        } else {
            $purchasables = [];
            $purchasableGroups = $request->input('purchasables') ?: [];
            foreach ($purchasableGroups as $group) {
                if (is_array($group)) {
                    array_push($purchasables, ...$group);
                }
            }
            $sale->setPurchasableIds($purchasables);
        }

        $allCategories = !$request->input('allCategories', false);
        if ($sale->allCategories = $allCategories) {
            $sale->setCategoryIds([]);
        } else {
            $relatedElements = [];
            $relatedElementByType = $request->input('relatedElements') ?: [];
            foreach ($relatedElementByType as $type) {
                if (is_array($type)) {
                    array_push($relatedElements, ...$type);
                }
            }
            $sale->setCategoryIds(array_unique($relatedElements));
        }

        if ($sale->allGroups = (bool)$request->input('allGroups', true)) {
            $sale->setUserGroupIds([]);
        } else {
            $sale->setUserGroupIds($request->input('groups', []) ?: []);
        }

        if (Plugin::getInstance()->getSales()->saveSale($sale)) {
            return $this->asModelSuccess($sale, t('Sale saved.', category: 'commerce'), 'sale');
        }

        return $this->asModelFailure($sale, t('Couldn\'t save sale.', category: 'commerce'), 'sale');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));
        if (!Plugin::getInstance()->getSales()->reorderSales($ids)) {
            return $this->asFailure(t('Couldn\'t reorder sales.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-deleteSales'), 403);

        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            abort_unless($request->expectsJson(), 400);
            $ids = [$id];
        }

        foreach ($ids as $deleteId) {
            Plugin::getInstance()->getSales()->deleteSaleById($deleteId);
        }

        if ($request->expectsJson()) {
            return $this->asSuccess();
        }

        return $this->asSuccess(t('Sales deleted.', category: 'commerce'), redirect: url()->previous());
    }

    public function getAllSales(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $sales = Plugin::getInstance()->getSales()->getAllSales();

        return response()->json(array_values($sales));
    }

    public function getSalesByProductId(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $id = $request->input('id');

        if (!$id) {
            return $this->asFailure(t('Product ID is required.', category: 'commerce'));
        }

        $product = Plugin::getInstance()->getProducts()->getProductById($id);

        if (!$product) {
            return $this->asFailure(t('No product available.', category: 'commerce'));
        }

        $sales = [];
        foreach ($product->getVariants(true) as $variant) {
            $variantSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($variant);
            foreach ($variantSales as $sale) {
                if (!ArrayHelper::firstWhere($sales, 'id', $sale->id)) {
                    $saleArray = $sale->toArray();
                    $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                    $sales[] = $saleArray;
                }
            }
        }

        return $this->asSuccess(data: ['sales' => $sales]);
    }

    public function getSalesByPurchasableId(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $id = $request->input('id');

        if (!$id) {
            return $this->asFailure(t('Purchasable ID is required.', category: 'commerce'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asFailure(t('No purchasable available.', category: 'commerce'));
        }

        $sales = [];
        $purchasableSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($purchasable);
        foreach ($purchasableSales as $sale) {
            if (!ArrayHelper::firstWhere($sales, 'id', $sale->id)) {
                $saleArray = $sale->toArray();
                $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                $sales[] = $saleArray;
            }
        }

        return $this->asSuccess(data: ['sales' => $sales]);
    }

    public function addPurchasableToSale(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $ids = $request->input('ids', []);
        $saleId = $request->input('saleId');

        if (empty($ids) || !$saleId) {
            return $this->asFailure(t('Purchasable ID and Sale ID are required.', category: 'commerce'));
        }

        $purchasables = [];
        foreach ($ids as $id) {
            $purchasables[] = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);
        }

        $sale = Plugin::getInstance()->getSales()->getSaleById($saleId);

        if (empty($purchasables) || count($purchasables) != count($ids) || !$sale) {
            return $this->asFailure(t('Unable to retrieve Sale and Purchasable.', category: 'commerce'));
        }

        $salePurchasableIds = $sale->getPurchasableIds();

        array_push($salePurchasableIds, ...$ids);
        if (!empty($salePurchasableIds)) {
            $sale->allPurchasables = false;
        }
        $sale->setPurchasableIds(array_unique($salePurchasableIds));

        if (!Plugin::getInstance()->getSales()->saveSale($sale)) {
            return $this->asFailure(t('Couldn\'t save sale.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function updateStatus(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-editSales'), 403);

        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $sales = SaleRecord::whereIn('id', $ids)->get();

            foreach ($sales as $sale) {
                $sale->enabled = ($status == 'enabled');
                $sale->save();
            }
        });

        return $this->asSuccess(t('Sales updated.', category: 'commerce'));
    }

    private function populateVariables(?int $id, Sale $sale, string $storeHandle): array
    {
        $variables = [
            'id' => $id,
            'sale' => $sale,
            'storeHandle' => $storeHandle,
            'storeSwitcher' => $this->getStoreSwitcher($storeHandle),
        ];

        $variables['title'] = $sale->id ? $sale->name : t('Create a new sale', category: 'commerce');

        if (Edition::get() === Edition::Pro) {
            $groups = UserGroups::getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        $variables['percentSymbol'] = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $primaryCurrencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencySymbol'] = I18N::getLocale()->getCurrencySymbol($primaryCurrencyIso);

        $variables['saleApplyAmount'] = '';
        if ($sale->applyAmount !== null) {
            if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
                $amount = -(float)$sale->applyAmount * 100;
                $variables['saleApplyAmount'] = I18N::getFormatter()->asDecimal($amount);
            } else {
                $variables['saleApplyAmount'] = I18N::getFormatter()->asDecimal(-(float)$sale->applyAmount);
            }
        }

        $variables['categoryElementType'] = Category::class;
        $variables['entryElementType'] = Entry::class;

        $categories = [];
        $entries = [];

        $request = request();
        if (empty($id) && $request->input('categoryIds')) {
            $categoryIds = explode('|', (string)$request->input('categoryIds'));
        } else {
            $categoryIds = $sale->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $elementId = (int)$categoryId;
            $element = Elements::getElementById($elementId);

            if ($element instanceof Category) {
                $categories[] = $element;
            } elseif ($element instanceof Entry) {
                $entries[] = $element;
            }
        }

        $variables['categories'] = $categories;
        $variables['entries'] = $entries;

        $variables['elementRelationshipTypeOptions'] = [
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => t('The purchasable defines the relationship', category: 'commerce'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => t('The purchasable is related by another element', category: 'commerce'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => t('Either way', category: 'commerce'),
        ];

        $purchasables = [];

        if (empty($id) && $request->input('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', (string)$request->input('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Elements::getElementById((int)$purchasableId);
                if ($purchasable instanceof Product) {
                    foreach ($purchasable->getVariants(true) as $variant) {
                        $purchasableIds[] = $variant->getId();
                    }
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
            $sale->allPurchasables = false;
        } else {
            $purchasableIds = $sale->getPurchasableIds();
        }

        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Elements::getElementById((int)$purchasableId);
            if ($purchasable instanceof PurchasableInterface) {
                $class = $purchasable::class;
                $purchasables[$class] ??= [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;

        $variables['purchasableTypes'] = [];
        $purchasableTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType,
            ];
        }

        return $variables;
    }
}
