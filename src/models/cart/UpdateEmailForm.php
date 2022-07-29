<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\commerce\base\CartForm;
use craft\errors\ElementNotFoundException;
use Exception;
use Throwable;
use yii\base\InvalidConfigException;

/**
 * Update Email Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class UpdateEmailForm extends CartForm
{
    public ?string $email = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = ['email', 'required'];
        $rules[] = ['email', 'validateSessionUser'];
        $rules[] = ['email', 'email'];

        return $rules;
    }

    /**
     * @param string $attribute
     * @return void
     */
    public function validateSessionUser(string $attribute): void
    {
        if (Craft::$app->getUser()->getIdentity()) {
            $this->addError('email', Craft::t('commerce', 'Cannot set email when logged in.'));
        }
    }

    /**
     * @return bool
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws InvalidConfigException
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        if (($this->getOrder()->getEmail() === null || $this->getOrder()->getEmail() != $this->email)) {
            try {
                $this->getOrder()->setEmail($this->email);
            } catch (Exception $e) {
                $this->addError('email', $e->getMessage());
            }
        }

        return true;
    }
}
