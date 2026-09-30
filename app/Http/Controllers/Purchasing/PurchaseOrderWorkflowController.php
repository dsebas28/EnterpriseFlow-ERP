<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * State transitions of a purchase order. Each endpoint checks the member's
 * permission; the action validates that the transition is legal.
 */
class PurchaseOrderWorkflowController extends Controller
{
    public function __construct(private readonly ChangePurchaseOrderStatus $status) {}

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);

        $this->status->submit($purchaseOrder);

        return back()->with('status', "{$purchaseOrder->number} submitted for approval.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('approve', $purchaseOrder);

        $this->status->approve($purchaseOrder, $request->user());

        return back()->with('status', "{$purchaseOrder->number} approved.");
    }

    public function returnToDraft(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('approve', $purchaseOrder);

        $this->status->returnToDraft($purchaseOrder);

        return back()->with('status', "{$purchaseOrder->number} returned to draft for changes.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseOrder);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->status->cancel($purchaseOrder, $request->user(), $validated['reason']);

        return back()->with('status', "{$purchaseOrder->number} cancelled.");
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $receive): RedirectResponse
    {
        $receipt = $receive->handle(
            $purchaseOrder,
            $request->quantities(),
            $request->user(),
            $request->warehouse(),
            $request->validated('notes'),
        );

        return back()->with('status', "Receipt {$receipt->number} recorded; stock updated.");
    }
}
