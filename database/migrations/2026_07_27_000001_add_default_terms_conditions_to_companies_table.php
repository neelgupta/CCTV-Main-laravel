<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('default_terms_conditions')->nullable()->after('quotation_terms');
        });

        DB::table('companies')
            ->select('id', 'quotation_terms')
            ->orderBy('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    $terms = json_decode((string) $company->quotation_terms, true);

                    if (! is_array($terms)) {
                        continue;
                    }

                    $lines = collect([
                        'Warranty' => trim((string) ($terms['warranty'] ?? '')),
                        'Payment Terms' => trim((string) ($terms['payment'] ?? '')),
                        'Delivery Timeline' => trim((string) ($terms['delivery'] ?? '')),
                    ])
                        ->filter(fn (string $value) => $value !== '')
                        ->map(fn (string $value, string $label) => $label . ': ' . $value)
                        ->values()
                        ->implode("\n");

                    if ($lines === '') {
                        continue;
                    }

                    DB::table('companies')
                        ->where('id', $company->id)
                        ->update(['default_terms_conditions' => $lines]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('default_terms_conditions');
        });
    }
};
