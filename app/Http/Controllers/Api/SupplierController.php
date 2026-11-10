<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('products')
            ->when(request('search'), fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', ["$s*"])
                    ->orWhere('email', 'like', "$s%");
            }))
            ->latest()
            ->paginate(request('per_page', 20));
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'company'   => 'nullable|string|max:150',
            'email'     => 'nullable|email|unique:suppliers',
            'phone'     => 'nullable|string|max:30',
            'address'   => 'nullable|string',
            'city'      => 'nullable|string|max:100',
            'country'   => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes'     => 'nullable|string',
        ]);
        $data['branch_id'] = $request->user()->branch_id;
        return response()->json(Supplier::create($data), 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json($supplier->load('purchases'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'company'   => 'nullable|string|max:150',
            'email'     => 'nullable|email|unique:suppliers,email,' . $supplier->id,
            'phone'     => 'nullable|string|max:30',
            'address'   => 'nullable|string',
            'city'      => 'nullable|string|max:100',
            'country'   => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes'     => 'nullable|string',
        ]);
        $supplier->update($data);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(['message' => 'Supplier deleted']);
    }

    public function all()
    {
        return response()->json(Supplier::where('is_active', true)->get(['id', 'name']));
    }
}
