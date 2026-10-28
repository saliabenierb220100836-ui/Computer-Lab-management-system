<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfilePhotoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg', 'extensions:jpeg', 'max:2048'],
        ]);

        $user = $request->user();
        $previousPath = $user->profile_photo_path;
        $filename = rawurlencode($user->student_or_employee_id).'.jpeg';
        $path = $request->file('photo')->storePubliclyAs('profile-photos', $filename, 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the uploaded profile photo.');
        }

        $user->update(['profile_photo_path' => $path]);

        if ($previousPath && $previousPath !== $path && ! Storage::disk('public')->delete($previousPath)) {
            Log::warning('Unable to remove a replaced profile photo.', [
                'user_id' => $user->id,
                'path' => $previousPath,
            ]);
        }

        return back()->with('success', 'Your profile photo has been updated.');
    }
}
