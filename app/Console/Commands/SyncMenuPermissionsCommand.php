<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Permission;
use App\Support\MenuCatalog;
use Illuminate\Console\Command;

class SyncMenuPermissionsCommand extends Command
{
    protected $signature = 'menu:sync-permissions {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Menyinkronkan ulang permission_id pada core_menus berdasarkan definisi katalog menu (idempoten, tanpa truncate).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $definitions = MenuCatalog::definitions();

        $permissionMap = Permission::query()
            ->pluck('id', 'slug')
            ->all();

        $updated = 0;
        $unchanged = 0;
        $missingMenus = [];
        $missingPermissions = [];

        foreach ($definitions as $menuData) {
            $this->syncMenu(
                $menuData,
                null,
                $permissionMap,
                $dryRun,
                $updated,
                $unchanged,
                $missingMenus,
                $missingPermissions
            );

            if (!empty($menuData['children'])) {
                $parent = Menu::where('module', $menuData['module'])
                    ->where('url', $menuData['url'])
                    ->whereNull('parent_id')
                    ->first();

                if (!$parent) {
                    continue;
                }

                foreach ($menuData['children'] as $childData) {
                    $this->syncMenu(
                        $childData,
                        $parent->id,
                        $permissionMap,
                        $dryRun,
                        $updated,
                        $unchanged,
                        $missingMenus,
                        $missingPermissions
                    );
                }
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Selesai. Diperbarui: {$updated}, sudah sesuai: {$unchanged}.");

        if (!empty($missingPermissions)) {
            $this->warn('Permission slug tidak ditemukan: ' . implode(', ', array_unique($missingPermissions)));
        }

        if (!empty($missingMenus)) {
            $this->warn('Menu tidak ditemukan di DB: ' . implode(', ', array_unique($missingMenus)));
        }

        return self::SUCCESS;
    }

    private function syncMenu(
        array $menuData,
        ?int $parentId,
        array $permissionMap,
        bool $dryRun,
        int &$updated,
        int &$unchanged,
        array &$missingMenus,
        array &$missingPermissions
    ): void {
        $slug = $menuData['permission_slug'] ?? null;
        $expectedPermissionId = null;

        if ($slug !== null) {
            if (!array_key_exists($slug, $permissionMap)) {
                // Slug didefinisikan tetapi permission belum ada — jangan paksa
                // menjadi null (bisa menghilangkan gate yang valid). Lewati.
                $missingPermissions[] = $slug;
                return;
            }
            $expectedPermissionId = $permissionMap[$slug];
        }

        $query = Menu::where('module', $menuData['module'])->where('url', $menuData['url']);
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);
        $menu = $query->first();

        if (!$menu) {
            $missingMenus[] = $menuData['module'] . ':' . $menuData['url'];
            return;
        }

        $current = $menu->permission_id;
        $expected = $expectedPermissionId;

        if ((int) $current === (int) $expected) {
            $unchanged++;
            return;
        }

        $updated++;

        if ($dryRun) {
            $this->line(sprintf(
                '  ~ %s (%s) : %s -> %s',
                $menu->name,
                $menu->url,
                $this->label($current),
                $this->label($expected)
            ));

            return;
        }

        $menu->update(['permission_id' => $expected]);

        $this->line(sprintf('  ✓ %s (%s) -> %s', $menu->name, $menu->url, $this->label($expected)));
    }

    private function label(?int $permissionId): string
    {
        if ($permissionId === null) {
            return 'null';
        }

        return Permission::whereKey($permissionId)->value('slug') ?? ('#' . $permissionId);
    }
}
