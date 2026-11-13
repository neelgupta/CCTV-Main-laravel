<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LeadAppointment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'lead_id',
        'appointment_date',
        'slot_label',
        'slot_start_time',
        'slot_end_time',
        'assigned_to',
        'created_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public const MAX_LEADS_PER_SLOT = 10;

    /**
     * Definition of standard operating slots:
     * 1-hour slots from 9 AM (09:00) to 8 PM (20:00).
     * Lunch break: 2 PM to 3 PM (14:00 - 15:00) is excluded.
     *
     * Slots:
     * 09:00 - 10:00
     * 10:00 - 11:00
     * 11:00 - 12:00
     * 12:00 - 13:00
     * 13:00 - 14:00
     * [14:00 - 15:00 Lunch Break - Excluded]
     * 15:00 - 16:00
     * 16:00 - 17:00
     * 17:00 - 18:00
     * 18:00 - 19:00
     * 19:00 - 20:00
     */
    public static function definedSlots(?Company $company = null): array
    {
        $defaultSlots = [
            [
                'start_time' => '09:00:00',
                'end_time'   => '10:00:00',
                'start_h_i'  => '09:00',
                'end_h_i'    => '10:00',
                'label'      => '09:00 AM - 10:00 AM',
                'short_label'=> '09:00 - 10:00',
                'agenda'     => 'Morning Kickoff & Discovery Session',
            ],
            [
                'start_time' => '10:00:00',
                'end_time'   => '11:00:00',
                'start_h_i'  => '10:00',
                'end_h_i'    => '11:00',
                'label'      => '10:00 AM - 11:00 AM',
                'short_label'=> '10:00 - 11:00',
                'agenda'     => 'Product Demonstration & Overview',
            ],
            [
                'start_time' => '11:00:00',
                'end_time'   => '12:00:00',
                'start_h_i'  => '11:00',
                'end_h_i'    => '12:00',
                'label'      => '11:00 AM - 12:00 PM',
                'short_label'=> '11:00 - 12:00',
                'agenda'     => 'Technical Architecture & Site Assessment',
            ],
            [
                'start_time' => '12:00:00',
                'end_time'   => '13:00:00',
                'start_h_i'  => '12:00',
                'end_h_i'    => '13:00',
                'label'      => '12:00 PM - 01:00 PM',
                'short_label'=> '12:00 - 13:00',
                'agenda'     => 'Quotation & Scope Review',
            ],
            [
                'start_time' => '13:00:00',
                'end_time'   => '14:00:00',
                'start_h_i'  => '13:00',
                'end_h_i'    => '14:00',
                'label'      => '01:00 PM - 02:00 PM',
                'short_label'=> '13:00 - 14:00',
                'agenda'     => 'Pre-Lunch Consultation & Proposal Q&A',
            ],
            // 14:00 - 15:00 (2:00 PM - 3:00 PM) lunch break omitted
            [
                'start_time' => '15:00:00',
                'end_time'   => '16:00:00',
                'start_h_i'  => '15:00',
                'end_h_i'    => '16:00',
                'label'      => '03:00 PM - 04:00 PM',
                'short_label'=> '15:00 - 16:00',
                'agenda'     => 'Post-Lunch Solution Walkthrough',
            ],
            [
                'start_time' => '16:00:00',
                'end_time'   => '17:00:00',
                'start_h_i'  => '16:00',
                'end_h_i'    => '17:00',
                'label'      => '04:00 PM - 05:00 PM',
                'short_label'=> '16:00 - 17:00',
                'agenda'     => 'Security & Camera Planning Session',
            ],
            [
                'start_time' => '17:00:00',
                'end_time'   => '18:00:00',
                'start_h_i'  => '17:00',
                'end_h_i'    => '18:00',
                'label'      => '05:00 PM - 06:00 PM',
                'short_label'=> '17:00 - 18:00',
                'agenda'     => 'Commercial Terms & Budget Discussion',
            ],
            [
                'start_time' => '18:00:00',
                'end_time'   => '19:00:00',
                'start_h_i'  => '18:00',
                'end_h_i'    => '19:00',
                'label'      => '06:00 PM - 07:00 PM',
                'short_label'=> '18:00 - 19:00',
                'agenda'     => 'Evening Follow-up & Decision Alignment',
            ],
            [
                'start_time' => '19:00:00',
                'end_time'   => '20:00:00',
                'start_h_i'  => '19:00',
                'end_h_i'    => '20:00',
                'label'      => '07:00 PM - 08:00 PM',
                'short_label'=> '19:00 - 20:00',
                'agenda'     => 'Final Wrap-up & Contract Closing',
            ],
        ];

        if (! $company && auth()->check()) {
            $company = auth()->user()->company;
        }

        $customAgendas = $company?->appointment_slot_agendas;
        if (is_array($customAgendas) && ! empty($customAgendas)) {
            foreach ($defaultSlots as &$slot) {
                $start = $slot['start_time'];
                $short = $slot['start_h_i'];
                if (isset($customAgendas[$start]) && trim((string) $customAgendas[$start]) !== '') {
                    $slot['agenda'] = trim((string) $customAgendas[$start]);
                } elseif (isset($customAgendas[$short]) && trim((string) $customAgendas[$short]) !== '') {
                    $slot['agenda'] = trim((string) $customAgendas[$short]);
                }
            }
            unset($slot);
        }

        return $defaultSlots;
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

