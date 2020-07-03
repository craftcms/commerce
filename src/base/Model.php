<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\Model as BaseModel;

/**
 * Class Model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Model extends BaseModel
{
    /**
     * @inheritDoc
     */
    public function fields()
    {
        $fields = parent::fields();

        //TODO Remove this when we require Craft 3.5 and the bahaviour supports define fields event
        if ($this->getBehavior('currencyAttributes')) {
            $fields = array_merge($fields, $this->getBehavior('currencyAttributes')->currencyFields());
        }

        return $fields;
    }
}
