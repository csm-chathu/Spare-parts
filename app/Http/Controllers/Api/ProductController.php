<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $products = Product::with([
                'partCategory:id,name',
                'partBrand:id,name',
                'qualityType:id,name',
                'vehicleType:id,name',
                'brand:id,name',
                'model:id,name',
                'supplier:id,name',
            ])
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when(request('search'), fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->where('name', 'like', "%$s%")
                    ->orWhere('sku', 'like', "%$s%")
                    ->orWhere('barcode', 'like', "%$s%")
                    ->orWhere('part_number', 'like', "%$s%");
            }))
            ->when(request('part_category_id'), fn($q, $c) => $q->where('part_category_id', $c))
            ->when(request('vehicle_type_id'),  fn($q, $v) => $q->where('vehicle_type_id', $v))
            ->when(request('brand_id'),         fn($q, $b) => $q->where('brand_id', $b))
            ->when(request('model_id'),          fn($q, $m) => $q->where('model_id', $m))
            ->when(request('low_stock'), fn($q) => $q->whereColumn('stock_quantity', '<=', 'min_stock_level'))
            ->latest()
            ->paginate(request('per_page', 20));

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'part_number'      => 'nullable|string|max:100',
            'description'      => 'nullable|string',
            'part_brand_id'    => 'nullable|exists:part_brands,id',
            'part_category_id' => 'nullable|exists:part_categories,id',
            'quality_type_id'  => 'nullable|exists:quality_types,id',
            'vehicle_type_id'  => 'nullable|exists:vehicle_types,id',
            'brand_id'         => 'nullable|exists:brands,id',
            'model_id'         => 'nullable|exists:models,id',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'purchase_price'   => 'required|numeric|min:0',
            'selling_price'    => 'required|numeric|min:0',
            'stock_quantity'   => 'required|integer|min:0',
            'min_stock_level'  => 'required|integer|min:0',
            'rack_location'    => 'nullable|string|max:50',
            'is_active'        => 'boolean',
            'barcode'          => 'nullable|string|max:100|unique:products,barcode',
            'image'            => 'nullable|image|max:2048',
            'image_url'        => 'nullable|url|max:1000',
            'image_public_id'  => 'nullable|string|max:255',
        ]);

        $last = Product::withTrashed()->max('id') ?? 0;
        $data['sku']       = str_pad($last + 1, 6, '0', STR_PAD_LEFT);
        $data['branch_id'] = $request->user()->branch_id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif (!empty($data['image_url'])) {
            $data['image']          = $data['image_url'];
            $data['image_public_id'] = $data['image_public_id'] ?? null;
        }
        unset($data['image_url']);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product)
    {
        $this->authorizeBranch($product->branch_id);
        return response()->json($product->load(['partCategory', 'qualityType', 'vehicleType', 'brand', 'model', 'supplier']));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'part_number'      => 'nullable|string|max:100',
            'description'      => 'nullable|string',
            'part_brand_id'    => 'nullable|exists:part_brands,id',
            'part_category_id' => 'nullable|exists:part_categories,id',
            'quality_type_id'  => 'nullable|exists:quality_types,id',
            'vehicle_type_id'  => 'nullable|exists:vehicle_types,id',
            'brand_id'         => 'nullable|exists:brands,id',
            'model_id'         => 'nullable|exists:models,id',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'purchase_price'   => 'required|numeric|min:0',
            'selling_price'    => 'required|numeric|min:0',
            'stock_quantity'   => 'required|integer|min:0',
            'min_stock_level'  => 'required|integer|min:0',
            'rack_location'    => 'nullable|string|max:50',
            'is_active'        => 'boolean',
            'barcode'          => 'nullable|string|max:100|unique:products,barcode,' . $product->id,
            'image'            => 'nullable|image|max:2048',
            'image_url'        => 'nullable|url|max:1000',
            'image_public_id'  => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image && !$this->isExternalUrl($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image']          = $request->file('image')->store('products', 'public');
            $data['image_public_id'] = null;
        } elseif (!empty($data['image_url'])) {
            if ($product->image && !$this->isExternalUrl($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image']          = $data['image_url'];
            $data['image_public_id'] = $data['image_public_id'] ?? null;
        }
        unset($data['image_url']);

        $this->authorizeBranch($product->branch_id);
        $product->update($data);
        return response()->json($product->fresh(['partCategory', 'qualityType', 'vehicleType', 'brand', 'model', 'supplier']));
    }

    public function destroy(Product $product)
    {
        $this->authorizeBranch($product->branch_id);
        if ($product->image && !$this->isExternalUrl($product->image)) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    private function isExternalUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function authorizeBranch(?int $branchId): void
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $branchId) {
            abort(403, 'Forbidden for this branch.');
        }
    }
}
