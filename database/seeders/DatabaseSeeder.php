<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch', 'is_active' => true]
        );

        $downtownBranch = Branch::firstOrCreate(
            ['code' => 'DWTN'],
            ['name' => 'Downtown Branch', 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'admin@siril-motors.com'],
            [
                'name'      => 'Admin User',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'branch_id' => $mainBranch->id,
                
                'can_delete_transactions' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@siril-motors.com'],
            [
                'name'      => 'Manager User',
                'password'  => Hash::make('password'),
                'role'      => 'manager',
                'branch_id' => $mainBranch->id,
                
                'can_delete_transactions' => false,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@siril-motors.com'],
            [
                'name'      => 'Staff User',
                'password'  => Hash::make('password'),
                'role'      => 'staff',
                'branch_id' => $downtownBranch->id,
                
                'can_delete_transactions' => false,
            ]
        );

        $this->call(CaratSeeder::class);
        $this->call(SparePartsStructureSeeder::class);
        $this->call(FeatureSeeder::class);

        $categories = [
            ['name' => 'Engine Parts',      'slug' => 'engine-parts'],
            ['name' => 'Suspension',        'slug' => 'suspension'],
            ['name' => 'Brakes',            'slug' => 'brakes'],
            ['name' => 'Electrical',        'slug' => 'electrical'],
            ['name' => 'Transmission',      'slug' => 'transmission'],
            ['name' => 'Cooling System',    'slug' => 'cooling-system'],
            ['name' => 'Fuel System',       'slug' => 'fuel-system'],
            ['name' => 'Lights & Wipers',   'slug' => 'lights-wipers'],
            ['name' => 'Filters',           'slug' => 'filters'],
            ['name' => 'Belts & Hoses',     'slug' => 'belts-hoses'],
        ];
        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug'], 'branch_id' => $mainBranch->id],
                array_merge($cat, [ 'branch_id' => $mainBranch->id])
            );
        }

        $suppliers = [
            ['name' => 'Auto Parts Direct',      'email' => 'info@autopartsdirect.com',      'phone' => '+1-800-AUTO-001', 'city' => 'Detroit',      'country' => 'USA'],
            ['name' => 'Genuine Motors Ltd',     'email' => 'sales@genuinemotors.com',       'phone' => '+44-121-AUTO-002','city' => 'Birmingham',    'country' => 'UK'],
            ['name' => 'Asia Auto Supplies',     'email' => 'contact@asiaauto.com',          'phone' => '+81-3-AUTO-003',  'city' => 'Tokyo',        'country' => 'Japan'],
            ['name' => 'European Auto Parts Co.','email' => 'orders@euroaparts.com',         'phone' => '+49-30-AUTO-004', 'city' => 'Berlin',       'country' => 'Germany'],
            ['name' => 'Global Vehicle Parts',   'email' => 'support@globalvehicle.com',     'phone' => '+91-22-AUTO-005', 'city' => 'Mumbai',       'country' => 'India'],
        ];
        foreach ($suppliers as $sup) {
            Supplier::updateOrCreate(
                ['email' => $sup['email']],
                array_merge($sup, [ 'branch_id' => $mainBranch->id])
            );
        }

        $categoryIds = Category::pluck('id')->toArray();
        $supplierIds = Supplier::pluck('id')->toArray();
        
        $spareParts = [
            // Engine Parts
            ['name' => 'Engine Oil Filter', 'category' => 'Engine Parts', 'price' => 25],
            ['name' => 'Air Filter', 'category' => 'Engine Parts', 'price' => 35],
            ['name' => 'Spark Plugs (Set of 4)', 'category' => 'Engine Parts', 'price' => 45],
            ['name' => 'Alternator', 'category' => 'Engine Parts', 'price' => 250],
            ['name' => 'Starter Motor', 'category' => 'Engine Parts', 'price' => 180],
            // Suspension
            ['name' => 'Front Shock Absorber', 'category' => 'Suspension', 'price' => 120],
            ['name' => 'Rear Shock Absorber', 'category' => 'Suspension', 'price' => 110],
            ['name' => 'Brake Pads (Front)', 'category' => 'Brakes', 'price' => 85],
            ['name' => 'Brake Pads (Rear)', 'category' => 'Brakes', 'price' => 75],
            ['name' => 'Brake Disc', 'category' => 'Brakes', 'price' => 145],
            // Electrical
            ['name' => 'Car Battery 12V 60Ah', 'category' => 'Electrical', 'price' => 95],
            ['name' => 'Headlight Bulb (H7)', 'category' => 'Lights & Wipers', 'price' => 22],
            ['name' => 'Tail Light Bulb', 'category' => 'Lights & Wipers', 'price' => 18],
            ['name' => 'Windshield Wiper Blade', 'category' => 'Lights & Wipers', 'price' => 28],
            // Transmission
            ['name' => 'Transmission Fluid (5L)', 'category' => 'Transmission', 'price' => 65],
            ['name' => 'Clutch Plate', 'category' => 'Transmission', 'price' => 160],
            ['name' => 'Clutch Release Bearing', 'category' => 'Transmission', 'price' => 85],
            // Cooling System
            ['name' => 'Radiator', 'category' => 'Cooling System', 'price' => 280],
            ['name' => 'Water Pump', 'category' => 'Cooling System', 'price' => 195],
            ['name' => 'Thermostat', 'category' => 'Cooling System', 'price' => 55],
            // Fuel System
            ['name' => 'Fuel Filter', 'category' => 'Fuel System', 'price' => 35],
            ['name' => 'Fuel Pump', 'category' => 'Fuel System', 'price' => 220],
            ['name' => 'Fuel Injector', 'category' => 'Fuel System', 'price' => 140],
            // Filters
            ['name' => 'Cabin Air Filter', 'category' => 'Filters', 'price' => 40],
            ['name' => 'Diesel Fuel Filter', 'category' => 'Filters', 'price' => 50],
            // Belts & Hoses
            ['name' => 'Serpentine Belt', 'category' => 'Belts & Hoses', 'price' => 65],
            ['name' => 'Coolant Hose', 'category' => 'Belts & Hoses', 'price' => 45],
            ['name' => 'Power Steering Hose', 'category' => 'Belts & Hoses', 'price' => 85],
        ];

        $sparePartsData = [];
        // Legacy product seeding removed. All products are now seeded via SparePartsStructureSeeder.

        $customers = [
            ['name' => 'Alice Johnson',  'email' => 'alice@example.com',  'phone' => '+1-555-001'],
            ['name' => 'Bob Smith',      'email' => 'bob@example.com',    'phone' => '+1-555-002'],
            ['name' => 'Carol Williams', 'email' => 'carol@example.com',  'phone' => '+1-555-003'],
            ['name' => 'David Brown',    'email' => 'david@example.com',  'phone' => '+1-555-004'],
            ['name' => 'Emma Davis',     'email' => 'emma@example.com',   'phone' => '+1-555-005'],
        ];
        foreach ($customers as $cust) {
            Customer::updateOrCreate(
                ['email' => $cust['email']],
                [
                    'name' => $cust['name'],
                    'phone' => $cust['phone'],
                    'branch_id' => $mainBranch->id,
                    
                ]
            );

        }
    }
}
