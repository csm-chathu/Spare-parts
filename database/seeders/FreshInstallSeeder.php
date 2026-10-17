<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\PartCategory;
use App\Models\QualityType;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FreshInstallSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch', 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'admin@siril-motors.com'],
            [
                'name'                    => 'Admin User',
                'username'                => 'admin',
                'password'                => Hash::make('password'),
                'role'                    => 'admin',
                'branch_id'               => $mainBranch->id,
                'can_delete_transactions' => true,
            ]
        );

        foreach (['Car', 'Pickup', 'Truck', 'Van', 'Bus', 'Motorbike'] as $type) {
            VehicleType::firstOrCreate(['name' => $type]);
        }

        $brandNames = ['Toyota', 'Nissan', 'Honda', 'Mitsubishi', 'Suzuki', 'Isuzu', 'Tata', 'Hyundai', 'Kia'];
        foreach ($brandNames as $brand) {
            Brand::firstOrCreate(['name' => $brand]);
        }

        $models = [
            ['brand' => 'Toyota',     'type' => 'Car',      'name' => 'Corolla'],
            ['brand' => 'Toyota',     'type' => 'Pickup',   'name' => 'Hilux'],
            ['brand' => 'Nissan',     'type' => 'Car',      'name' => 'Sunny'],
            ['brand' => 'Nissan',     'type' => 'Van',      'name' => 'Caravan'],
            ['brand' => 'Honda',      'type' => 'Car',      'name' => 'Civic'],
            ['brand' => 'Mitsubishi', 'type' => 'Pickup',   'name' => 'L200'],
            ['brand' => 'Suzuki',     'type' => 'Car',      'name' => 'Alto'],
            ['brand' => 'Isuzu',      'type' => 'Truck',    'name' => 'NKR'],
            ['brand' => 'Tata',       'type' => 'Truck',    'name' => 'LPT 1618'],
            ['brand' => 'Hyundai',    'type' => 'Van',      'name' => 'H1'],
            ['brand' => 'Kia',        'type' => 'Van',      'name' => 'Pregio'],
        ];
        foreach ($models as $m) {
            $brand = Brand::where('name', $m['brand'])->first();
            $type  = VehicleType::where('name', $m['type'])->first();
            if ($brand && $type) {
                VehicleModel::firstOrCreate([
                    'brand_id'        => $brand->id,
                    'vehicle_type_id' => $type->id,
                    'name'            => $m['name'],
                ]);
            }
        }

        $partCategories = [
            'Engine Parts', 'Brake Parts', 'Suspension Parts', 'Electrical Parts',
            'Body Parts', 'Filters', 'Cooling System', 'Transmission',
            'Fuel System', 'Lights & Wipers', 'Belts & Hoses',
        ];
        foreach ($partCategories as $cat) {
            PartCategory::firstOrCreate(['name' => $cat]);
        }

        foreach (['Genuine', 'OEM', 'Aftermarket Premium', 'Economy'] as $q) {
            QualityType::firstOrCreate(['name' => $q]);
        }

        $this->call(FeatureSeeder::class);
    }
}
