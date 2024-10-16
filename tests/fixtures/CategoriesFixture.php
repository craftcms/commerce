<?php

namespace craftcommercetests\fixtures;

use Craft;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\Structure;
use craft\services\Categories;
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


    public function init(): void
    {
        parent::init();

        $this->groupIds = [];
    }

    public function load(): void
    {
        // Refresh memoization
        Craft::$app->set('categories', new Categories());
        // TODO: remove this when category fixtures are updated in Craft core.
        $catGroup = Craft::$app->getCategories()->getGroupByHandle('categories');
        if (!$catGroup) {
            $structure = new Structure(['maxLevels' => 1]);
            if (!Craft::$app->getStructures()->saveStructure($structure)) {
                codecept_debug('Unable to save structure');
            }

            $catGroup = new CategoryGroup();
            $catGroup->name = 'Categories';
            $catGroup->handle = 'categories';
            $catGroup->structureId = $structure->id;
            $layout = Craft::$app->getFields()->getLayoutByType(Category::class);

            if ($layout) {
                $catGroup->fieldLayoutId = $layout->id;
            }

            $allSiteSettings = [];
        } else {
            $allSiteSettings = Craft::$app->getCategories()->getGroupSiteSettings($catGroup->id);
        }

        $sites = Craft::$app->getSites()->getAllSites();

        if (count($allSiteSettings) !== count($sites)) {
            foreach ($sites as $site) {
                if (!ArrayHelper::firstWhere($allSiteSettings, 'siteId', $site->id)) {
                    $siteSettings = new CategoryGroup_SiteSettings();
                    $siteSettings->siteId = $site->id;
                    $allSiteSettings[] = $siteSettings;
                }
            }
        }

        $allSiteSettings = ArrayHelper::index($allSiteSettings, 'siteId');

        $catGroup->setSiteSettings($allSiteSettings);

        if (!Craft::$app->getCategories()->saveGroup($catGroup)) {
            codecept_debug('Unable to save category group');
            codecept_debug($catGroup->getErrors());
        }

        if (empty($this->groupIds)) {
            foreach (Craft::$app->getCategories()->getAllGroups() as $group) {
                $this->groupIds[$group->handle] = $group->id;
            }
        }

        parent::load();
    }

    public function unload(): void
    {
        parent::unload();

        foreach ($this->groupIds as $groupId) {
            Craft::$app->getCategories()->deleteGroupById($groupId);
        }
        $this->groupIds = [];
    }
}
