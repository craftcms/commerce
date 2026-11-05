<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\models\Checkout;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Checkout as CheckoutRecord;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\db\Exception as DbException;

/**
 * Checkouts service.
 *
 * Manages the configurations of the hosted checkout, which are kept in project config and mirrored to the database.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Checkouts extends Component
{
    public const CONFIG_CHECKOUTS_KEY = 'commerce.checkouts';

    /**
     * @var Collection<Checkout>|null
     */
    private ?Collection $_allCheckouts = null;

    /**
     * Returns all checkouts, in their sort order.
     *
     * @return Collection<Checkout>
     */
    public function getAllCheckouts(): Collection
    {
        if ($this->_allCheckouts !== null) {
            return $this->_allCheckouts;
        }

        $stores = (new Query())
            ->select(['checkoutId', 'storeId'])
            ->from(Table::CHECKOUTS_STORES)
            ->all();
        $gateways = (new Query())
            ->select(['checkoutId', 'gatewayId'])
            ->from(Table::CHECKOUTS_GATEWAYS)
            ->all();

        $checkouts = collect((new Query())
            ->select([
                'id', 'name', 'handle', 'enabled', 'isDefault', 'sortOrder', 'brandColor', 'colorScheme', 'cornerStyle',
                'logoUrl', 'returnUrl', 'continueShoppingUrl', 'allowPartialPayment', 'partialPaymentPercentages',
                'requireBillingAddress', 'orderCondition', 'orderConditionMessage', 'dateCreated', 'dateUpdated', 'uid',
            ])
            ->from(Table::CHECKOUTS)
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all())
            ->map(function(array $row) use ($stores, $gateways) {
                $checkout = new Checkout();
                $checkout->id = (int)$row['id'];
                $checkout->name = $row['name'];
                $checkout->handle = $row['handle'];
                $checkout->enabled = (bool)$row['enabled'];
                $checkout->isDefault = (bool)$row['isDefault'];
                $checkout->sortOrder = $row['sortOrder'] !== null ? (int)$row['sortOrder'] : null;
                $checkout->brandColor = $row['brandColor'];
                $checkout->colorScheme = $row['colorScheme'];
                $checkout->cornerStyle = $row['cornerStyle'];
                $checkout->logoUrl = $row['logoUrl'];
                $checkout->returnUrl = $row['returnUrl'];
                $checkout->continueShoppingUrl = $row['continueShoppingUrl'];
                $checkout->allowPartialPayment = (bool)$row['allowPartialPayment'];
                $checkout->setPartialPaymentPercentages($row['partialPaymentPercentages']);
                $checkout->requireBillingAddress = $row['requireBillingAddress'] !== null ? (bool)$row['requireBillingAddress'] : null;
                $checkout->orderConditionMessage = $row['orderConditionMessage'];
                $checkout->dateCreated = DateTimeHelper::toDateTime($row['dateCreated']) ?: null;
                $checkout->dateUpdated = DateTimeHelper::toDateTime($row['dateUpdated']) ?: null;
                $checkout->uid = $row['uid'];
                $checkout->setOrderCondition($row['orderCondition']);
                $checkout->setStoreIds(array_column(array_filter($stores, fn($r) => (int)$r['checkoutId'] === $checkout->id), 'storeId'));
                $checkout->setGatewayIds(array_column(array_filter($gateways, fn($r) => (int)$r['checkoutId'] === $checkout->id), 'gatewayId'));

                return $checkout;
            });

        return $this->_allCheckouts = $checkouts->values();
    }

    /**
     * Returns the enabled checkouts that work with a store.
     *
     * @return Collection<Checkout>
     */
    public function getCheckoutsForStore(Store|int $store): Collection
    {
        return $this->getAllCheckouts()
            ->filter(fn(Checkout $checkout) => $checkout->enabled && $checkout->allowsStore($store))
            ->values();
    }

    public function getCheckoutById(int $id): ?Checkout
    {
        return $this->getAllCheckouts()->firstWhere('id', $id);
    }

    public function getCheckoutByHandle(string $handle): ?Checkout
    {
        return $this->getAllCheckouts()->firstWhere('handle', $handle);
    }

    public function getCheckoutByUid(string $uid): ?Checkout
    {
        return $this->getAllCheckouts()->firstWhere('uid', $uid);
    }

    /**
     * Returns the checkout to use for a store when none is asked for: the default one, or else the first enabled.
     */
    public function getDefaultCheckout(Store|int $store): ?Checkout
    {
        $checkouts = $this->getCheckoutsForStore($store);

        return $checkouts->firstWhere('isDefault', true) ?? $checkouts->first();
    }

    /**
     * Returns the checkout to use for a store, or a stand-in with the standard options if none are set up, so the
     * hosted checkout works before anyone has configured it.
     */
    public function getCheckoutForStore(Store|int $store, ?string $handle = null): Checkout
    {
        if ($handle) {
            $checkout = $this->getCheckoutByHandle($handle);

            if ($checkout && $checkout->enabled && $checkout->allowsStore($store)) {
                return $checkout;
            }
        }

        if ($checkout = $this->getDefaultCheckout($store)) {
            return $checkout;
        }

        $fallback = new Checkout(['name' => 'Default', 'handle' => 'default', 'isDefault' => true]);
        $fallback->setStoreIds([$store instanceof Store ? $store->id : $store]);

        return $fallback;
    }

    /**
     * Saves a checkout to project config. The database is updated once the config change is applied.
     */
    public function saveCheckout(Checkout $checkout, bool $runValidation = true): bool
    {
        $isNew = !$checkout->id;

        if ($runValidation && !$checkout->validate()) {
            Craft::info('Checkout not saved due to validation error(s).', __METHOD__);
            return false;
        }

        if ($isNew) {
            $checkout->uid = StringHelper::UUID();
            $checkout->sortOrder ??= ((int)(new Query())->from(Table::CHECKOUTS)->max('sortOrder')) + 1;
        }

        // The first checkout is the default
        if ($isNew && $this->getAllCheckouts()->isEmpty()) {
            $checkout->isDefault = true;
        }

        Craft::$app->getProjectConfig()->set(self::CONFIG_CHECKOUTS_KEY . '.' . $checkout->uid, $checkout->getConfig(), "Save the “{$checkout->handle}” checkout");

        if ($isNew) {
            $checkout->id = Db::idByUid(Table::CHECKOUTS, $checkout->uid);
        }

        $this->_allCheckouts = null;

        return true;
    }

    /**
     * Applies a checkout’s project config to the database.
     */
    public function handleChangedCheckout(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        // The checkout refers to stores and gateways, which need to be in the database first
        ProjectConfigData::ensureAllStoresProcessed();
        $projectConfig = Craft::$app->getProjectConfig();
        if ($projectConfig->getIsApplyingExternalChanges()) {
            foreach ($data['gateways'] ?? [] as $gatewayUid) {
                $projectConfig->processConfigChanges(Gateways::CONFIG_GATEWAY_KEY . '.' . $gatewayUid);
            }
        }

        $storeIds = array_values(array_filter(array_map(
            fn(string $storeUid) => Plugin::getInstance()->getStores()->getStoreByUid($storeUid)?->id,
            $data['stores'] ?? [],
        )));
        $gatewayIds = array_values(array_filter(array_map(
            fn(string $gatewayUid) => Db::idByUid(Table::GATEWAYS, $gatewayUid),
            $data['gateways'] ?? [],
        )));

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $record = CheckoutRecord::findOne(['uid' => $uid]) ?? new CheckoutRecord();

            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->enabled = (bool)($data['enabled'] ?? true);
            $record->isDefault = (bool)($data['isDefault'] ?? false);
            $record->sortOrder = $data['sortOrder'] ?? null;
            $record->brandColor = $data['brandColor'] ?? null;
            $record->colorScheme = $data['colorScheme'] ?? Checkout::COLOR_SCHEME_LIGHT;
            $record->cornerStyle = $data['cornerStyle'] ?? Checkout::CORNER_STYLE_ROUNDED;
            $record->logoUrl = $data['logoUrl'] ?? null;
            $record->returnUrl = $data['returnUrl'] ?? null;
            $record->continueShoppingUrl = $data['continueShoppingUrl'] ?? null;
            $record->allowPartialPayment = (bool)($data['allowPartialPayment'] ?? false);
            $record->partialPaymentPercentages = Json::encode($data['partialPaymentPercentages'] ?? []);
            $record->requireBillingAddress = $data['requireBillingAddress'] ?? null;
            $record->orderCondition = isset($data['orderCondition']) ? Json::encode($data['orderCondition']) : null;
            $record->orderConditionMessage = $data['orderConditionMessage'] ?? null;
            $record->uid = $uid;
            $record->save(false);

            Db::delete(Table::CHECKOUTS_STORES, ['checkoutId' => $record->id]);
            foreach ($storeIds as $storeId) {
                Db::insert(Table::CHECKOUTS_STORES, ['checkoutId' => $record->id, 'storeId' => $storeId]);
            }

            Db::delete(Table::CHECKOUTS_GATEWAYS, ['checkoutId' => $record->id]);
            foreach ($gatewayIds as $gatewayId) {
                Db::insert(Table::CHECKOUTS_GATEWAYS, ['checkoutId' => $record->id, 'gatewayId' => $gatewayId]);
            }

            // Only one checkout is the default
            if ($record->isDefault) {
                CheckoutRecord::updateAll(['isDefault' => false], ['not', ['id' => $record->id]]);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_allCheckouts = null;
    }

    /**
     * Deletes a checkout, via project config.
     */
    public function deleteCheckoutById(int $id): bool
    {
        $checkout = $this->getCheckoutById($id);

        if (!$checkout) {
            return false;
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_CHECKOUTS_KEY . '.' . $checkout->uid, "Delete the “{$checkout->handle}” checkout");

        $this->_allCheckouts = null;

        return true;
    }

    /**
     * Removes a checkout from the database after it’s been removed from project config.
     */
    public function handleDeletedCheckout(ConfigEvent $event): void
    {
        $record = CheckoutRecord::findOne(['uid' => $event->tokenMatches[0]]);

        if (!$record) {
            return;
        }

        try {
            $record->delete();
        } catch (DbException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw $e;
        }

        $this->_allCheckouts = null;
    }
}
