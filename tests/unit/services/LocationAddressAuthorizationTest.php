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
use craft\commerce\records\InventoryLocation as InventoryLocationRecord;
use craft\commerce\records\StoreSettings as StoreSettingsRecord;
use craft\elements\Address;
use craft\elements\User;
use craft\helpers\StringHelper;
use ReflectionProperty;
use UnitTester;

/**
 * LocationAddressAuthorizationTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.9
 */
class LocationAddressAuthorizationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    private int $storeId;
    private ?int $originalLocationAddressId = null;

    /**
     * @var array<int, int|null> Original address IDs, indexed by inventory location ID
     */
    private array $originalInventoryAddressIds = [];

    /**
     * @var User[]
     */
    private array $users = [];

    /**
     * @var Address[]
     */
    private array $addresses = [];

    protected function _before(): void
    {
        parent::_before();

        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        // Creates the store settings record if it doesn’t exist yet
        Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($storeId);
        $storeSettings = StoreSettingsRecord::findOne($storeId);
        self::assertNotNull($storeSettings, 'Store settings record should exist');
        $this->storeId = $storeId;
        $this->originalLocationAddressId = $storeSettings->locationAddressId;

        foreach (InventoryLocationRecord::find()->all() as $location) {
            $this->originalInventoryAddressIds[$location->id] = $location->addressId;
        }

        // A store and inventory locations without an address
        StoreSettingsRecord::updateAll(['locationAddressId' => null], ['id' => $this->storeId]);
        InventoryLocationRecord::updateAll(['addressId' => null]);
        $this->clearInventoryLocationCache();
    }

    protected function _after(): void
    {
        StoreSettingsRecord::updateAll(['locationAddressId' => $this->originalLocationAddressId], ['id' => $this->storeId]);
        foreach ($this->originalInventoryAddressIds as $id => $addressId) {
            InventoryLocationRecord::updateAll(['addressId' => $addressId], ['id' => $id]);
        }
        $this->clearInventoryLocationCache();

        foreach ($this->addresses as $address) {
            Craft::$app->getElements()->deleteElement($address, true);
        }

        foreach ($this->users as $user) {
            Craft::$app->getElements()->deleteElement($user, true);
        }

        parent::_after();
    }

    /**
     * Unsaved addresses must not be treated as the location address of a store or inventory location that doesn’t have one.
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

    /**
     * Unsaved addresses stay denied even for users who can manage the store and inventory locations.
     */
    public function testUnsavedAddressForAnotherUserIsNotAuthorizedWithPermissions(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $address = new Address(['ownerId' => $otherUser->id]);
        $this->mockPermissions(['commerce-manageGeneralStoreSettings', 'commerce-manageInventoryLocations']);

        self::assertFalse(Craft::$app->getElements()->canSave($address, $user));
    }

    public function testStoreLocationAddressRequiresPermission(): void
    {
        $user = $this->createUser();
        $address = $this->createAddress($this->createUser());
        StoreSettingsRecord::updateAll(['locationAddressId' => $address->id], ['id' => $this->storeId]);
        $elementsService = Craft::$app->getElements();

        $this->mockPermissions([]);
        self::assertFalse($elementsService->canView($address, $user));
        self::assertFalse($elementsService->canSave($address, $user));

        $this->mockPermissions(['commerce-manageInventoryLocations']);
        self::assertFalse($elementsService->canSave($address, $user));

        $this->mockPermissions(['commerce-manageGeneralStoreSettings']);
        self::assertTrue($elementsService->canView($address, $user));
        self::assertTrue($elementsService->canSave($address, $user));
    }

    public function testInventoryLocationAddressRequiresPermission(): void
    {
        $user = $this->createUser();
        $address = $this->createAddress($this->createUser());
        $location = InventoryLocationRecord::find()->one();
        self::assertNotNull($location, 'An inventory location should exist');
        InventoryLocationRecord::updateAll(['addressId' => $address->id], ['id' => $location->id]);
        $this->clearInventoryLocationCache();
        $elementsService = Craft::$app->getElements();

        $this->mockPermissions([]);
        self::assertFalse($elementsService->canView($address, $user));
        self::assertFalse($elementsService->canSave($address, $user));

        $this->mockPermissions(['commerce-manageGeneralStoreSettings']);
        self::assertFalse($elementsService->canSave($address, $user));

        $this->mockPermissions(['commerce-manageInventoryLocations']);
        self::assertTrue($elementsService->canView($address, $user));
        self::assertTrue($elementsService->canSave($address, $user));
    }

    public function testTrashedInventoryLocationAddressIsNotAuthorized(): void
    {
        $user = $this->createUser();
        $address = $this->createAddress($this->createUser());
        $location = InventoryLocationRecord::find()->one();
        self::assertNotNull($location, 'An inventory location should exist');
        InventoryLocationRecord::updateAll(['addressId' => $address->id, 'dateDeleted' => '2020-01-01 00:00:00'], ['id' => $location->id]);
        $this->clearInventoryLocationCache();

        $this->mockPermissions(['commerce-manageInventoryLocations']);
        self::assertFalse(Craft::$app->getElements()->canSave($address, $user));

        InventoryLocationRecord::updateAll(['dateDeleted' => null], ['id' => $location->id]);
        $this->clearInventoryLocationCache();
    }

    private function mockPermissions(array $permissions): void
    {
        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                // Craft compares against lowercased permission names
                'getPermissionsByUserId' => fn() => array_map('strtolower', $permissions),
            ],
            []
        );
    }

    /**
     * Clears the inventory locations service’s memoized locations.
     */
    private function clearInventoryLocationCache(): void
    {
        $service = Plugin::getInstance()->getInventoryLocations();
        foreach (['_allLocations', '_allLocationsWithTrashed'] as $property) {
            $reflection = new ReflectionProperty($service, $property);
            $reflection->setValue($service, null);
        }
    }

    private function createAddress(User $owner): Address
    {
        $address = new Address([
            'ownerId' => $owner->id,
            'countryCode' => 'US',
            'addressLine1' => '1 Test Street',
        ]);
        self::assertTrue(Craft::$app->getElements()->saveElement($address, false));
        $this->addresses[] = $address;

        return $address;
    }

    private function createUser(): User
    {
        $user = new User([
            'username' => 'location-address-' . StringHelper::randomString(6),
            'email' => 'location-address-' . StringHelper::randomString(6) . '@example.com',
        ]);
        Craft::$app->getElements()->saveElement($user, false);
        $this->users[] = $user;

        return $user;
    }
}
