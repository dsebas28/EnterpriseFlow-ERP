<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerNoteController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('addNote', $customer);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $note = $customer->notes()->make(['body' => $validated['body']]);
        $note->user_id = $request->user()?->id;
        $note->save();

        return back()->with('status', 'Note added.');
    }

    public function destroy(Customer $customer, CustomerNote $note): RedirectResponse
    {
        Gate::authorize('deleteNote', [$customer, $note]);

        $note->delete();

        return back()->with('status', 'Note deleted.');
    }
}
