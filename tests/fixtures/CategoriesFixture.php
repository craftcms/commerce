<?php

namespace craftcommercetests\fixtures;

use Craft;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\Structure;
use craft\test\fixtures\elements\CategoryFixture;

/**
 * Categories Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class CategoriesFixture extends CategoryFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/categories.php';

    public $depends = [FieldLayoutFixture::class, ProductFixture::class];

    public function init()
    {
        // TODO: remove this when category fixtures are updated in Craft core.
        $structure = new Structure(['maxLevels' => 1]);
        if (!Craft::$app->getStructures()->saveStructure($structure)) {
            codecept_debug('Unable to save structure');
        }

        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings = new CategoryGroup_SiteSettings();
            $siteSettings->siteId = $site->id;

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $categoryGroup = new CategoryGroup();
        $categoryGroup->name = 'Categories';
        $categoryGroup->handle = 'categories';
        $categoryGroup->structureId = $structure->id;
        $categoryGroup->setSiteSettings($allSiteSettings);

        if (!Craft::$app->getCategories()->saveGroup($categoryGroup)) {
            codecept_debug('Unable to save category group');
        }

        parent::init();
    }
}