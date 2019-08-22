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
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\i18n\Locale;
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
    // Public Methods
    // =========================================================================

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
        $discount->totalUseLimit = $request->getBodyParam('totalUseLimit');

        $baseDiscount = Localization::normalizeNumber($request->getBodyParam('baseDiscount'));
        $discount->baseDiscount = $baseDiscount * -1;

        $perItemDiscount = Localization::normalizeNumber($request->getBodyParam('perItemDiscount'));
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
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save discount.'));
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

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder discounts.')]);
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
     * @throws HttpException
     */
    public function actionClearCouponUsageHistory()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getDiscounts()->clearCouponUsageHistoryById($id);

        return $this->asJson(['success' => true]);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     */
    private function _populateVariables(&$variables)
    {
        if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Discount');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        if ($variables['discount']->baseDiscount != 0) {
            $variables['discount']->baseDiscount = Craft::$app->formatter->asDecimal((float)$variables['discount']->baseDiscount * -1);
        }

        if ($variables['discount']->perItemDiscount != 0) {
            $variables['discount']->perItemDiscount = Craft::$app->formatter->asDecimal((float)$variables['discount']->perItemDiscount * -1);
        }

        if ($variables['discount']->purchaseTotal != 0) {
            $variables['discount']->purchaseTotal = Craft::$app->formatter->asDecimal((float)$variables['discount']->purchaseTotal);
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

        $variables['purchasables'] = null;


        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId;
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

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
