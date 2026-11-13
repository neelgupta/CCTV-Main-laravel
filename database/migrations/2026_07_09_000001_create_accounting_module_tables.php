<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('income_categories')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('ticket_invoices')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 50);
            $table->string('transaction_no', 100)->nullable();
            $table->date('received_date');
            $table->string('attachment', 2048)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_id']);
            $table->index(['company_id', 'category_id', 'received_date']);
            $table->index(['company_id', 'payment_method', 'received_date']);
            $table->index(['company_id', 'transaction_no']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('vendor_name', 191);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 50);
            $table->string('transaction_no', 100)->nullable();
            $table->date('expense_date');
            $table->string('attachment', 2048)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'category_id', 'expense_date']);
            $table->index(['company_id', 'payment_method', 'expense_date']);
            $table->index(['company_id', 'vendor_name']);
            $table->index(['company_id', 'transaction_no']);
        });

        $now = now();
        $permissionRows = [
            ['key' => 'accounting.dashboard.view', 'name' => 'View Accounting Dashboard', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.incomes.view', 'name' => 'View Accounting Income', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.incomes.create', 'name' => 'Create Accounting Income', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.incomes.edit', 'name' => 'Edit Accounting Income', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.incomes.delete', 'name' => 'Delete Accounting Income', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.expenses.view', 'name' => 'View Accounting Expense', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.expenses.create', 'name' => 'Create Accounting Expense', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.expenses.edit', 'name' => 'Edit Accounting Expense', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.expenses.delete', 'name' => 'Delete Accounting Expense', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.categories.manage', 'name' => 'Manage Accounting Categories', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accounting.reports.view', 'name' => 'View Accounting Reports', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insertOrIgnore($permissionRows);

        $companyIds = DB::table('companies')->orderBy('id')->pluck('id');
        foreach ($companyIds as $companyId) {
            $incomeCategories = [
                ['name' => 'AMC Payment', 'description' => 'Income collected against AMC contracts.'],
                ['name' => 'Support Income', 'description' => 'Income from support visits and service calls.'],
                ['name' => 'Service Income', 'description' => 'Income from maintenance services.'],
                ['name' => 'Installation', 'description' => 'Income from installation jobs.'],
                ['name' => 'Product Sale', 'description' => 'Income from product sales.'],
                ['name' => 'Other', 'description' => 'Miscellaneous income.'],
            ];

            foreach ($incomeCategories as $category) {
                DB::table('income_categories')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $category['name']],
                    [
                        'description' => $category['description'],
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $expenseCategories = [
                ['name' => 'Salary', 'description' => 'Employee salary and payroll expenses.'],
                ['name' => 'Fuel', 'description' => 'Vehicle and travel fuel expenses.'],
                ['name' => 'Office Rent', 'description' => 'Office rental payments.'],
                ['name' => 'Electricity', 'description' => 'Utility and power expenses.'],
                ['name' => 'Travel', 'description' => 'Travel and local conveyance.'],
                ['name' => 'Internet', 'description' => 'Connectivity and internet bills.'],
                ['name' => 'Marketing', 'description' => 'Promotional and marketing expenses.'],
                ['name' => 'Office Expense', 'description' => 'General office running costs.'],
                ['name' => 'Repair', 'description' => 'Repair and maintenance expenses.'],
                ['name' => 'Miscellaneous', 'description' => 'Miscellaneous expenses.'],
            ];

            foreach ($expenseCategories as $category) {
                DB::table('expense_categories')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $category['name']],
                    [
                        'description' => $category['description'],
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('income_categories');
    }
};
