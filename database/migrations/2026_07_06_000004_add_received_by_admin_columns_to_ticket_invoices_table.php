<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_invoices', 'received_by_admin')) {
                $table->boolean('received_by_admin')->default(false)->after('payment_method');
            }

            if (! Schema::hasColumn('ticket_invoices', 'received_by_admin_at')) {
                $table->dateTime('received_by_admin_at')->nullable()->after('received_by_admin');
            }

            if (! Schema::hasColumn('ticket_invoices', 'received_by_admin_by')) {
                $table->foreignId('received_by_admin_by')->nullable()->after('received_by_admin_at')->constrained('users')->nullOnDelete();
            }

            $table->index(['staff_id', 'payment_status'], 'ticket_invoices_staff_payment_index');
            $table->index(['staff_id', 'received_by_admin'], 'ticket_invoices_staff_received_index');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_invoices', 'staff_id')) {
                $table->dropIndex('ticket_invoices_staff_received_index');
                $table->dropIndex('ticket_invoices_staff_payment_index');
            }

            if (Schema::hasColumn('ticket_invoices', 'received_by_admin_by')) {
                $table->dropConstrainedForeignId('received_by_admin_by');
            }

            if (Schema::hasColumn('ticket_invoices', 'received_by_admin_at')) {
                $table->dropColumn('received_by_admin_at');
            }

            if (Schema::hasColumn('ticket_invoices', 'received_by_admin')) {
                $table->dropColumn('received_by_admin');
            }
        });
    }
};
