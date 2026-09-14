<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyiapkan peran beserta hak aksesnya.
 *
 * The permission list is regenerated from the Filament resources that exist,
 * so this seeder stays correct as new resources arrive in later phases.
 */
class RoleSeeder extends Seeder
{
    /** Subjects only a system administrator may touch. */
    private const ADMIN_ONLY_SUBJECTS = ['User', 'Role'];

    /** Actions that cannot change data. */
    private const READ_ACTIONS = ['ViewAny', 'View'];

    /** Day-to-day actions, without the ability to delete. */
    private const OPERATIONAL_ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Replicate', 'Reorder'];

    /** Subjects asset staff may create and edit. Staff carry out work orders but do not plan them. */
    private const STAFF_WRITABLE_SUBJECTS = ['Asset', 'AssetAssignment', 'WorkOrder'];

    /**
     * The permission list comes from code rather than data, so scanning the
     * resources once per process is enough.
     *
     * @var array<int, string>|null
     */
    private static ?array $discovered = null;

    public function run(): void
    {
        $this->ensurePermissionsExist();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::pluck('name');

        $this->sync('super_admin', $permissions->all());

        $this->sync('asset_manager', $permissions
            ->reject(fn (string $name): bool => in_array($this->subject($name), self::ADMIN_ONLY_SUBJECTS, strict: true))
            ->all());

        $this->sync('asset_staff', $permissions
            ->filter(function (string $name): bool {
                $action = $this->action($name);
                $subject = $this->subject($name);

                if (in_array($subject, self::ADMIN_ONLY_SUBJECTS, strict: true)) {
                    return false;
                }

                return in_array($subject, self::STAFF_WRITABLE_SUBJECTS, strict: true)
                    ? in_array($action, self::OPERATIONAL_ACTIONS, strict: true)
                    : in_array($action, self::READ_ACTIONS, strict: true);
            })
            ->all());

        $this->sync('auditor', $permissions
            ->filter(fn (string $name): bool => in_array($this->action($name), self::READ_ACTIONS, strict: true)
                && ! in_array($this->subject($name), self::ADMIN_ONLY_SUBJECTS, strict: true))
            ->all());
    }

    private function ensurePermissionsExist(): void
    {
        if (self::$discovered === null) {
            Artisan::call('shield:generate', [
                '--all' => true,
                '--panel' => 'admin',
                '--option' => 'permissions',
                '--no-interaction' => true,
            ]);

            self::$discovered = Permission::pluck('name')->all();

            return;
        }

        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        Permission::upsert(
            collect(self::$discovered)
                ->map(fn (string $name): array => ['name' => $name, 'guard_name' => 'web', ...$timestamps])
                ->all(),
            ['name', 'guard_name'],
        );
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function sync(string $role, array $permissions): void
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }

    private function action(string $permission): string
    {
        return str($permission)->before(':')->toString();
    }

    private function subject(string $permission): string
    {
        return str($permission)->after(':')->toString();
    }
}
