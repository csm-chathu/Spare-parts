<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        return Employee::with('branch:id,name,code')
            ->when($request->search, fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->whereRaw('MATCH(name, designation, department) AGAINST(? IN BOOLEAN MODE)', ["$s*"])
                      ->orWhere('employee_number', 'like', "$s%");
            }))
            ->when($request->department, fn($q, $d) => $q->where('department', $d))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(20);
    }

    public function all()
    {
        return Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'employee_number', 'name', 'designation', 'basic_salary']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:200',
            'nic'             => 'nullable|string|max:30|unique:employees,nic',
            'designation'     => 'nullable|string|max:100',
            'department'      => 'nullable|string|max:100',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'basic_salary'    => 'required|numeric|min:0',
            'joined_date'     => 'required|date',
            'terminated_date' => 'nullable|date|after:joined_date',
            'contact_phone'   => 'nullable|string|max:20',
            'contact_email'   => 'nullable|email|max:200',
            'address'         => 'nullable|string|max:500',
            'bank_name'       => 'nullable|string|max:100',
            'bank_account'    => 'nullable|string|max:50',
            'is_active'       => 'boolean',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $seq    = Employee::withTrashed()->count() + 1;
        $data['employee_number'] = 'EMP-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        $data['branch_id']       = auth()->user()->branch_id;

        $employee = Employee::create($data);
        AuditLog::record('employee_created', "Employee {$employee->employee_number} – {$employee->name} added", $employee);

        return response()->json($employee, 201);
    }

    public function show(Employee $employee)
    {
        $employee->load(['branch:id,name', 'salaryPayments' => function ($q) {
            $q->with('paidBy:id,name')->orderByDesc('payment_date')->limit(12);
        }]);
        return response()->json($employee);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:200',
            'nic'             => 'nullable|string|max:30|unique:employees,nic,' . $employee->id,
            'designation'     => 'nullable|string|max:100',
            'department'      => 'nullable|string|max:100',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'basic_salary'    => 'required|numeric|min:0',
            'joined_date'     => 'required|date',
            'terminated_date' => 'nullable|date|after:joined_date',
            'contact_phone'   => 'nullable|string|max:20',
            'contact_email'   => 'nullable|email|max:200',
            'address'         => 'nullable|string|max:500',
            'bank_name'       => 'nullable|string|max:100',
            'bank_account'    => 'nullable|string|max:50',
            'is_active'       => 'boolean',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $employee->update($data);
        AuditLog::record('employee_updated', "Employee {$employee->employee_number} updated", $employee);

        return response()->json($employee->fresh('branch:id,name'));
    }

    public function destroy(Employee $employee)
    {
        if ($employee->salaryPayments()->exists()) {
            return response()->json(['message' => 'Cannot delete employee with salary records. Deactivate instead.'], 422);
        }

        AuditLog::record('employee_deleted', "Employee {$employee->employee_number} – {$employee->name} deleted", $employee);
        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }
}
