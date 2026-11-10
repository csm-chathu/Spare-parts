<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX ft_products_search (name, part_number)');
        DB::statement('ALTER TABLE customers ADD FULLTEXT INDEX ft_customers_search (name, phone, vehicle_number)');
        DB::statement('ALTER TABLE job_cards ADD FULLTEXT INDEX ft_job_cards_search (customer_name, vehicle_number, customer_phone)');
        DB::statement('ALTER TABLE employees ADD FULLTEXT INDEX ft_employees_search (name, designation, department)');
        DB::statement('ALTER TABLE suppliers ADD FULLTEXT INDEX ft_suppliers_search (name)');
        DB::statement('ALTER TABLE expenses ADD FULLTEXT INDEX ft_expenses_search (description)');
        DB::statement('ALTER TABLE business_loans ADD FULLTEXT INDEX ft_loans_search (lender_name)');
        DB::statement('ALTER TABLE rent_payments ADD FULLTEXT INDEX ft_rent_search (property_name, landlord_name)');
        DB::statement('ALTER TABLE journal_entries ADD FULLTEXT INDEX ft_journal_search (description)');
        DB::statement('ALTER TABLE accounts ADD FULLTEXT INDEX ft_accounts_search (name)');
        DB::statement('ALTER TABLE audit_logs ADD FULLTEXT INDEX ft_audit_search (action)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP INDEX ft_products_search');
        DB::statement('ALTER TABLE customers DROP INDEX ft_customers_search');
        DB::statement('ALTER TABLE job_cards DROP INDEX ft_job_cards_search');
        DB::statement('ALTER TABLE employees DROP INDEX ft_employees_search');
        DB::statement('ALTER TABLE suppliers DROP INDEX ft_suppliers_search');
        DB::statement('ALTER TABLE expenses DROP INDEX ft_expenses_search');
        DB::statement('ALTER TABLE business_loans DROP INDEX ft_loans_search');
        DB::statement('ALTER TABLE rent_payments DROP INDEX ft_rent_search');
        DB::statement('ALTER TABLE journal_entries DROP INDEX ft_journal_search');
        DB::statement('ALTER TABLE accounts DROP INDEX ft_accounts_search');
        DB::statement('ALTER TABLE audit_logs DROP INDEX ft_audit_search');
    }
};
