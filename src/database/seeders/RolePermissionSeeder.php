<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'create-request',
            'view-request',
            'update-request',
            'delete-request',
            'cancel-request',
            'assign-rescuer',
            'manage-users',
            'view-all-requests',
        ];

        foreach ($permissions as $permission) {
            if (!Permission::where('name', $permission)->exists()) {
                Permission::create(['name' => $permission]);
            }
        }

        $userRole = Role::where('name', 'user')->first();
        if (!$userRole) {
            $userRole = Role::create(['name' => 'user']);
        }
        $userRole->syncPermissions(['create-request', 'view-request', 'cancel-request']);

        $rescuerRole = Role::where('name', 'rescuer')->first();
        if (!$rescuerRole) {
            $rescuerRole = Role::create(['name' => 'rescuer']);
        }
        $rescuerRole->syncPermissions(['view-all-requests', 'update-request', 'assign-rescuer']);

        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $adminRole = Role::create(['name' => 'admin']);
        }
        $adminRole->syncPermissions(Permission::all());

        // Create admin user and assign admin role
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'admin',
                'email' => 'admin@admin.com',
                'phone' => '00000000000',
                'password' => Hash::make('password'),
            ]);
        }

        if (!$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        // ADR-0005: 기본 행사("상시 운영") 보장. 여기서 하는 이유는 created_by 가 NOT NULL 이라
        // «유저가 생긴 뒤»에만 만들 수 있기 때문이다 — 신규 설치의 migrate 단계에는 유저가 없다.
        // 멱등(firstOrCreate). 기존 설치는 ensure_default_event_exists 마이그레이션이 담당한다.
        Project::defaultEvent();
    }
}
