<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            if (! Permission::where('name', $permission)->exists()) {
                Permission::create(['name' => $permission]);
            }
        }

        $userRole = Role::where('name', 'user')->first();
        if (! $userRole) {
            $userRole = Role::create(['name' => 'user']);
        }
        $userRole->syncPermissions(['create-request', 'view-request', 'cancel-request']);

        // 시스템 롤은 «일반회원(user) / 관리자회원(admin)» 둘뿐이다 (2026-08-12).
        //
        // 🔑 예전에는 rescuer 가 있었다. 대응 인력의 체계가 둘(시스템 롤 + 행사 역할)이라
        //    관리자가 「이 사람 권한이 뭐지」를 두 군데서 봐야 했다. 신고는 ADR-0005 로
        //    이미 「모든 신고는 행사에 속한다」로 일원화됐으므로, 대응 인력도 행사 역할
        //    하나로 모은다 — 상시 구급 인력은 항상 활성인 「상시 운영」 행사의 구급대다.
        //    (이관은 2026_08_12_120000 마이그레이션.)
        //
        // permission 목록은 남긴다 — 관리자별 메뉴 권한 오픈에 쓸 예정.

        $adminRole = Role::where('name', 'admin')->first();
        if (! $adminRole) {
            $adminRole = Role::create(['name' => 'admin']);
        }
        $adminRole->syncPermissions(Permission::all());

        // Create admin user and assign admin role
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if (! $adminUser) {
            $adminUser = User::create([
                'name' => 'admin',
                'email' => 'admin@admin.com',
                'phone' => '00000000000',
                'password' => Hash::make('password'),
            ]);
        }

        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        // ADR-0005: 기본 행사("상시 운영") 보장. 여기서 하는 이유는 created_by 가 NOT NULL 이라
        // «유저가 생긴 뒤»에만 만들 수 있기 때문이다 — 신규 설치의 migrate 단계에는 유저가 없다.
        // 멱등(firstOrCreate). 기존 설치는 ensure_default_event_exists 마이그레이션이 담당한다.
        Project::defaultEvent();
    }
}
