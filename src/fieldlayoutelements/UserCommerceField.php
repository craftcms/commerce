<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\fieldlayoutelements\BaseField;
use yii\base\InvalidArgumentException;

/**
 * Class UserCommerceField
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @internal
 * @todo remove in favor of a dedicated user management screen
 */
class UserCommerceField extends BaseField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'commerceInfo';
    }

    /**
     * @inheritdoc
     */
    public function mandatory(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasCustomWidth(): bool
    {
        return false;
    }

    protected function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Commerce');
    }

    protected function showLabel(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof User) {
            throw new InvalidArgumentException('UserCommerceField can only be used in the user field layout.');
        }

        if ($element->getIsUnpublishedDraft()) {
            return null;
        }

        return Craft::$app->getView()->renderTemplate('commerce/_includes/users/_customerTables', [
            'user' => $element,
        ]);
    }
}
