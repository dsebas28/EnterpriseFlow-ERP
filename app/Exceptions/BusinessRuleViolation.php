<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request that is well-formed and authorised but violates a business
 * rule (e.g. removing the last owner). Rendered as 422: the client can fix
 * it by changing the request, unlike a 403.
 */
class BusinessRuleViolation extends DomainException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'errors' => ['rule' => [$this->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return back()->withErrors(['rule' => $this->getMessage()]);
    }
}
