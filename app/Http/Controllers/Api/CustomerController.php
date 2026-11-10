<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $customers = Customer::withCount('sales')
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when(request('search'), fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->whereRaw('MATCH(name, phone, vehicle_number) AGAINST(? IN BOOLEAN MODE)', ["$s*"])
                    ->orWhere('email', 'like', "$s%");
            }))
            ->latest()
            ->paginate(request('per_page', 20));
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'email'          => 'nullable|email|unique:customers',
            'phone'          => 'nullable|string|max:30',
            'vehicle_number' => 'nullable|string|max:30',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string|max:100',
            'country'        => 'nullable|string|max:100',
            'date_of_birth'  => 'nullable|date',
            'gender'         => 'nullable|in:male,female,other',
            'notes'          => 'nullable|string',
        ]);
        $data['branch_id'] = $request->user()->branch_id;
        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer)
    {
        $this->authorizeBranch($customer->branch_id);
        return response()->json($customer->load(['sales' => fn($q) => $q->with('items.product')->latest()->take(10)]));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'email'          => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone'          => 'nullable|string|max:30',
            'vehicle_number' => 'nullable|string|max:30',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string|max:100',
            'country'        => 'nullable|string|max:100',
            'date_of_birth'  => 'nullable|date',
            'gender'         => 'nullable|in:male,female,other',
            'notes'          => 'nullable|string',
        ]);
        $this->authorizeBranch($customer->branch_id);
        $customer->update($data);
        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeBranch($customer->branch_id);
        $customer->delete();
        return response()->json(['message' => 'Customer deleted']);
    }

    public function all()
    {
        $user = request()->user();
        $query = Customer::orderBy('name');
        if (!$user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }
        return response()->json($query->get(['id', 'name', 'phone', 'vehicle_number']));
    }

    private function authorizeBranch(?int $branchId): void
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $branchId) {
            abort(403, 'Forbidden for this branch.');
        }
    }
}
