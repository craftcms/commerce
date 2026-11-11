<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateResolver;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductTypeSite;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Events\DigitalProductTypeSaved;
use CraftCms\Commerce\DigitalProduct\Events\DigitalProductTypeSaving;
use CraftCms\Commerce\DigitalProduct\Models\DigitalProductType as DigitalProductTypeRecord;
use CraftCms\Commerce\DigitalProduct\Models\DigitalProductTypeSite as DigitalProductTypeSiteRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Throwable;

use function CraftCms\Cms\currentUser;

/**
 * Digital product type service. Types are stored in project config under {@see self::CONFIG_KEY}.
 */
#[Singleton]
class DigitalProductTypes
{
    public const string CONFIG_KEY = 'commerce.digitalProductTypes';

    /** @var DigitalProductType[]|null */
    private ?array $_allTypes = null;

    /** @var array<int, DigitalProductTypeSite[]> */
    private array $_siteSettingsByTypeId = [];

    /**
     * @return DigitalProductType[]
     */
    public function getAllDigitalProductTypes(): array
    {
        return $this->_allTypes ??= DigitalProductTypeRecord::query()
            ->orderBy('name')
            ->get()
            ->map(fn(DigitalProductTypeRecord $record) => new DigitalProductType([
                'id' => $record->id,
                'name' => $record->name,
                'handle' => $record->handle,
                'skuFormat' => $record->skuFormat,
                'fieldLayoutId' => $record->fieldLayoutId,
                'uid' => $record->uid,
            ]))
            ->all();
    }

    /**
     * @return int[]
     */
    public function getAllDigitalProductTypeIds(): array
    {
        return collect($this->getAllDigitalProductTypes())->pluck('id')->all();
    }

    /**
     * Returns the types whose digital products the current user can manage.
     *
     * @return DigitalProductType[]
     */
    public function getEditableDigitalProductTypes(): array
    {
        $ids = $this->getEditableDigitalProductTypeIds();

        return collect($this->getAllDigitalProductTypes())
            ->filter(fn(DigitalProductType $type) => in_array($type->id, $ids, true))
            ->values()
            ->all();
    }

    /**
     * @return int[]
     */
    public function getEditableDigitalProductTypeIds(): array
    {
        if (app()->runningInConsole()) {
            return $this->getAllDigitalProductTypeIds();
        }

        $user = currentUser();

        if (!$user) {
            return [];
        }

        return collect($this->getAllDigitalProductTypes())
            ->filter(fn(DigitalProductType $type) => $user->can("commerce-manageDigitalProductType:$type->uid"))
            ->pluck('id')
            ->all();
    }

    public function getDigitalProductTypeById(int $id): ?DigitalProductType
    {
        return collect($this->getAllDigitalProductTypes())->firstWhere('id', $id);
    }

    public function getDigitalProductTypeByHandle(string $handle): ?DigitalProductType
    {
        return collect($this->getAllDigitalProductTypes())->firstWhere('handle', $handle);
    }

    public function getDigitalProductTypeByUid(string $uid): ?DigitalProductType
    {
        return collect($this->getAllDigitalProductTypes())->firstWhere('uid', $uid);
    }

    /**
     * @return DigitalProductTypeSite[]
     */
    public function getDigitalProductTypeSites(int $typeId): array
    {
        return $this->_siteSettingsByTypeId[$typeId] ??= DigitalProductTypeSiteRecord::query()
            ->where('digitalProductTypeId', $typeId)
            ->get()
            ->map(fn(DigitalProductTypeSiteRecord $record) => new DigitalProductTypeSite([
                'id' => $record->id,
                'digitalProductTypeId' => $record->digitalProductTypeId,
                'siteId' => $record->siteId,
                'hasUrls' => (bool)$record->hasUrls,
                'uriFormat' => $record->uriFormat,
                'template' => $record->template,
            ]))
            ->all();
    }

    /**
     * @throws Throwable
     */
    public function saveDigitalProductType(DigitalProductType $type, bool $runValidation = true): bool
    {
        $isNew = !$type->id;

        event(new DigitalProductTypeSaving($type, $isNew));

        if ($runValidation && !$type->validate()) {
            return false;
        }

        if ($isNew) {
            $type->uid ??= (string)Str::uuid();
        } else {
            $type->uid = DigitalProductTypeRecord::query()->findOrFail($type->id)->uid;
        }

        ProjectConfig::set(self::CONFIG_KEY . '.' . $type->uid, $type->getConfig());

        if ($isNew) {
            $type->id = DB::table(Table::DIGITALPRODUCTTYPES)->idByUid($type->uid);
        }

        return true;
    }

    /**
     * @throws Throwable
     */
    public function handleChangedDigitalProductType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $record = DigitalProductTypeRecord::query()->where('uid', $uid)->first() ?? new DigitalProductTypeRecord();
        $isNew = !$record->exists;

