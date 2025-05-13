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
     * @param string $toLanguage
     * @param string|null $formattingLocale
     * @throws InvalidConfigException
     * @TODO rename `toLanguage` to `locale` in 6.0
     */
    public static function switchAppLanguage(string $toLanguage, ?string $formattingLocale = null): void
    {
        Craft::$app->language = $toLanguage;
        $locale = Craft::$app->getI18n()->getLocaleById($toLanguage);
        Craft::$app->set('locale', $locale);

        if ($formattingLocale !== null) {
            $locale = Craft::$app->getI18n()->getLocaleById($formattingLocale);
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
