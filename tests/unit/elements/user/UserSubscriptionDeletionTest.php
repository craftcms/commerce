<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\user;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Subscription;
use craft\elements\User;
use craftcommercetests\fixtures\SubscriptionPlansFixture;
use UnitTester;

/**
 * UserSubscriptionDeletionTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class UserSubscriptionDeletionTest extends Unit
{
    protected UnitTester $tester;

    private ?User $_user = null;
    private ?int $_subscriptionId = null;

    public function _fixtures(): array
    {
        return [
            'plans' => ['class' => SubscriptionPlansFixture::class],
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        $user = new User();
        $user->username = 'subscription-cascade-test-' . uniqid();
        $user->email = 'subscription-cascade-' . uniqid() . '@crafttest.com';
        Craft::$app->getElements()->saveElement($user, false);
        $this->_user = $user;

        $plan = $this->tester->grabFixture('plans')->getModel('monthly');

        $subscription = new Subscription();
        $subscription->userId = $user->id;
        $subscription->planId = $plan->id;
        $subscription->gatewayId = $plan->gatewayId;
        $subscription->reference = 'test-cascade-' . uniqid();
        $subscription->trialDays = 0;
        $subscription->hasStarted = true;
        $subscription->subscriptionData = ['test' => 'cascade-delete'];
        Craft::$app->getElements()->saveElement($subscription, false);
        $this->_subscriptionId = $subscription->id;
    }

    public function testSubscriptionIsDeletedWhenUserIsHardDeleted(): void
    {
        self::assertNotNull(
            Subscription::find()->id($this->_subscriptionId)->status(null)->one(),
            'Subscription should exist before user deletion.'
        );

        // Soft-delete then hard-delete the user, mimicking the trash → permanently delete flow
        Craft::$app->getElements()->deleteElement($this->_user);
        Craft::$app->getElements()->deleteElement($this->_user, true);
        $this->_user = null;

        self::assertNull(
            Subscription::find()->id($this->_subscriptionId)->status(null)->one(),
            'Subscription should be deleted when its user is hard-deleted.'
        );
    }

    protected function _after(): void
    {
        parent::_after();

        // Clean up if the test failed before the user was deleted
        if ($this->_user?->id) {
            Craft::$app->getElements()->deleteElementById($this->_user->id, User::class, null, true);
        }

        $this->_user = null;
        $this->_subscriptionId = null;
    }
}
