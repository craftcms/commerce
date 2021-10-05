<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\AddressZoneInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\AddressEvent;
use craft\commerce\events\PurgeAddressesEvent;
use craft\commerce\events\UserAddressEvent;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\commerce\records\UserAddress;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use LitEmoji\LitEmoji;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\StaleObjectException;

/**
 * Address Book service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressBook extends Component
{
    /**
     * @event UserAddressEvent The event that is triggered before user address is saved.
     *
     * ```php
     * Event::on(
     * AddressBook::class,
     * AddressBook::EVENT_BEFORE_SAVE_USER_ADDRESS,
     *      function(UserAddressEvent $event) {
     *          // @var User $user
     *          $user = $event->user;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_USER_ADDRESS = 'beforeSaveUserAddress';

    /**
     * @event UserAddressEvent The event that is triggered after user address is successfully saved.
     *
     * ```php
     * Event::on(
     * AddressBook::class,
     * AddressBook::EVENT_AFTER_SAVE_USER_ADDRESS,
     *      function(UserAddressEvent $event) {
     *          // @var User $user
     *          $user = $event->user;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_USER_ADDRESS = 'afterSaveUserAddress';

    /**
     * Associates an address with the user, and saves the address.
     *
     * @param Address $address
     * @param User $user Defaults to the current customer in session if none is passing in.
     * @param bool $runValidation should we validate this address before saving.
     * @param bool|null $isPrimaryBillingAddress
     * @param bool|null $isPrimaryShippingAddress
     * @return bool
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function saveAddress(Address $address, User $user, bool $runValidation = true, ?bool $isPrimaryBillingAddress = null, ?bool $isPrimaryShippingAddress = null): bool
    {
        // Fire a 'beforeSaveUserAddress' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_USER_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_USER_ADDRESS, new UserAddressEvent([
                'address' => $address,
                'user' => $user,
            ]));
        }

        if (Plugin::getInstance()->getAddresses()->saveAddress($address, $runValidation)) {
            $userAddressRecord = UserAddress::find()->where([
                'userId' => $user->id,
                'addressId' => $address->id,
            ])->one();

            if (!$userAddressRecord) {
                $userAddressRecord = new UserAddress();
            }

            $userAddressRecord->userId = $user->id;
            $userAddressRecord->addressId = $address->id;

            // Set primary billing and shipping if we are being explicit asked to
            if (null !== $isPrimaryBillingAddress) {
                $userAddressRecord->isPrimaryBillingAddress = $isPrimaryBillingAddress;
            }

            if (null !== $isPrimaryShippingAddress) {
                $userAddressRecord->isPrimaryShippingAddress = $isPrimaryShippingAddress;
            }

            if ($userAddressRecord->save()) {
                // Fire a 'afterSaveUserAddress' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_USER_ADDRESS)) {
                    $this->trigger(self::EVENT_AFTER_SAVE_USER_ADDRESS, new UserAddressEvent([
                        'address' => $address,
                        'user' => $user,
                    ]));
                }

                return true;
            }
        }

        return false;
    }
}
