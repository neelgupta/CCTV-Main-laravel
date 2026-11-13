<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_invoices', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('companies')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ticket_invoices', 'staff_id')) {
                $table->foreignId('staff_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('staff')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ticket_invoices', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)
                    ->nullable()
                    ->after('staff_id');
            }
        });

        $rows = DB::table('ticket_invoices as ti')
            ->join('tickets as t', 't.id', '=', 'ti.ticket_id')
            ->leftJoin('ticket_documents as td', 'td.id', '=', 'ti.invoice_id')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_to')
            ->leftJoin('staff as s', 's.user_id', '=', 'u.id')
            ->select([
                'ti.id',
                't.company_id as company_id',
                's.id as staff_id',
                DB::raw('COALESCE(ti.total_amount, ti.invoice_total, td.total, 0) as total_amount'),
            ])
            ->orderBy('ti.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('ticket_invoices')
                ->where('id', $row->id)
                ->update([
                    'company_id' => $row->company_id,
                    'staff_id' => $row->staff_id,
                    'total_amount' => $row->total_amount,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_invoices', 'total_amount')) {
                $table->dropColumn('total_amount');
            }

            if (Schema::hasColumn('ticket_invoices', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }

            if (Schema::hasColumn('ticket_invoices', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
