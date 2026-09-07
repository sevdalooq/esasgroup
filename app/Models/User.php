<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kullanıcının rolleri
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withTimestamps();
    }

    /**
     * Kullanıcının özel izinleri (grant/revoke)
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Kullanıcı belirli bir role sahip mi?
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Kullanıcı admin mi?
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Kullanıcı belirli bir izne sahip mi?
     * Önce kullanıcı özel izinlerine, sonra rol izinlerine bakar
     */
    public function hasPermission(string $permissionName): bool
    {
        // Admin her şeye yetkili
        if ($this->isAdmin()) {
            return true;
        }

        // Kullanıcı özel izinlerini kontrol et
        $userPermission = $this->permissions()
            ->where('name', $permissionName)
            ->first();

        if ($userPermission) {
            // Özel olarak revoke edilmişse false
            if ($userPermission->pivot->type === 'revoke') {
                return false;
            }
            // Özel olarak grant edilmişse true
            return true;
        }

        // Rol izinlerini kontrol et
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kullanıcının tüm etkin izinlerini döndür
     */
    public function getAllPermissions(): array
    {
        $permissions = [];

        // Rol izinlerini ekle
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[$permission->name] = true;
            }
        }

        // Kullanıcı özel izinlerini uygula
        foreach ($this->permissions as $permission) {
            if ($permission->pivot->type === 'grant') {
                $permissions[$permission->name] = true;
            } else {
                unset($permissions[$permission->name]);
            }
        }

        return array_keys($permissions);
    }

    /**
     * Rol ata
     */
    public function assignRole(Role $role): void
    {
        if (!$this->hasRole($role->name)) {
            $this->roles()->attach($role->id);
        }
    }

    /**
     * Rol kaldır
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * Özel izin ver
     */
    public function grantPermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([
            $permission->id => ['type' => 'grant']
        ]);
    }

    /**
     * Özel izin kaldır
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([
            $permission->id => ['type' => 'revoke']
        ]);
    }

    /**
     * Özel izni temizle (rol iznine dön)
     */
    public function clearPermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }
}
