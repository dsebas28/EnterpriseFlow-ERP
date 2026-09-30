<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ChangeSaleStatus;
use App\Actions\Sales\ConfirmSale;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleWorkflowController extends Controller
{
    public function markPending(Sale $sale, ChangeSaleStatus $status): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $status->markPending($sale);

        return back()->with('status', "{$sale->number} marked as pending.");
    }

    public function returnToDraft(Sale $sale, ChangeSaleStatus $status): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $status->returnToDraft($sale);

        return back()->with('status', "{$sale->number} returned to draft.");
    }

    public function confirm(Request $request, Sale $sale, ConfirmSale $confirm): RedirectResponse
    {
        Gate::authorize('confirm', $sale);

        $confirm->handle($sale, $request->user());

        return back()->with('status', "{$sale->number} confirmed; stock deducted.");
    }

    public function cancel(Request $request, Sale $sale, CancelSale $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $sale);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $cancel->handle($sale, $request->user(), $validated['reason']);

        return back()->with('status', "{$sale->number} cancelled.");
    }
}
