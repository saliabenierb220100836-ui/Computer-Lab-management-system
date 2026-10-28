<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Notifications\ConfirmAdminNameChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function requestNameChange(Request $request): RedirectResponse
    {
        $request->merge([
            'first_name' => is_string($request->input('first_name')) ? trim($request->input('first_name')) : $request->input('first_name'),
            'last_name' => is_string($request->input('last_name')) ? trim($request->input('last_name')) : $request->input('last_name'),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
        ]);
        $admin = Administrator::query()->findOrFail($request->user()->id);
        abort_unless($admin->hasVerifiedEmail(), 403);

        if ($data['first_name'] === $admin->first_name && $data['last_name'] === $admin->last_name) {
            return back()->with('success', 'Your account already has that name.');
        }

        $token = Str::random(64);
        Cache::put($this->pendingNameChangeKey($token), [
            'administrator_id' => $admin->id,
            'email' => $admin->email,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ], now()->addMinutes(30));

        $url = URL::temporarySignedRoute(
            'admin.name-change.confirm',
            now()->addMinutes(30),
            ['token' => $token],
        );
        $admin->notify(new ConfirmAdminNameChange(
            $admin->first_name,
            $admin->last_name,
            $data['first_name'],
            $data['last_name'],
            $url,
        ));

        return back()->with('success', 'Check your current email address for a link to confirm the name change.');
    }

    public function confirmNameChange(string $token): View
    {
        $pending = Cache::get($this->pendingNameChangeKey($token));
        abort_unless(is_array($pending), 410, 'This name-change link has expired or has already been used.');
        $admin = Administrator::query()->findOrFail($pending['administrator_id']);
        abort_unless(hash_equals($admin->email, $pending['email']), 410, 'This name-change link is no longer valid.');

        return view('account.confirm-name-change', [
            'admin' => $admin,
            'pendingName' => trim($pending['first_name'].' '.$pending['last_name']),
            'confirmed' => false,
        ]);
    }

    public function applyNameChange(string $token): View
    {
        $pending = Cache::pull($this->pendingNameChangeKey($token));
        abort_unless(is_array($pending), 410, 'This name-change link has expired or has already been used.');
        $admin = Administrator::query()->findOrFail($pending['administrator_id']);
        abort_unless(hash_equals($admin->email, $pending['email']), 410, 'This name-change link is no longer valid.');

        $admin->update([
            'first_name' => $pending['first_name'],
            'last_name' => $pending['last_name'],
            'name' => trim($pending['first_name'].' '.$pending['last_name']),
        ]);

        return view('account.confirm-name-change', [
            'admin' => $admin,
            'pendingName' => $admin->name,
            'confirmed' => true,
        ]);
    }

    private function pendingNameChangeKey(string $token): string
    {
        return 'admin-name-change:'.$token;
    }
}
