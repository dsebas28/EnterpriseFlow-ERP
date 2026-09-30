<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'categories' => array_map(fn (NotificationCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
                'database' => $user->wantsNotification($category, 'database'),
                'mail' => $user->wantsNotification($category, 'mail'),
            ], NotificationCategory::configurable()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (NotificationCategory::configurable() as $category) {
            $rules["preferences.{$category->value}.database"] = ['required', 'boolean'];
            $rules["preferences.{$category->value}.mail"] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules);

        // Only known categories and channels are stored, whatever was sent.
        $preferences = [];
        foreach (NotificationCategory::configurable() as $category) {
            $preferences[$category->value] = [
                'database' => (bool) $validated['preferences'][$category->value]['database'],
                'mail' => (bool) $validated['preferences'][$category->value]['mail'],
            ];
        }

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['notification_preferences' => $preferences])->save();

        return back()->with('status', 'Notification preferences saved.');
    }
}
