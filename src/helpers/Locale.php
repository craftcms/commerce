<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\helpers\ArrayHelper;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Locale Helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.13
 */
class Locale
{
    /**
     * Set language of the application
     *
     * @param $toLanguage
     * @throws InvalidConfigException
     */
    public static function switchAppLanguage($appLanguage, $formattingLanguage = null): void
    {
        Craft::$app->language = $appLanguage;
        $locale = Craft::$app->getI18n()->getLocaleById($appLanguage);
        Craft::$app->set('locale', $locale);

        if ($formattingLanguage !== null) {
            $locale = Craft::$app->getI18n()->getLocaleById($formattingLanguage);
        }

        Craft::$app->set('formattingLocale', $locale);
    }

    /**
     * Get the created sites languages and all languages.
     *
     * @throws Exception
     */
    public static function getSiteAndOtherLanguages(): array
    {
        $pdfLanguageOptions['siteLanguages']['optgroup'] = Craft::t('commerce', 'Site Languages');

        $siteLanguageOptions = [];
        // Get current site's locale
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $locale = Craft::$app->getI18n()->getLocaleById($site->language);

            $siteLanguageOptions[$locale->getLanguageID()] = $site->name . ' - ' . $locale->getDisplayName();
        }

        $pdfLanguageOptions = array_merge($pdfLanguageOptions, $siteLanguageOptions);

        $pdfLanguageOptions['otherLanguages']['optgroup'] = Craft::t('commerce', 'Other Languages');

        /** @var \craft\i18n\Locale[] $allLocales */
        $allLocales = ArrayHelper::index(Craft::$app->getI18n()->getAppLocales(), 'id');
        ArrayHelper::multisort($allLocales, 'displayName');

        $allLocaleOptions = [];

        foreach ($allLocales as $locale) {
            $allLocaleOptions[$locale->id] = $locale->getDisplayName();
        }

        $otherLocaleOptions = array_diff_key($allLocaleOptions, $siteLanguageOptions);

        return array_merge($pdfLanguageOptions, $otherLocaleOptions);
    }
}
