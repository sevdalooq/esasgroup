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
            // Kullanıcı izinleri
            ['name' => 'users.view', 'display_name' => 'Kullanicilari Gor', 'group' => 'Kullanicilar'],
            ['name' => 'users.create', 'display_name' => 'Kullanici Olustur', 'group' => 'Kullanicilar'],
            ['name' => 'users.edit', 'display_name' => 'Kullanici Duzenle', 'group' => 'Kullanicilar'],
            ['name' => 'users.delete', 'display_name' => 'Kullanici Sil', 'group' => 'Kullanicilar'],

            // Rol izinleri
            ['name' => 'roles.view', 'display_name' => 'Rolleri Gor', 'group' => 'Roller'],
            ['name' => 'roles.create', 'display_name' => 'Rol Olustur', 'group' => 'Roller'],
            ['name' => 'roles.edit', 'display_name' => 'Rol Duzenle', 'group' => 'Roller'],
            ['name' => 'roles.delete', 'display_name' => 'Rol Sil', 'group' => 'Roller'],

            // Müşteri izinleri
            ['name' => 'customers.view', 'display_name' => 'Musterileri Gor', 'group' => 'Musteriler'],
            ['name' => 'customers.create', 'display_name' => 'Musteri Olustur', 'group' => 'Musteriler'],
            ['name' => 'customers.edit', 'display_name' => 'Musteri Duzenle', 'group' => 'Musteriler'],
            ['name' => 'customers.delete', 'display_name' => 'Musteri Sil', 'group' => 'Musteriler'],

            // Personel izinleri
            ['name' => 'personnel.view', 'display_name' => 'Personeli Gor', 'group' => 'Personel'],
            ['name' => 'personnel.create', 'display_name' => 'Personel Olustur', 'group' => 'Personel'],
            ['name' => 'personnel.edit', 'display_name' => 'Personel Duzenle', 'group' => 'Personel'],
            ['name' => 'personnel.delete', 'display_name' => 'Personel Sil', 'group' => 'Personel'],

            // Grup/Firma izinleri
            ['name' => 'groups.view', 'display_name' => 'Gruplari Gor', 'group' => 'Gruplar'],
            ['name' => 'groups.create', 'display_name' => 'Grup Olustur', 'group' => 'Gruplar'],
            ['name' => 'groups.edit', 'display_name' => 'Grup Duzenle', 'group' => 'Gruplar'],
            ['name' => 'groups.delete', 'display_name' => 'Grup Sil', 'group' => 'Gruplar'],

            // Envanter izinleri
            ['name' => 'inventory.view', 'display_name' => 'Envanteri Gor', 'group' => 'Envanter'],
            ['name' => 'inventory.create', 'display_name' => 'Envanter Olustur', 'group' => 'Envanter'],
            ['name' => 'inventory.edit', 'display_name' => 'Envanter Duzenle', 'group' => 'Envanter'],
            ['name' => 'inventory.delete', 'display_name' => 'Envanter Sil', 'group' => 'Envanter'],

            // Proje izinleri
            ['name' => 'projects.view', 'display_name' => 'Projeleri Gor', 'group' => 'Projeler'],
            ['name' => 'projects.create', 'display_name' => 'Proje Olustur', 'group' => 'Projeler'],
            ['name' => 'projects.edit', 'display_name' => 'Proje Duzenle', 'group' => 'Projeler'],
            ['name' => 'projects.delete', 'display_name' => 'Proje Sil', 'group' => 'Projeler'],
            ['name' => 'projects.manage_days', 'display_name' => 'Proje Gunlerini Yonet', 'group' => 'Projeler'],
            ['name' => 'projects.start_day', 'display_name' => 'Gun Baslat', 'group' => 'Projeler'],
            ['name' => 'projects.end_day', 'display_name' => 'Gun Bitir', 'group' => 'Projeler'],
            ['name' => 'projects.change_status', 'display_name' => 'Proje Durumu Degistir', 'group' => 'Projeler'],

            // Finansal izinler (eski)
            ['name' => 'finance.view', 'display_name' => 'Finansi Gor', 'group' => 'Finans'],
            ['name' => 'finance.manage_payments', 'display_name' => 'Odemeleri Yonet', 'group' => 'Finans'],
            ['name' => 'finance.view_reports', 'display_name' => 'Raporlari Gor', 'group' => 'Finans'],

            // Muhasebe izinleri
            ['name' => 'accounting.view', 'display_name' => 'Muhasebe Gor', 'group' => 'Muhasebe'],
            ['name' => 'accounting.manage', 'display_name' => 'Kasa Yonet', 'group' => 'Muhasebe'],
            ['name' => 'accounting.finalize', 'display_name' => 'Proje Muhasebeleştir', 'group' => 'Muhasebe'],
            ['name' => 'accounting.make_payment', 'display_name' => 'Odeme Yap', 'group' => 'Muhasebe'],
            ['name' => 'accounting.receive_payment', 'display_name' => 'Odeme Al', 'group' => 'Muhasebe'],
            ['name' => 'accounting.approve_expenses', 'display_name' => 'Masraf Onayla', 'group' => 'Muhasebe'],

            // Ayarlar izinleri
            ['name' => 'settings.view', 'display_name' => 'Ayarlari Gor', 'group' => 'Ayarlar'],
            ['name' => 'settings.edit', 'display_name' => 'Ayarlari Duzenle', 'group' => 'Ayarlar'],
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
                'display_name' => 'Yonetici',
                'description' => 'Tam yetkili sistem yoneticisi',
                'is_system' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Mudur',
                'description' => 'Proje ve personel yonetimi',
                'is_system' => true,
            ]
        );

        $supervisorRole = Role::firstOrCreate(
            ['name' => 'supervisor'],
            [
                'display_name' => 'Saha Sorumlusu',
                'description' => 'Saha operasyonlari yonetimi',
                'is_system' => true,
            ]
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Izleyici',
                'description' => 'Sadece goruntuleme yetkisi',
                'is_system' => false,
            ]
        );

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
        ])->pluck('id')->toArray();
        $managerRole->syncPermissions($managerPermissions);

        // Supervisor izinleri
        $supervisorPermissions = Permission::whereIn('name', [
            'personnel.view',
            'inventory.view',
            'projects.view', 'projects.manage_days', 'projects.start_day', 'projects.end_day', 'projects.change_status',
            'finance.manage_payments',
            'accounting.view',
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