        DB::transaction(function() use ($record, $isNew, $uid, $data) {
            $record->uid = $uid;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->skuFormat = $data['skuFormat'] ?? null;

            if (!empty($data['fieldLayouts']) && !empty($config = reset($data['fieldLayouts']))) {
                $layout = FieldLayout::createFromConfig($config);
                $layout->uid = key($data['fieldLayouts']);
                // A type migrated from the Digital Products plugin keeps the plugin type's field layout row.
                $layout->id = $record->fieldLayoutId ?? DB::table(CraftTable::FIELDLAYOUTS)->idByUid($layout->uid);
                $layout->type = DigitalProduct::class;
                Fields::saveLayout($layout, false);
                $record->fieldLayoutId = $layout->id;
            } elseif ($record->fieldLayoutId) {
                Fields::deleteLayoutById($record->fieldLayoutId);
                $record->fieldLayoutId = null;
            }

            $record->dateUpdated = now()->toDateTimeString();
            if ($isNew) {
                $record->dateCreated = $record->dateUpdated;
            }
            $record->save();

            $siteData = $data['siteSettings'] ?? [];
            $siteIdMap = DB::table(CraftTable::SITES)->idsByUids(array_keys($siteData));
            $oldSiteRecords = DigitalProductTypeSiteRecord::query()
                ->where('digitalProductTypeId', $record->id)
                ->get()
                ->keyBy('siteId');

            foreach ($siteData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid] ?? null;

                if (!$siteId) {
                    continue;
                }

                $siteRecord = $oldSiteRecords->get($siteId) ?? new DigitalProductTypeSiteRecord([
                    'digitalProductTypeId' => $record->id,
                    'siteId' => $siteId,
                    'dateCreated' => now()->toDateTimeString(),
                ]);
                $siteRecord->hasUrls = (bool)$siteSettings['hasUrls'];
                $siteRecord->uriFormat = $siteRecord->hasUrls ? $siteSettings['uriFormat'] : null;
                $siteRecord->template = $siteRecord->hasUrls ? $siteSettings['template'] : null;
                $siteRecord->dateUpdated = now()->toDateTimeString();
                $siteRecord->save();
            }

            $oldSiteRecords
                ->reject(fn(DigitalProductTypeSiteRecord $siteRecord) => in_array($siteRecord->siteId, $siteIdMap))
                ->each(fn(DigitalProductTypeSiteRecord $siteRecord) => $siteRecord->delete());
        });

        $this->_allTypes = null;
        unset($this->_siteSettingsByTypeId[$record->id]);

        if (!$isNew) {
            dispatch(new ResaveElements(
                elementType: DigitalProduct::class,
                criteria: [
                    'siteId' => '*',
                    'status' => null,
                    'typeId' => $record->id,
                ],
            ));
        }

        event(new DigitalProductTypeSaved($this->getDigitalProductTypeById($record->id), $isNew));
    }

    public function deleteDigitalProductTypeById(int $id): bool
    {
        $type = $this->getDigitalProductTypeById($id);

        if (!$type) {
            return false;
        }

        ProjectConfig::remove(self::CONFIG_KEY . '.' . $type->uid);

        return true;
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedDigitalProductType(ConfigEvent $event): void
    {
        $record = DigitalProductTypeRecord::query()->where('uid', $event->tokenMatches[0])->first();

        if (!$record) {
            return;
        }

        DB::transaction(function() use ($record) {
            $products = DigitalProduct::find()
                ->typeId($record->id)
                ->status(null)
                ->site('*')
                ->unique()
                ->limit(null)
                ->all();

            foreach ($products as $product) {
                Elements::deleteElement($product);
            }

            if ($record->fieldLayoutId) {
                Fields::deleteLayoutById($record->fieldLayoutId);
            }

            $record->delete();
        });

        $this->_allTypes = null;
        unset($this->_siteSettingsByTypeId[$record->id]);
    }

    /**
     * Copies each type's primary site settings to a newly added site.
     */
    public function afterSaveSiteHandler(SiteSaved $event): void
    {
        if (!$event->isNew || !isset($event->oldPrimarySiteId) || ProjectConfig::isApplyingExternalChanges()) {
            return;
        }

        $oldPrimarySiteUid = DB::table(CraftTable::SITES)->uidById($event->oldPrimarySiteId);

        foreach (ProjectConfig::get(self::CONFIG_KEY) ?? [] as $typeUid => $config) {
            if ($primarySiteSettings = $config['siteSettings'][$oldPrimarySiteUid] ?? null) {
                ProjectConfig::set(self::CONFIG_KEY . ".$typeUid.siteSettings.{$event->site->uid}", $primarySiteSettings);
            }
        }
    }

    /**
     * Removes a deleted site from each type's site settings.
     */
    public function pruneDeletedSite(SiteDeleted $event): void
    {
        foreach (array_keys(ProjectConfig::get(self::CONFIG_KEY) ?? []) as $typeUid) {
            ProjectConfig::remove(self::CONFIG_KEY . ".$typeUid.siteSettings.{$event->site->uid}");
        }
    }

    public function isTemplateValid(DigitalProductType $type, int $siteId): bool
    {
        $siteSettings = $type->getSiteSettings()[$siteId] ?? null;

        return $siteSettings
            && $siteSettings->hasUrls
            && $siteSettings->template
            && app(TemplateResolver::class)->exists($siteSettings->template, TemplateMode::Site);
    }

    /**
     * Returns the project config data for every digital product type, for project config rebuilds.
     *
     * @return array<string, array<string, mixed>>
     */
    public function rebuildProjectConfig(): array
    {
        $this->_allTypes = null;
        $this->_siteSettingsByTypeId = [];

        return collect($this->getAllDigitalProductTypes())
            ->mapWithKeys(fn(DigitalProductType $type) => [$type->uid => $type->getConfig()])
            ->all();
    }
}
