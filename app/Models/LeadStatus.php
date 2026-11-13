<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class LeadStatus extends Model
{
    use HasFactory, BelongsToCompany;

    private const SYSTEM_STAGE_NAMES = ['new lead', 'fresh lead', 'proposal sent', 'loss', 'won'];

    protected $fillable = [
        'company_id', 'name', 'color', 'is_default', 'order'
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public static function ensureNewLead(int $companyId): self
    {
        $status = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['new lead'])
                    ->orWhereRaw('LOWER(name) = ?', ['fresh lead']);
            })
            ->first();

        if ($status) {
            $status->forceFill([
                'name' => 'New Lead',
                'is_default' => true,
                'order' => 0,
            ])->save();

            return $status;
        }

        return self::create([
            'company_id' => $companyId,
            'name' => 'New Lead',
            'color' => '#3498db',
            'is_default' => true,
            'order' => 0,
        ]);
    }

    public static function ensureWon(int $companyId): self
    {
        self::ensureProposalSent($companyId);

        $won = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['won'])
            ->first();

        if ($won) {
            $won->forceFill([
                'name' => 'Won',
                'is_default' => true,
            ])->save();

            return $won;
        }

        $maxOrder = (int) self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->max('order');

        return self::create([
            'company_id' => $companyId,
            'name' => 'Won',
            'color' => '#2ecc71',
            'is_default' => true,
            'order' => $maxOrder + 1,
        ]);
    }

    public static function ensureProposalSent(int $companyId): self
    {
        $status = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['proposal sent'])
            ->first();

        if ($status) {
            $status->forceFill([
                'name' => 'Proposal Sent',
                'is_default' => true,
            ])->save();

            return $status;
        }

        $maxOrder = (int) self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->max('order');

        return self::create([
            'company_id' => $companyId,
            'name' => 'Proposal Sent',
            'color' => '#0ea5e9',
            'is_default' => true,
            'order' => $maxOrder + 1,
        ]);
    }

    public static function ensureLoss(int $companyId): self
    {
        $status = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['loss'])
            ->first();

        if ($status) {
            $status->forceFill([
                'name' => 'Loss',
                'is_default' => true,
            ])->save();

            return $status;
        }

        $maxOrder = (int) self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->max('order');

        return self::create([
            'company_id' => $companyId,
            'name' => 'Loss',
            'color' => '#ef4444',
            'is_default' => true,
            'order' => $maxOrder + 1,
        ]);
    }

    public static function normalizeSystemStages(int $companyId): void
    {
        $statuses = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();

        $statuses->each(function (self $status) {
            $name = strtolower(trim((string) $status->name));
            $shouldBeDefault = in_array($name, self::SYSTEM_STAGE_NAMES, true);

            if ((bool) $status->is_default !== $shouldBeDefault) {
                $status->forceFill(['is_default' => $shouldBeDefault])->save();
            }
        });

        $proposalSent = $statuses->first(fn (self $s) => strtolower(trim((string) $s->name)) === 'proposal sent');
        $won = $statuses->first(fn (self $s) => strtolower(trim((string) $s->name)) === 'won');
        $loss = $statuses->first(fn (self $s) => strtolower(trim((string) $s->name)) === 'loss');

        // 1. Ensure Proposal Sent comes before Won
        if ($proposalSent && $won && (int) $proposalSent->order >= (int) $won->order) {
            $pOrder = (int) $proposalSent->order;
            $wOrder = (int) $won->order;
            if ($pOrder === $wOrder) {
                $wOrder = $pOrder + 1;
            }
            $proposalSent->forceFill(['order' => $wOrder])->save();
            $won->forceFill(['order' => $pOrder])->save();
        }

        // Re-fetch after potential swap
        $statuses = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();

        $won = $statuses->first(fn (self $s) => strtolower(trim((string) $s->name)) === 'won');
        $loss = $statuses->first(fn (self $s) => strtolower(trim((string) $s->name)) === 'loss');

        // 2. Ensure Loss comes after Won
        if ($won && $loss && (int) $loss->order <= (int) $won->order) {
            $maxOrder = (int) $statuses->max('order');
            $loss->forceFill(['order' => $maxOrder + 1])->save();
        }
    }

    public function isSystemStage(): bool
    {
        $name = strtolower(trim((string) $this->name));

        return $this->is_default || in_array($name, self::SYSTEM_STAGE_NAMES, true);
    }
}
