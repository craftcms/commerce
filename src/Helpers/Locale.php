<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use Craft;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Sites;

class Locale
{
    public static function switchAppLanguage(string $toLanguage, ?string $formattingLocale = null): void
    {
        // These Craft::$app property assignments are Yii2-layer calls that still work
        // via the yii2-adapter during the transition to Craft 6.
        Craft::$app->language = $toLanguage;
        $locale = I18N::getLocaleById($toLanguage);
        Craft::$app->set('locale', $locale);

        if ($formattingLocale !== null) {
            $locale = I18N::getLocaleById($formattingLocale);
        }

        Craft::$app->set('formattingLocale', $locale);

        // The Laravel-side app locale drives CraftCms\Cms\Translation\I18N::getFormattingLocale()'s
        // fallback (used by number/currency formatting outside CP requests) — the Craft::$app
        // assignments above don't reach it, so it needs to be set here too.
        app()->setLocale($toLanguage);
    }

    public static function getSiteAndOtherLanguages(): array
    {
        $pdfLanguageOptions['siteLanguages']['optgroup'] = t('Site Languages', category: 'commerce');

        $siteLanguageOptions = [];
        foreach (Sites::getAllSites() as $site) {
            $locale = I18N::getLocaleById($site->language);
            $siteLanguageOptions[$locale->getLanguageID()] = $site->name . ' - ' . $locale->getDisplayName();
        }

        $pdfLanguageOptions = array_merge($pdfLanguageOptions, $siteLanguageOptions);
        $pdfLanguageOptions['otherLanguages']['optgroup'] = t('Other Languages', category: 'commerce');

        $allLocales = I18N::getAppLocales()->keyBy('id')->sortBy('displayName');

        $allLocaleOptions = [];
        foreach ($allLocales as $locale) {
            $allLocaleOptions[$locale->id] = $locale->getDisplayName();
        }

        $otherLocaleOptions = array_diff_key($allLocaleOptions, $siteLanguageOptions);

        return array_merge($pdfLanguageOptions, $otherLocaleOptions);
    }
}
