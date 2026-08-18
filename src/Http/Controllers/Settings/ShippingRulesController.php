<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\ShippingAddressZone;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\helpers\Json;
use craft\helpers\Localization;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Facades\Template;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Shipping\Records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class ShippingRulesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function edit(?string $storeHandle = null, ?int $methodId = null, ?int $ruleId = null): string
    {
        $store = $this->resolveStore($storeHandle);

        $plugin = Plugin::getInstance();
        $shippingMethod = $plugin->getShippingMethods()->getShippingMethodById($methodId, $store->id);
        abort_if($shippingMethod === null, 404);

        if ($ruleId) {
            $shippingRule = $plugin->getShippingRules()->getShippingRuleById($ruleId);
            abort_if($shippingRule === null, 404);
        } else {
            $shippingRule = new ShippingRule();
            $shippingRule->methodId = $shippingMethod->id;
            $shippingRule->storeId = $shippingMethod->storeId;
        }

        InputNamespace::set('new');
        HtmlStack::startJsBuffer();

        $newZone = new ShippingAddressZone();
        $condition = $newZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';

        $newShippingZoneFields = InputNamespace::namespaceInputs(
            Template::renderTemplate('commerce/store-management/shipping/shippingzones/_fields', ['condition' => $condition])
        );
        $newShippingZoneJs = HtmlStack::clearJsBuffer(false);
        InputNamespace::set(null);

        $title = $ruleId ? $shippingRule->name : t('Create a new shipping rule', category: 'commerce');

        $shippingZones = $plugin->getShippingZones()->getAllShippingZones($store->id)->all();
        $shippingZoneOptions = [];
        $shippingZoneOptions[] = t('Anywhere', category: 'commerce');
        foreach ($shippingZones as $model) {
            $shippingZoneOptions[$model->id] = $model->name;
        }

        $categoryShippingOptions = [
            ['label' => t('Allow', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_ALLOW],
            ['label' => t('Disallow', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_DISALLOW],
            ['label' => t('Require', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_REQUIRE],
        ];

        return pageTemplate('commerce/store-management/shipping/shippingrules/_edit', [
            'methodId' => $methodId,
            'ruleId' => $ruleId,
            'shippingRule' => $shippingRule,
            'shippingMethod' => $shippingMethod,
            'newShippingZoneFields' => $newShippingZoneFields,
            'newShippingZoneJs' => $newShippingZoneJs,
            'title' => $title,
            'shippingZones' => $shippingZoneOptions,
            'categoryShippingOptions' => $categoryShippingOptions,
            'storeId' => $store->id,
            'storeHandle' => $store->handle,
            'storeSwitcher' => $this->getStoreSwitcher($store->handle),
        ], TemplateMode::Cp);
    }

    public function duplicate(Request $request): Response
    {
        return $this->save($request, duplicate: true);
    }

    public function save(Request $request, bool $duplicate = false): Response
    {
        $shippingRule = new ShippingRule();

        if (!$duplicate) {
            $shippingRule->id = $request->input('id');
        }
        $shippingRule->storeId = $request->input('storeId');

        $moneyInputs = [
            'baseRate',
            'maxRate',
            'minRate',
            'perItemRate',
            'weightRate',
        ];

        foreach ($moneyInputs as $moneyInput) {
            $input = $request->input($moneyInput);
            $input += [
                'currency' => $shippingRule->getStore()->getCurrency(),
            ];
            $shippingRule->$moneyInput = (float)Money::toDecimal(Money::toMoney($input));
        }

        $shippingRule->name = $request->input('name');
        $shippingRule->description = $request->input('description');
        $shippingRule->methodId = $request->input('methodId');
        $shippingRule->enabled = (bool)$request->input('enabled');
        $shippingRule->orderConditionFormula = trim((string)$request->input('orderConditionFormula', ''));
        $shippingRule->percentageRate = Localization::normalizeNumber($request->input('percentageRate'));
        $shippingRule->setOrderCondition($request->input('orderCondition'));
        $shippingRule->setCustomerCondition($request->input('customerCondition'));

        $ruleCategories = [];
        $allRulesCategories = $request->input('ruleCategories');
        foreach ($allRulesCategories as $key => $ruleCategory) {
            $perItemRate = $ruleCategory['perItemRate'];
            $weightRate = $ruleCategory['weightRate'];
            $percentageRate = $ruleCategory['percentageRate'];
            $ruleCategory['perItemRate'] = (!isset($perItemRate) || trim((string)$perItemRate['value']) === '')
                ? null
                : Money::toDecimal(Money::toMoney(array_merge([
                    'currency' => $shippingRule->getStore()->getCurrency(),
                ], $perItemRate)));
            $ruleCategory['weightRate'] = (!isset($weightRate) || trim((string)$weightRate['value']) === '')
                ? null
                : Money::toDecimal(Money::toMoney(array_merge([
                    'currency' => $shippingRule->getStore()->getCurrency(),
                ], $weightRate)));
            $ruleCategory['percentageRate'] = (!isset($percentageRate) || trim((string)$percentageRate) === '') ? null : Localization::normalizeNumber($percentageRate);

            $ruleCategories[$key] = new ShippingRuleCategory($ruleCategory);
            $ruleCategories[$key]->shippingCategoryId = $key;
        }

        $shippingRule->setShippingRuleCategories($ruleCategories);

        if (!Plugin::getInstance()->getShippingRules()->saveShippingRule($shippingRule)) {
            return $this->asModelFailure($shippingRule, t('Couldn\'t save shipping rule.', category: 'commerce'), 'shippingRule');
        }

        return $this->asModelSuccess($shippingRule, t('Shipping rule saved.', category: 'commerce'), 'shippingRule');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));
        Plugin::getInstance()->getShippingRules()->reorderShippingRules($ids);

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        if ($request->ajax()) {
            abort_unless($request->expectsJson(), 400);
        }

        $id = $request->input('id');
        abort_if(!$id, 400, 'Shipping rule ID not submitted');

        $rule = Plugin::getInstance()->getShippingRules()->getShippingRuleById($id);
        abort_if($rule === null, 400, 'Cannot find shipping rule to delete');

        if (!Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id)) {
            return $this->asFailure(t('Could not delete shipping rule', category: 'commerce'));
        }

        if ($request->ajax()) {
            return $this->asSuccess();
        }

        return $this->redirectToPostedUrl($rule);
    }
}
