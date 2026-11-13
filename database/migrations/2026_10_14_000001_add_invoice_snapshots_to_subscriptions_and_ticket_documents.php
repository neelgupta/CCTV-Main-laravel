<?php

use App\Models\CompanySubscription;
use App\Models\TicketDocument;
use App\Support\BillingDocumentSnapshot;
use App\Support\SubscriptionInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_subscriptions', 'invoice_snapshot')) {
                $table->json('invoice_snapshot')->nullable();
            }
        });

        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_documents', 'billing_snapshot')) {
                $table->json('billing_snapshot')->nullable();
            }
        });

        // Existing invoices can only be frozen with today's details; from here on later
        // edits to settings, companies, clients or plans no longer change them.
        CompanySubscription::query()
            ->whereNull('invoice_snapshot')
            ->with(['company', 'plan'])
            ->chunkById(200, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    DB::table('company_subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['invoice_snapshot' => json_encode(SubscriptionInvoice::snapshot($subscription))]);
                }
            });

        TicketDocument::query()
            ->whereNull('billing_snapshot')
            ->chunkById(200, function ($documents): void {
                foreach ($documents as $document) {
                    DB::table('ticket_documents')
                        ->where('id', $document->id)
                        ->update(['billing_snapshot' => json_encode(BillingDocumentSnapshot::capture($document))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('company_subscriptions', 'invoice_snapshot')) {
                $table->dropColumn('invoice_snapshot');
            }
        });

        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_documents', 'billing_snapshot')) {
                $table->dropColumn('billing_snapshot');
            }
        });
    }
};
