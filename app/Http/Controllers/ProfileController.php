<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show', ['user' => auth()->user()]);
    }

    public function updateAvatar(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $ruta = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $ruta]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Foto de perfil actualizada correctamente.',
                'avatar_url' => $user->avatarUrl(),
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Foto de perfil actualizada correctamente.');
    }
}
