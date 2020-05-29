<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\models\Discount;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\elements\Category;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\i18n\Locale;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use function explode;
use function get_class;

/**
 * Class Discounts Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DiscountsController extends BaseCpController
{
    const DISCOUNT_COUNTER_TYPE_TOTAL = 'total';
    const DISCOUNT_COUNTER_TYPE_EMAIL = 'email';
    const DISCOUNT_COUNTER_TYPE_CUSTOMER = 'customer';


    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();
        return $this->renderTemplate('commerce/promotions/discounts/index', compact('discounts'));
    }

    /**
     * @param int|null $id
     * @param Discount|null $discount
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Discount $discount = null): Response
    {
        $variables = compact('id', 'discount');

        if (!$variables['discount']) {
            if ($variables['id']) {
                $variables['discount'] = Plugin::getInstance()->getDiscounts()->getDiscountById($variables['id']);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = new Discount();
            }
        }

        $this->_populateVariables($variables);

        return $this->renderTemplate('commerce/promotions/discounts/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $discount = new Discount();
        $request = Craft::$app->getRequest();

        $discount->id = $request->getBodyParam('id');
        $discount->name = $request->getBodyParam('name');
        $discount->description = $request->getBodyParam('description');
        $discount->enabled = (bool)$request->getBodyParam('enabled');
        $discount->stopProcessing = (bool)$request->getBodyParam('stopProcessing');
        $discount->purchaseQty = $request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $request->getBodyParam('maxPurchaseQty');
        $discount->percentDiscount = $request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $request->getBodyParam('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool)$request->getBodyParam('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool)$request->getBodyParam('hasFreeShippingForOrder');
        $discount->excludeOnSale = (bool)$request->getBodyParam('excludeOnSale');
        $discount->code = trim($request->getBodyParam('code')) ?: null;
        $discount->perUserLimit = $request->getBodyParam('perUserLimit');
        $discount->perEmailLimit = $request->getBodyParam('perEmailLimit');
        $discount->totalDiscountUseLimit = $request->getBodyParam('totalDiscountUseLimit');
        $discount->ignoreSales = (bool)$request->getBodyParam('ignoreSales');
        $discount->categoryRelationshipType = $request->getBodyParam('categoryRelationshipType');
        $discount->baseDiscountType = $request->getBodyParam('baseDiscountType') ?: DiscountRecord::BASE_DISCOUNT_TYPE_VALUE;
        $discount->appliedTo = $request->getBodyParam('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = $request->getBodyParam('orderConditionFormula');

        $baseDiscount = $request->getBodyParam('baseDiscount') ?: 0;
        $baseDiscount = Localization::normalizeNumber($baseDiscount);
        $discount->baseDiscount = $baseDiscount * -1;

        $perItemDiscount = $request->getBodyParam('perItemDiscount') ?: 0;
        $perItemDiscount = Localization::normalizeNumber($perItemDiscount);
        $discount->perItemDiscount = $perItemDiscount * -1;

        $discount->purchaseTotal = Localization::normalizeNumber($request->getBodyParam('purchaseTotal'));

        $date = $request->getBodyParam('dateFrom');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        // Format into a %
        $percentDiscountAmount = $request->getBodyParam('percentDiscount');
        $localeData = Craft::$app->getLocale();
        $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $percentDiscountAmount = Localization::normalizeNumber($percentDiscountAmount);
        if (strpos($percentDiscountAmount, $percentSign) || (float)$percentDiscountAmount >= 1) {
            $discount->percentDiscount = (float)$percentDiscountAmount / -100;
        } else {
            $discount->percentDiscount = (float)$percentDiscountAmount * -1;
        }

        $purchasables = [];
        $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
        foreach ($purchasableGroups as $group) {
            if (is_array($group)) {
                array_push($purchasables, ...$group);
            }
        }
        $purchasables = array_unique($purchasables);
        $discount->setPurchasableIds($purchasables);

        $categories = $request->getBodyParam('categories', []);
        if (!$categories) {
            $categories = [];
        }
        $discount->setCategoryIds($categories);

        $groups = $request->getBodyParam('groups', []);
        if (!$groups) {
            $groups = [];
        }
        $discount->setUserGroupIds($groups);

        // Save it
        if (Plugin::getInstance()->getDiscounts()->saveDiscount($discount)
        ) {
            Craft::$app->getSession()->setNotice(Plugin::t('Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save discount.'));
        }

        // Send the model back to the template
        $variables = [
            'discount' => $discount
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     *
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getDiscounts()->reorderDiscounts($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Plugin::t('Couldn’t reorder discounts.')]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getDiscounts()->deleteDiscountById($id);

        return $this->asJson(['success' => true]);
    }

    /**
     * @return Response
     * @throws \yii\db\Exception
     * @throws \yii\web\BadRequestHttpException
     * @since 3.0
     */
    public function actionClearDiscountUses(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $type = Craft::$app->getRequest()->getBodyParam('type', 'total');
        $types = [self::DISCOUNT_COUNTER_TYPE_TOTAL, self::DISCOUNT_COUNTER_TYPE_CUSTOMER, self::DISCOUNT_COUNTER_TYPE_EMAIL];

        if (!in_array($type, $types, true)) {
            return $this->asErrorJson(Plugin::t('Type not in allowed options.'));
        }

        switch ($type) {
            case self::DISCOUNT_COUNTER_TYPE_CUSTOMER:
                Plugin::getInstance()->getDiscounts()->clearCustomerUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_EMAIL:
                Plugin::getInstance()->getDiscounts()->clearEmailUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_TOTAL:
                Plugin::getInstance()->getDiscounts()->clearDiscountUsesById($id);
                break;
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.0
     */
    public function actionUpdateStatus()
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t updated discounts status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $discounts = DiscountRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var DiscountRecord $discount */
        foreach ($discounts as $discount) {
            $discount->enabled = ($status == 'enabled');
            $discount->save();
        }
        $transaction->commit();

        Craft::$app->getSession()->setNotice(Plugin::t('Discounts updated.'));
    }


    /**
     * @param array $variables
     */
    private function _populateVariables(&$variables)
    {
        if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Plugin::t('Create a Discount');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        $localizedNumberAttributes = ['baseDiscount', 'perItemDiscount', 'purchaseTotal'];
        $flipNegativeNumberAttributes = ['baseDiscount', 'perItemDiscount'];
        foreach ($localizedNumberAttributes as $attr) {
            if (!isset($variables['discount']->{$attr})) {
                continue;
            }

            if ($variables['discount']->{$attr} != 0) {
                $number = (float)$variables['discount']->{$attr};
                if (in_array($attr, $flipNegativeNumberAttributes)) {
                    $number *= -1;
                }

                $variables['discount']->{$attr} = Craft::$app->formatter->asDecimal($number);
            } else {
                $variables['discount']->{$attr} = 0;
            }
        }

        $variables['counterTypeTotal'] = self::DISCOUNT_COUNTER_TYPE_TOTAL;
        $variables['counterTypeCustomer'] = self::DISCOUNT_COUNTER_TYPE_CUSTOMER;
        $variables['counterTypeEmail'] = self::DISCOUNT_COUNTER_TYPE_EMAIL;

        if ($variables['discount']->id) {
            $variables['emailUsage'] = Plugin::getInstance()->getDiscounts()->getEmailUsageStatsById($variables['discount']->id);
            $variables['customerUsage'] = Plugin::getInstance()->getDiscounts()->getCustomerUsageStatsById($variables['discount']->id);
        } else {
            $variables['emailUsage'] = 0;
            $variables['customerUsage'] = 0;
        }

        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
        $currencyName = $currency ? $currency->getCurrency() : '';

        $variables['baseDiscountTypes'] = [
            DiscountRecord::BASE_DISCOUNT_TYPE_VALUE => Plugin::t($currencyName . ' value'),
        ];

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL] = Plugin::t('(%) off total original price and shipping total (Deprecated)');
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED] = Plugin::t('(%) off total discounted price and shipping total (Deprecated)');
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS] = Plugin::t('(%) off total original price (Deprecated)');
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED] = Plugin::t('(%) off total discounted price (Deprecated)');
        }

        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = $categoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

        $variables['categories'] = $categories;

        $variables['categoryRelationshipType'] = [
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Plugin::t('Source'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Plugin::t('Target'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Plugin::t('Both'),
        ];

        $variables['appliedTo'] = [
            DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS => Plugin::t('Discount the matching items only'),
            DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS => Plugin::t('Discount all line items')
        ];

        $variables['purchasables'] = null;

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId; // this would only be null if we are duplicating a variant, otherwise should never be null
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

        $purchasableIds = array_filter($purchasableIds);

        $purchasables = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
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
                'elementType' => $purchasableType
            ];
        }
    }
}
