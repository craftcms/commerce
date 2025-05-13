<?php

namespace craft\commerce\behaviors;

use Craft;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\events\DefineRulesEvent;
use RuntimeException;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

class ValidateOrganizationTaxIdBehavior extends Behavior
{
    /** @var Address */
    public $owner;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof Address) {
            throw new RuntimeException('ValidateOrganizationTaxIdBehavior can only be attached to an Address element');
        }

        parent::attach($owner);
    }

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            Address::EVENT_DEFINE_RULES => 'defineRules',
        ];
    }

    /**
     * @param DefineRulesEvent $event
     * @return void
     */
    public function defineRules(DefineRulesEvent $event): void
    {
        $rules = $event->rules;
        $rules[] = [['organizationTaxId'], 'validateOrganizationTaxId', 'skipOnEmpty' => true];
        $event->rules = $rules;
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function validateOrganizationTaxId(): void
    {
        $validOrganizationTaxId = Plugin::getInstance()->getVat()->isValidVatId($this->owner->organizationTaxId);

        if (!$validOrganizationTaxId) {
            $this->owner->addError('organizationTaxId', Craft::t('commerce', 'Invalid VAT ID.'));
        }
    }
}
