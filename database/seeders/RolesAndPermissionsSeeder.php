<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // İzinleri oluştur
        $permissions = [
            // Saha (mobil/QR) izinleri
            ['name' => 'field.access', 'display_name' => 'Saha Ekranına Eriş', 'group' => 'Saha'],
            ['name' => 'field.scan', 'display_name' => 'QR/NFC ile İşlem Yap', 'group' => 'Saha'],
            ['name' => 'self.access', 'display_name' => 'Personel Modu (kendi görevleri)', 'group' => 'Saha'],

            // Aday havuzu izinleri
            ['name' => 'candidates.view', 'display_name' => 'Başvuruları Gör', 'group' => 'Aday Havuzu'],
            ['name' => 'candidates.manage', 'display_name' => 'Başvuruları Onayla/Reddet', 'group' => 'Aday Havuzu'],

            // Kullanıcı izinleri
            ['name' => 'users.view', 'display_name' => 'Kullanıcıları Gör', 'group' => 'Kullanıcılar'],
            ['name' => 'users.create', 'display_name' => 'Kullanıcı Oluştur', 'group' => 'Kullanıcılar'],
            ['name' => 'users.edit', 'display_name' => 'Kullanıcı Düzenle', 'group' => 'Kullanıcılar'],
            ['name' => 'users.delete', 'display_name' => 'Kullanıcı Sil', 'group' => 'Kullanıcılar'],

            // Rol izinleri
            ['name' => 'roles.view', 'display_name' => 'Rolleri Gör', 'group' => 'Roller'],
            ['name' => 'roles.create', 'display_name' => 'Rol Oluştur', 'group' => 'Roller'],
            ['name' => 'roles.edit', 'display_name' => 'Rol Düzenle', 'group' => 'Roller'],
            ['name' => 'roles.delete', 'display_name' => 'Rol Sil', 'group' => 'Roller'],

            // Müşteri izinleri
            ['name' => 'customers.view', 'display_name' => 'Müşterileri Gör', 'group' => 'Müşteriler'],
            ['name' => 'customers.create', 'display_name' => 'Müşteri Oluştur', 'group' => 'Müşteriler'],
            ['name' => 'customers.edit', 'display_name' => 'Müşteri Düzenle', 'group' => 'Müşteriler'],
            ['name' => 'customers.delete', 'display_name' => 'Müşteri Sil', 'group' => 'Müşteriler'],

            // Personel izinleri
            ['name' => 'personnel.view', 'display_name' => 'Personeli Gör', 'group' => 'Personel'],
            ['name' => 'personnel.create', 'display_name' => 'Personel Oluştur', 'group' => 'Personel'],
            ['name' => 'personnel.edit', 'display_name' => 'Personel Düzenle', 'group' => 'Personel'],
            ['name' => 'personnel.delete', 'display_name' => 'Personel Sil', 'group' => 'Personel'],

            // Grup/Firma izinleri
            ['name' => 'groups.view', 'display_name' => 'Ekipleri Gör', 'group' => 'Ekipler'],
            ['name' => 'groups.create', 'display_name' => 'Ekip Oluştur', 'group' => 'Ekipler'],
            ['name' => 'groups.edit', 'display_name' => 'Ekip Düzenle', 'group' => 'Ekipler'],
            ['name' => 'groups.delete', 'display_name' => 'Ekip Sil', 'group' => 'Ekipler'],

            // Envanter izinleri
            ['name' => 'inventory.view', 'display_name' => 'Envanteri Gör', 'group' => 'Envanter'],
            ['name' => 'inventory.create', 'display_name' => 'Envanter Oluştur', 'group' => 'Envanter'],
            ['name' => 'inventory.edit', 'display_name' => 'Envanter Düzenle', 'group' => 'Envanter'],
            ['name' => 'inventory.delete', 'display_name' => 'Envanter Sil', 'group' => 'Envanter'],

            // Proje izinleri
            ['name' => 'projects.view', 'display_name' => 'Projeleri Gör', 'group' => 'Projeler'],
            ['name' => 'projects.create', 'display_name' => 'Proje Oluştur', 'group' => 'Projeler'],
            ['name' => 'projects.edit', 'display_name' => 'Proje Düzenle', 'group' => 'Projeler'],
            ['name' => 'projects.delete', 'display_name' => 'Proje Sil', 'group' => 'Projeler'],
            ['name' => 'projects.manage_days', 'display_name' => 'Proje Günlerini Yönet', 'group' => 'Projeler'],
            ['name' => 'projects.start_day', 'display_name' => 'Gün Başlat', 'group' => 'Projeler'],
            ['name' => 'projects.end_day', 'display_name' => 'Gün Bitir', 'group' => 'Projeler'],
            ['name' => 'projects.change_status', 'display_name' => 'Proje Durumu Değiştir', 'group' => 'Projeler'],

            // Finansal izinler (eski)
            ['name' => 'finance.view', 'display_name' => 'Finansı Gör', 'group' => 'Finans'],
            ['name' => 'finance.manage_payments', 'display_name' => 'Ödemeleri Yönet', 'group' => 'Finans'],
            ['name' => 'finance.view_reports', 'display_name' => 'Raporları Gör', 'group' => 'Finans'],

            // Muhasebe izinleri
            ['name' => 'accounting.view', 'display_name' => 'Muhasebe Gör', 'group' => 'Muhasebe'],
            ['name' => 'accounting.manage', 'display_name' => 'Kasa Yönet', 'group' => 'Muhasebe'],
            ['name' => 'accounting.finalize', 'display_name' => 'Proje Muhasebeleştir', 'group' => 'Muhasebe'],
            ['name' => 'accounting.make_payment', 'display_name' => 'Ödeme Yap', 'group' => 'Muhasebe'],
            ['name' => 'accounting.receive_payment', 'display_name' => 'Ödeme Al', 'group' => 'Muhasebe'],
            ['name' => 'accounting.approve_expenses', 'display_name' => 'Masraf Onayla', 'group' => 'Muhasebe'],

            // Ayarlar izinleri
            ['name' => 'settings.view', 'display_name' => 'Ayarları Gör', 'group' => 'Ayarlar'],
            ['name' => 'settings.edit', 'display_name' => 'Ayarları Düzenle', 'group' => 'Ayarlar'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Rolleri oluştur
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Yönetici',
                'description' => 'Tam yetkili sistem yöneticisi',
                'is_system' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Müdür',
                'description' => 'Proje ve personel yönetimi',
                'is_system' => true,
            ]
        );

        $supervisorRole = Role::firstOrCreate(
            ['name' => 'supervisor'],
            [
                'display_name' => 'Saha Sorumlusu',
                'description' => 'Saha operasyonları yönetimi',
                'is_system' => true,
            ]
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'İzleyici',
                'description' => 'Sadece görüntüleme yetkisi',
                'is_system' => false,
            ]
        );

        $personnelRole = Role::firstOrCreate(
            ['name' => 'personnel'],
            [
                'display_name' => 'Personel',
                'description' => 'Sahada görevli personel: sadece kendi görevleri, giriş, mola ve konum',
                'is_system' => true,
            ]
        );
        $personnelRole->syncPermissions(Permission::whereIn('name', ['self.access'])->pluck('id')->toArray());

        // Admin tüm izinlere sahip
        $adminRole->syncPermissions(Permission::pluck('id')->toArray());

        // Manager izinleri
        $managerPermissions = Permission::whereIn('name', [
            'customers.view', 'customers.create', 'customers.edit',
            'personnel.view', 'personnel.create', 'personnel.edit',
            'groups.view', 'groups.create', 'groups.edit',
            'inventory.view', 'inventory.create', 'inventory.edit',
            'projects.view', 'projects.create', 'projects.edit', 'projects.manage_days', 'projects.start_day', 'projects.end_day', 'projects.change_status',
            'finance.view', 'finance.manage_payments', 'finance.view_reports',
            'accounting.view', 'accounting.finalize', 'accounting.make_payment', 'accounting.receive_payment', 'accounting.approve_expenses',
            'settings.view', 'settings.edit',
            'field.access', 'field.scan', 'candidates.view', 'candidates.manage',
        ])->pluck('id')->toArray();
        $managerRole->syncPermissions($managerPermissions);

        // Supervisor izinleri
        $supervisorPermissions = Permission::whereIn('name', [
            'personnel.view',
            'inventory.view',
            'projects.view', 'projects.manage_days', 'projects.start_day', 'projects.end_day', 'projects.change_status',
            'finance.manage_payments',
            'accounting.view',
            'field.access', 'field.scan',
        ])->pluck('id')->toArray();
        $supervisorRole->syncPermissions($supervisorPermissions);

        // Viewer izinleri
        $viewerPermissions = Permission::whereIn('name', [
            'customers.view',
            'personnel.view',
            'groups.view',
            'inventory.view',
            'projects.view',
            'finance.view',
        ])->pluck('id')->toArray();
        $viewerRole->syncPermissions($viewerPermissions);

        // İlk kullanıcıya admin rolü ver
        $firstUser = User::first();
        if ($firstUser && !$firstUser->roles()->exists()) {
            $firstUser->assignRole($adminRole);
        }
    }
}
