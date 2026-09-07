<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Giriş yapan kullanıcının (ve bağlı personel kaydının) uygulama içi bildirimleri */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->notifications();

        $personnel = \App\Models\Personnel::where('user_id', $user->id)->first();
        if ($personnel) {
            $query = \Illuminate\Notifications\DatabaseNotification::query()
                ->where(function ($q) use ($user, $personnel) {
                    $q->where(fn ($w) => $w->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id))
                      ->orWhere(fn ($w) => $w->where('notifiable_type', $personnel->getMorphClass())->where('notifiable_id', $personnel->id));
                });
        }

        $items = $query->latest()->limit((int) $request->get('limit', 30))->get();

        return response()->json([
            'data' => $items,
            'unread_count' => (clone $items)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = \Illuminate\Notifications\DatabaseNotification::findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Okundu olarak işaretlendi']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        $personnel = \App\Models\Personnel::where('user_id', $request->user()->id)->first();
        $personnel?->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Tüm bildirimler okundu']);
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate(['fcm_token' => 'required|string|max:255']);
        $request->user()->forceFill(['fcm_token' => $data['fcm_token']])->save();
        \App\Models\Personnel::where('user_id', $request->user()->id)->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['message' => 'Cihaz kaydedildi']);
    }
}
