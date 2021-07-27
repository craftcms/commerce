<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\validators;

use Craft;
use yii\validators\Validator;

/**
 * Class GbVatIdValidator.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class GbVatIdValidator extends Validator
{
    /**
     * @var string
     */
    protected string $vatIdPattern = '/^(\d{9}|\d{12}|(GD|HA)\d{3})$/';

    /**
     * @var string
     */
    protected string $vatIdValidationUrl = 'https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!isset($this->message)) {
            $this->message = Craft::t('commerce', '{attribute} must be a valid GB VAT ID.');
        }
    }

    /**
     * @param string $vatId
     * @return bool
     */
    protected function matchesVatIdPattern(string $vatId): bool
    {
        return preg_match($this->vatIdPattern, $this->_removeVatIdPrefix($vatId)) > 0;
    }

    /**
     * Remove the two alpha character prefix
     *
     * @param string $vatId
     * @return string
     */
    private function _removeVatIdPrefix(string $vatId): string
    {
        return preg_replace('/^[a-zA-Z]{2}/', '', $vatId);
    }

    /**
     * @param mixed $value
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function validateValue($value): ?array
    {
        if (!$this->matchesVatIdPattern($value)) {
            return [$this->message, []];
        }

        $client = Craft::createGuzzleClient();

        try {
            $client->request('GET', $this->vatIdValidationUrl . $this->_removeVatIdPrefix($value), ['headers' => ['Accept' => 'application/json']]);
        } catch (\Exception $e) {
            // Log any actual errors, 404 is simply an invalid VAT ID
            if ($e->getCode() !== 404) {
                Craft::error($e->getMessage());
            }

            return [$this->message, []];
        }

        return null;
    }
}
