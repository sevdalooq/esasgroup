<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Tüm ayarları gruplandırılmış olarak getir
     */
    public function index(): JsonResponse
    {
        $settings = Setting::getAllGrouped();
        return response()->json($settings);
    }

    /**
     * Belirli bir grubun ayarlarını getir
     */
    public function getByGroup(string $group): JsonResponse
    {
        $settings = Setting::where('group', $group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = [
                'value' => $setting->typed_value,
                'type' => $setting->type,
                'display_name' => $setting->display_name,
                'description' => $setting->description,
            ];
        }

        return response()->json($result);
    }

    /**
     * Ayarları toplu güncelle
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'nullable|in:string,number,boolean,json',
            'settings.*.group' => 'nullable|string',
        ]);

        $updated = [];

        foreach ($validated['settings'] as $settingData) {
            $setting = Setting::where('key', $settingData['key'])->first();

            if ($setting) {
                // Mevcut ayarı güncelle
                $value = $settingData['value'];

                // Tip dönüşümü
                if ($setting->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                } elseif ($setting->type === 'boolean') {
                    $value = $value ? 'true' : 'false';
                }

                $setting->update(['value' => $value]);
                $updated[] = $setting->key;
            } else {
                // Yeni ayar oluştur
                Setting::create([
                    'key' => $settingData['key'],
                    'value' => $settingData['value'],
                    'type' => $settingData['type'] ?? 'string',
                    'group' => $settingData['group'] ?? 'general',
                ]);
                $updated[] = $settingData['key'];
            }
        }

        // Cache'i temizle
        Setting::clearCache();

        return response()->json([
            'message' => 'Ayarlar guncellendi',
            'updated' => $updated,
        ]);
    }

    /**
     * Logo yükle
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Eski logoyu sil
        $oldLogo = Setting::get('company_logo');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        // Yeni logoyu yükle
        $path = $request->file('logo')->store('logos', 'public');

        // Ayarı güncelle
        Setting::set('company_logo', $path, 'string', 'company');

        return response()->json([
            'message' => 'Logo yuklendi',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Logoyu sil
     */
    public function deleteLogo(): JsonResponse
    {
        $logo = Setting::get('company_logo');

        if ($logo && Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->delete($logo);
        }

        Setting::set('company_logo', null, 'string', 'company');

        return response()->json([
            'message' => 'Logo silindi',
        ]);
    }
}
