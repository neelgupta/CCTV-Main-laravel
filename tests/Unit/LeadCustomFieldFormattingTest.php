<?php

namespace Tests\Unit;

use App\Models\Lead;
use Tests\TestCase;

class LeadCustomFieldFormattingTest extends TestCase
{
    public function test_date_custom_fields_are_displayed_as_day_month_year(): void
    {
        $lead = new Lead();

        $this->assertSame(
            '01/06/2026',
            $lead->formatCustomFieldValue([
                'type' => 'date',
            ], '2026-06-01')
        );
    }

    public function test_excel_serial_date_custom_fields_are_converted_before_display(): void
    {
        $lead = new Lead();

        $this->assertSame(
            '11/05/2026',
            $lead->formatCustomFieldValue([
                'type' => 'date',
            ], 46153)
        );
    }
}
