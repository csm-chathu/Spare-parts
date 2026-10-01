<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    private const ALL = ['admin', 'manager', 'accountant', 'hr', 'finance', 'cashier', 'branch', 'auditor'];

    private array $features = [
        // General
        ['key' => 'dashboard',            'label' => 'Dashboard',          'route' => '/',                     'icon' => 'HomeIcon',                    'group' => 'general',    'sort_order' => 1,  'roles' => self::ALL],
        ['key' => 'products',             'label' => 'Parts Inventory',    'route' => '/products',             'icon' => 'CubeIcon',                    'group' => 'general',    'sort_order' => 2,  'roles' => self::ALL],
        ['key' => 'master-data',          'label' => 'Master Data',        'route' => '/master-data',          'icon' => 'CircleStackIcon',              'group' => 'general',    'sort_order' => 3,  'roles' => ['admin', 'manager']],
        ['key' => 'customers',            'label' => 'Customers',          'route' => '/customers',            'icon' => 'UsersIcon',                   'group' => 'general',    'sort_order' => 4,  'roles' => self::ALL],
        ['key' => 'suppliers',            'label' => 'Suppliers',          'route' => '/suppliers',            'icon' => 'TruckIcon',                   'group' => 'general',    'sort_order' => 5,  'roles' => self::ALL],
        ['key' => 'sales',                'label' => 'Sales',              'route' => '/sales',                'icon' => 'ShoppingCartIcon',            'group' => 'general',    'sort_order' => 6,  'roles' => self::ALL],
        ['key' => 'job-cards',            'label' => 'Job Cards',          'route' => '/job-cards',            'icon' => 'WrenchScrewdriverIcon',       'group' => 'general',    'sort_order' => 7,  'roles' => self::ALL],

        // Purchasing
        ['key' => 'purchase-orders',      'label' => 'Purchase Orders',    'route' => '/purchase-orders',      'icon' => 'ClipboardDocumentIcon',       'group' => 'purchasing', 'sort_order' => 10, 'roles' => self::ALL],
        ['key' => 'grn',                  'label' => 'GRN',                'route' => '/grn',                  'icon' => 'InboxArrowDownIcon',           'group' => 'purchasing', 'sort_order' => 11, 'roles' => self::ALL],
        ['key' => 'goods-invoices',       'label' => 'Goods Invoices',     'route' => '/goods-invoices',       'icon' => 'DocumentCurrencyDollarIcon',  'group' => 'purchasing', 'sort_order' => 12, 'roles' => self::ALL],
        ['key' => 'supplier-payments',    'label' => 'Supplier Payments',  'route' => '/supplier-payments',    'icon' => 'CreditCardIcon',              'group' => 'purchasing', 'sort_order' => 13, 'roles' => self::ALL],
        ['key' => 'purchase-returns',     'label' => 'Purchase Returns',   'route' => '/purchase-returns',     'icon' => 'ArrowUturnLeftIcon',          'group' => 'purchasing', 'sort_order' => 14, 'roles' => self::ALL],
        ['key' => 'stock-ledger',         'label' => 'Stock Ledger',       'route' => '/stock-ledger',         'icon' => 'ChartBarSquareIcon',          'group' => 'purchasing', 'sort_order' => 15, 'roles' => self::ALL],

        // Admin / Operations
        ['key' => 'reports',              'label' => 'Reports',            'route' => '/reports',              'icon' => 'ChartBarIcon',                'group' => 'admin',      'sort_order' => 20, 'roles' => ['admin', 'manager', 'accountant', 'auditor', 'finance']],
        ['key' => 'day-end',              'label' => 'Day End',            'route' => '/day-end',              'icon' => 'ClipboardDocumentCheckIcon',  'group' => 'admin',      'sort_order' => 21, 'roles' => ['admin', 'manager', 'cashier', 'branch']],
        ['key' => 'audit-log',            'label' => 'Audit Log',          'route' => '/audit-log',            'icon' => 'ClipboardDocumentListIcon',   'group' => 'admin',      'sort_order' => 22, 'roles' => ['admin']],
        ['key' => 'users',                'label' => 'Users',              'route' => '/users',                'icon' => 'UserGroupIcon',               'group' => 'admin',      'sort_order' => 23, 'roles' => ['admin']],
        ['key' => 'shop-settings',        'label' => 'Shop Settings',      'route' => '/shop-settings',        'icon' => 'Cog6ToothIcon',               'group' => 'admin',      'sort_order' => 24, 'roles' => ['admin', 'manager']],
        ['key' => 'expenses',             'label' => 'Expenses',           'route' => '/expenses',             'icon' => 'ReceiptPercentIcon',          'group' => 'admin',      'sort_order' => 25, 'roles' => ['admin', 'manager', 'finance', 'auditor']],
        ['key' => 'sms',                  'label' => 'SMS Centre',         'route' => '/sms',                  'icon' => 'DevicePhoneMobileIcon',       'group' => 'admin',      'sort_order' => 26, 'roles' => ['admin', 'manager']],

        // HR
        ['key' => 'employees',            'label' => 'Employees',          'route' => '/employees',            'icon' => 'UserGroupIcon',               'group' => 'hr',         'sort_order' => 30, 'roles' => ['admin', 'manager', 'hr']],
        ['key' => 'salary-payments',      'label' => 'Salary Payments',    'route' => '/salary-payments',      'icon' => 'BanknotesIcon',               'group' => 'hr',         'sort_order' => 31, 'roles' => ['admin', 'manager', 'hr']],

        // Finance
        ['key' => 'loans',                'label' => 'Business Loans',     'route' => '/loans',                'icon' => 'BuildingLibraryIcon',         'group' => 'finance',    'sort_order' => 40, 'roles' => ['admin', 'manager', 'finance', 'auditor']],
        ['key' => 'customer-investments', 'label' => 'Owner Investments',  'route' => '/customer-investments', 'icon' => 'CurrencyDollarIcon',          'group' => 'finance',    'sort_order' => 41, 'roles' => ['admin', 'manager', 'finance']],
        ['key' => 'rentals',              'label' => 'Monthly Rentals',    'route' => '/rentals',              'icon' => 'HomeModernIcon',              'group' => 'finance',    'sort_order' => 42, 'roles' => ['admin', 'manager', 'finance', 'auditor']],

        // Accounting
        ['key' => 'opening-balances',     'label' => 'Opening Balances',   'route' => '/opening-balances',     'icon' => 'ScaleIcon',                   'group' => 'accounting', 'sort_order' => 50, 'roles' => ['admin', 'manager', 'accountant', 'auditor']],
        ['key' => 'accounts',             'label' => 'Chart of Accounts',  'route' => '/accounts',             'icon' => 'BookOpenIcon',                'group' => 'accounting', 'sort_order' => 51, 'roles' => ['admin', 'manager', 'accountant', 'auditor']],
        ['key' => 'journal-entries',      'label' => 'Journal Entries',    'route' => '/journal-entries',      'icon' => 'DocumentTextIcon',            'group' => 'accounting', 'sort_order' => 52, 'roles' => ['admin', 'manager', 'accountant', 'auditor']],
        ['key' => 'general-ledger',       'label' => 'General Ledger',     'route' => '/general-ledger',       'icon' => 'PresentationChartBarIcon',    'group' => 'accounting', 'sort_order' => 53, 'roles' => ['admin', 'manager', 'accountant', 'auditor']],
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->features as $feature) {
            $roles = $feature['roles'];
            unset($feature['roles']);

            $feature['created_at'] = $now;
            $feature['updated_at'] = $now;

            $id = DB::table('features')->insertGetId($feature);

            $pivotRows = array_map(fn($role) => [
                'role'       => $role,
                'feature_id' => $id,
                'can_view'   => true,
            ], $roles);

            DB::table('role_features')->insert($pivotRows);
        }
    }
}
