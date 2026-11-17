<?php
#App\GP247\Plugins\DataExport\AppConfig.php
namespace App\GP247\Plugins\DataExport;

use App\GP247\Plugins\DataExport\Application\JobFiles;
use App\GP247\Plugins\DataExport\Models\ExtensionModel;
use App\GP247\Plugins\DataExport\Support\Access;
use GP247\Core\ExtensionConfigDefault;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminMenu;
use GP247\Core\Models\AdminPermission;
use Illuminate\Support\Facades\Schema;

/**
 * Install / update / uninstall of the Data Export plugin (Pattern A: install() and update()
 * converge to the same state and can run again safely).
 *
 * Install refuses a site where the shop is not really installed (its tables missing) instead of
 * failing on the first export. Uninstall removes every export file, the menu entry and the four
 * permissions; the run list stays (it holds no personal data). Permissions are given to no role:
 * administrators only until the owner grants them.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-install-lifecycle
 */
class AppConfig extends ExtensionConfigDefault
{
    private const MENU_PARENT_KEY = 'ADMIN_SYSTEM';

    public function __construct()
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/gp247.json'), true);
        $this->configGroup = $config['configGroup'];
        $this->configKey = $config['configKey'];
        $this->configCode = $config['configCode'] ?? $this->configKey;
        $this->requireCore = $config['requireCore'] ?? [];
        $this->requireComposerPackages = $config['requireComposerPackages'] ?? [];
        $this->requireGp247Extensions = $config['requireGp247Extensions'] ?? [];
        $this->appPath = $this->configGroup . '/' . $this->configKey;
        $this->title = trans($this->appPath . '::lang.title');
        $this->image = $this->appPath . '/' . $config['image'];
        $this->version = $config['version'];
        $this->auth = $config['auth'];
        $this->link = $config['link'];
    }

    /**
     * Why the plugin cannot run on this site, or null.
     *
     * @return string|null
     */
    public static function missingRequirement(): ?string
    {
        if (!class_exists(\GP247\Core\Backup\Application\SliceBudget::class)) {
            return 'gp247/core 3.12 or newer is required.';
        }
        if (!Schema::connection(GP247_DB_CONNECTION)->hasTable(GP247_DB_PREFIX . 'shop_product')) {
            return 'The shop is not installed on this site (run php artisan gp247:shop-install first).';
        }

        return null;
    }

    public function install()
    {
        if (AdminConfig::where('key', $this->configKey)->where('group', $this->configGroup)->exists()) {
            return ['error' => 1, 'msg' => gp247_language_render('admin.extension.plugin_exist')];
        }
        $missing = self::missingRequirement();
        if ($missing !== null) {
            return ['error' => 1, 'msg' => $missing];
        }

        try {
            AdminConfig::insert([[
                'group' => $this->configGroup,
                'code' => $this->configCode,
                'key' => $this->configKey,
                'sort' => 0,
                'store_id' => GP247_STORE_ID_GLOBAL,
                'value' => self::ON,
                'detail' => $this->appPath . '::lang.title',
            ]]);
            $this->converge();

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    public function update(?string $fromVersion = null)
    {
        try {
            $this->converge();
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }

        return ['error' => 0, 'msg' => ''];
    }

    public function uninstall()
    {
        try {
            (new AdminConfig)->where('key', $this->configKey)->where('group', $this->configGroup)->delete();
            AdminMenu::where('key', $this->configKey)->delete();
            $this->removeFilesAndPermissions();
            app(ExtensionModel::class)->uninstallExtension();

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    public function enable()
    {
        $missing = self::missingRequirement();
        if ($missing !== null) {
            return ['error' => 1, 'msg' => $missing];
        }
        (new AdminConfig)->where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::ON]);

        return ['error' => 0, 'msg' => gp247_language_render('admin.extension.enable_success')];
    }

    public function disable()
    {
        (new AdminConfig)->where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::OFF]);

        return ['error' => 0, 'msg' => ''];
    }

    public function setupStore($storeId = null)
    {
    }

    public function removeStore($storeId = null)
    {
    }

    public function clickApp()
    {
    }

    public function getInfo()
    {
        return [
            'title' => $this->title,
            'key' => $this->configKey,
            'code' => $this->configCode,
            'image' => $this->image,
            'permission' => self::ALLOW,
            'version' => $this->version,
            'auth' => $this->auth,
            'link' => $this->link,
            'value' => 0,
            'appPath' => $this->appPath,
        ];
    }

    /**
     * Delete every export file and the plugin's permissions (uninstall).
     *
     * @return void
     *
     * @aidlc-unit data-export
     * @aidlc-story US-data-export-install-lifecycle
     */
    public function removeFilesAndPermissions(): void
    {
        foreach (JobFiles::idsOnDisk() as $id) {
            JobFiles::delete($id);
        }
        AdminPermission::whereIn('slug', array_column(self::permissionDefinitions(), 'slug'))->delete();
    }

    /**
     * The four permissions of the plugin.
     *
     * WHY two URI shapes: the route middleware matches GET routes exactly, while ANY::…/<group>/*
     * covers the Livewire actions (authorized against "<admin>/data-export/<group>/<run id | new>")
     * and the download route "<admin>/data-export/<group>/<id>/download".
     *
     * @return list<array{slug: string, name: string, http_uri: string}>
     *
     * @aidlc-unit data-export
     * @aidlc-story US-data-export-access-control
     */
    public static function permissionDefinitions(): array
    {
        $base = Access::base();

        return [
            ['slug' => 'data-export.view', 'name' => 'Data export — open the screen', 'http_uri' => 'GET::' . $base],
            ['slug' => 'data-export.catalog', 'name' => 'Data export — catalog', 'http_uri' => 'ANY::' . $base . '/catalog/*'],
            ['slug' => 'data-export.customers', 'name' => 'Data export — customers', 'http_uri' => 'ANY::' . $base . '/customers/*'],
            ['slug' => 'data-export.orders', 'name' => 'Data export — orders', 'http_uri' => 'ANY::' . $base . '/orders/*'],
        ];
    }

    /**
     * Converge the plugin's schema, menu and permissions (re-entrant).
     *
     * @return void
     */
    protected function converge(): void
    {
        app(ExtensionModel::class)->installExtension();
        $this->installMenu();
        $this->installPermissions();
    }

    /**
     * Add the menu entry under System once.
     *
     * @return void
     */
    protected function installMenu(): void
    {
        if (AdminMenu::where('key', $this->configKey)->exists()) {
            return;
        }
        $parent = AdminMenu::where('key', self::MENU_PARENT_KEY)->first();
        if (!$parent) {
            return;
        }
        AdminMenu::insert([
            'sort' => 92,
            'parent_id' => $parent->id,
            'title' => $this->appPath . '::lang.title',
            'icon' => 'fas fa-file-export',
            'uri' => 'route_admin::admin_data_export.index',
            'key' => $this->configKey,
        ]);
    }

    /**
     * Create or repair the permissions.
     *
     * @return void
     */
    protected function installPermissions(): void
    {
        foreach (self::permissionDefinitions() as $definition) {
            $permission = AdminPermission::firstOrCreate(
                ['slug' => $definition['slug']],
                ['name' => $definition['name'], 'http_uri' => $definition['http_uri']]
            );
            if ($permission->http_uri !== $definition['http_uri']) {
                $permission->update(['http_uri' => $definition['http_uri']]);
            }
        }
    }
}
