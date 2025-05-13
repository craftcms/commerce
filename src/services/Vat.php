<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\Plugin;
use DvK\Vat\Validator;
use Exception;
use yii\base\Component;

/**
 * VAT service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class Vat extends Component
{
    /**
     * @var string
     */
    protected string $cacheKeyPrefix = 'commerce:validVatId:';

    /**
     * @var mixed Allows for the possibility of a custom validator
     */
    protected mixed $validator;

    /**
     * @param string $vatId
     * @return bool
     */
    public function isValidVatId(string $vatId): bool
    {
        // Do we have a valid VAT ID in our cache?
        $validOrganizationTaxId = Craft::$app->getCache()->exists($this->cacheKeyPrefix . $vatId);

        // If we do not have a valid VAT ID in cache, see if we can get one from the API
        if (!$validOrganizationTaxId) {
            try {
                $validators = Plugin::getInstance()->getTaxes()->getEnabledTaxIdValidators();
                foreach ($validators as $validator) {
                    if ($validator->validateFormat($vatId) && $validator->validate($vatId)) {
                        $validOrganizationTaxId = true;
                        break;
                    }
                }
            } catch (Exception $e) {
                Craft::error('Communication with VAT API failed: ' . $e->getMessage(), __METHOD__);

                $validOrganizationTaxId = false;
            }
        }

        if (!$validOrganizationTaxId) {
            // Clean up if the API returned false and the item was still in cache
            Craft::$app->getCache()->delete($this->cacheKeyPrefix . $vatId);
            return false;
        }

        Craft::$app->getCache()->set($this->cacheKeyPrefix . $vatId, '1');
        return true;
    }

    /**
     * @return Validator
     * @deprecated in 5.3.0 use Taxes::getEnabledTaxIdValidators() instead
     */
    protected function getVatValidator(): Validator
    {
        if (!isset($this->validator)) {
            $this->validator = new Validator();
        }

        return $this->validator;
    }
}
