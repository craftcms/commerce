<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\transfer;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Transfer;
use craft\elements\User;
use craftcommercetests\fixtures\CustomerFixture;
use UnitTester;

/**
 * TransferTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.8
 */
class TransferTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
        ];
    }

    public function testManageInventoryTransfersPermission(): void
    {
        /** @var User $user */
        $user = $this->tester->grabFixture('customer')->getElement('customer2');
        $transfer = new Transfer();

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, []);
        $user = Craft::$app->getUsers()->getUserById($user->id);
        self::assertFalse($transfer->canView($user));
        self::assertFalse($transfer->canSave($user));
        self::assertFalse($transfer->canDelete($user));

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['commerce-manageInventoryTransfers']);
        $user = Craft::$app->getUsers()->getUserById($user->id);
        self::assertTrue($transfer->canView($user));
        self::assertTrue($transfer->canSave($user));
        self::assertTrue($transfer->canDelete($user));
    }
}
