<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * List customers. Sort by `name` (default) or `created`, `-` for descending.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', '-name', 'created', '-created'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sort = $filters['sort'] ?? 'name';
        $column = ltrim($sort, '-') === 'created' ? 'created_at' : 'name';

        $customers = Customer::query()
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('tax_id', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return ApiResponse::success(CustomerResource::collection($customers));
    }

    public function store(SaveCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return ApiResponse::created(CustomerResource::make($customer), 'Customer created successfully.');
    }

    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return ApiResponse::success(CustomerResource::make($customer));
    }
}
