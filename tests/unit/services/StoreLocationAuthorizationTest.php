<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin;
use craft\commerce\records\StoreSettings as StoreSettingsRecord;
use craft\elements\Address;
use craft\elements\User;
use craft\helpers\StringHelper;
use UnitTester;

/**
 * StoreLocationAuthorizationTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.7
 */
class StoreLocationAuthorizationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    private int $storeId;
    private ?int $originalLocationAddressId = null;

    /**
     * @var User[]
     */
    private array $users = [];

    protected function _before(): void
    {
        parent::_before();

        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        // Creates the store settings record if it doesn’t exist yet
        Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($storeId);
        $storeSettings = StoreSettingsRecord::findOne($storeId);
        $this->storeId = $storeSettings->id;
        $this->originalLocationAddressId = $storeSettings->locationAddressId;

        // A store without a location address
        StoreSettingsRecord::updateAll(['locationAddressId' => null], ['id' => $this->storeId]);
    }

    protected function _after(): void
    {
        StoreSettingsRecord::updateAll(['locationAddressId' => $this->originalLocationAddressId], ['id' => $this->storeId]);

        foreach ($this->users as $user) {
            Craft::$app->getElements()->deleteElement($user, true);
        }

        parent::_after();
    }

    /**
     * Unsaved addresses must not be treated as the location address of a store that doesn’t have one.
     */
    public function testUnsavedAddressForAnotherUserIsNotAuthorized(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $address = new Address(['ownerId' => $otherUser->id]);
        $elementsService = Craft::$app->getElements();

        self::assertFalse($elementsService->canSave($address, $user));
        self::assertFalse($elementsService->canView($address, $user));

        // The owner is still allowed
        self::assertTrue($elementsService->canSave($address, $otherUser));
    }

    private function createUser(): User
    {
        $user = new User([
            'username' => 'store-location-' . StringHelper::randomString(6),
            'email' => 'store-location-' . StringHelper::randomString(6) . '@example.com',
        ]);
        Craft::$app->getElements()->saveElement($user, false);
        $this->users[] = $user;

        return $user;
    }
}
