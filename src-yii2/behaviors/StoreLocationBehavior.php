<?php

namespace craft\commerce\behaviors;

use craft\commerce\records\StoreSettings;
use craft\elements\Address;
use craft\events\AuthorizationCheckEvent;
use craft\events\ModelEvent;
use RuntimeException;
use yii\base\Behavior;

class StoreLocationBehavior extends Behavior
{
    /** @var Address */
    public $owner;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof Address) {
            throw new RuntimeException('StoreLocationBehavior can only be attached to an Address element');
        }

        parent::attach($owner);
    }

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            Address::EVENT_AFTER_SAVE => 'saveStoreLocation',
            Address::EVENT_AUTHORIZE_VIEW => 'authorize',
        ];
    }

    /**
     * @param AuthorizationCheckEvent $event
     * @return void
     */
    public function authorize(AuthorizationCheckEvent $event)
    {
        $event->authorized = true;
    }

    /**
     * @param ModelEvent $event
     * @return void
     */
    public function saveStoreLocation(ModelEvent $event): void
    {
        $address = $event->sender;
        /** @var StoreSettings $store */
        $store = StoreSettings::find()->one(); // we only have one store right now, and we assume it is the first one
        $store->locationAddressId = $address->id;
        $store->save();
    }
}
