<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceStage extends Model
{
    use BelongsToCompany, HasFactory;

    public const FIXED_STAGE_KEYS = [
        'new',
        'open',
        'close',
    ];

    public const DEFAULT_STAGES = [
        [
            'key' => 'new',
            'name' => 'New',
            'color' => '#38bdf8',
            'description' => 'Fresh requests waiting for first action.',
            'mapped_status' => 'open',
            'order' => 0,
            'is_default' => true,
        ],
        [
            'key' => 'open',
            'name' => 'Open',
            'color' => '#6366f1',
            'description' => 'Accepted jobs ready for execution.',
            'mapped_status' => 'open',
            'order' => 1,
            'is_default' => true,
        ],
        [
            'key' => 'blocker',
            'name' => 'Blocker',
            'color' => '#ef4444',
            'description' => 'High-risk cases needing escalation.',
            'mapped_status' => 'open',
            'order' => 2,
            'is_default' => false,
        ],
        [
            'key' => 'in_progress',
            'name' => 'In-Progress',
            'color' => '#f59e0b',
            'description' => 'Technicians are actively working on them.',
            'mapped_status' => 'in_progress',
            'order' => 3,
            'is_default' => false,
        ],
        [
            'key' => 'hold',
            'name' => 'Hold',
            'color' => '#94a3b8',
            'description' => 'Paused until customer or parts are available.',
            'mapped_status' => 'hold',
            'order' => 4,
            'is_default' => false,
        ],
        [
            'key' => 'testing',
            'name' => 'Testing',
            'color' => '#22c55e',
            'description' => 'Fix applied and verification is underway.',
            'mapped_status' => 'answered',
            'order' => 5,
            'is_default' => false,
        ],
        [
            'key' => 'reopen_reschedule',
            'name' => 'Reopen/Reschedule',
            'color' => '#ec4899',
            'description' => 'Follow-up visit or reopened case.',
            'mapped_status' => 'reschedule',
            'order' => 6,
            'is_default' => false,
        ],
        [
            'key' => 'resolved',
            'name' => 'Resolved',
            'color' => '#14b8a6',
            'description' => 'Issue solved and awaiting final closure.',
            'mapped_status' => 'resolved',
            'order' => 7,
            'is_default' => false,
        ],
        [
            'key' => 'close',
            'name' => 'Close',
            'color' => '#0f172a',
            'description' => 'Completed and formally closed.',
            'mapped_status' => 'closed',
            'order' => 8,
            'is_default' => true,
        ],
    ];

    protected $fillable = [
        'company_id',
        'key',
        'name',
        'color',
        'description',
        'mapped_status',
        'order',
        'is_default',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'board_stage', 'key');
    }

    public function isFixedStage(): bool
    {
        return in_array(strtolower((string) $this->key), self::FIXED_STAGE_KEYS, true);
    }

    public function isReorderLocked(): bool
    {
        return $this->isFixedStage();
    }

    public static function ensureDefaults(int $companyId): void
    {
        $existingStages = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get()
            ->keyBy('key');

        $legacyCloseStage = $existingStages->get('done_close');
        if ($legacyCloseStage && ! $existingStages->has('close')) {
            $legacyCloseStage->update([
                'key' => 'close',
                'name' => 'Close',
                'mapped_status' => 'closed',
                'is_default' => true,
            ]);

            $existingStages = self::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderBy('order')
                ->get()
                ->keyBy('key');
        }

        foreach (self::DEFAULT_STAGES as $stage) {
            $existingStage = $existingStages->get($stage['key']);
            $isFixed = in_array($stage['key'], self::FIXED_STAGE_KEYS, true);

            if ($existingStage) {
                if ($isFixed) {
                    $existingStage->update([
                        'name' => $stage['name'],
                        'mapped_status' => $stage['mapped_status'],
                        'is_default' => true,
                    ]);
                }

                continue;
            }

            if (! $isFixed) {
                continue;
            }

            self::create([
                'company_id' => $companyId,
                'key' => $stage['key'],
                'name' => $stage['name'],
                'color' => $stage['color'],
                'description' => $stage['description'],
                'mapped_status' => $stage['mapped_status'],
                'order' => $stage['order'],
                'is_default' => true,
            ]);
        }
    }

    public static function orderedForCompany(int $companyId)
    {
        self::ensureDefaults($companyId);

        return self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get();
    }

    public static function optionsForCompany(int $companyId): array
    {
        return self::orderedForCompany($companyId)
            ->pluck('name', 'key')
            ->all();
    }

    public static function resolveStageKey(
        int $companyId,
        string $status,
        ?string $priority = null,
        ?int $assignedTo = null,
        int $repliesCount = 0,
        ?string $preferredStage = null
    ): string {
        $stages = self::orderedForCompany($companyId)->keyBy('key');

        if ($preferredStage && $stages->has($preferredStage)) {
            return $preferredStage;
        }

        if ($status === 'open') {
            if (in_array($priority, ['urgent', 'high'], true) && $assignedTo && $stages->has('blocker')) {
                return 'blocker';
            }

            if (! $assignedTo && $repliesCount === 0 && $stages->has('new')) {
                return 'new';
            }

            if ($stages->has('open')) {
                return 'open';
            }
        }

        $sameStatusStage = self::fallbackStage($companyId, null, $status);
        if ($sameStatusStage) {
            return $sameStatusStage->key;
        }

        $openStage = self::fallbackStage($companyId, null, 'open');
        if ($openStage) {
            return $openStage->key;
        }

        return (string) self::orderedForCompany($companyId)->first()?->key ?: 'open';
    }

    public static function resolveStatusForStage(int $companyId, string $stageKey): string
    {
        $stage = self::orderedForCompany($companyId)->firstWhere('key', $stageKey);

        return $stage?->mapped_status ?: 'open';
    }

    public static function fallbackStage(int $companyId, ?string $exceptKey = null, ?string $mappedStatus = null): ?self
    {
        $query = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($exceptKey, fn ($builder) => $builder->where('key', '!=', $exceptKey))
            ->when($mappedStatus, fn ($builder) => $builder->where('mapped_status', $mappedStatus))
            ->orderBy('order');

        $stage = $query->first();
        if ($stage) {
            return $stage;
        }

        return self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($exceptKey, fn ($builder) => $builder->where('key', '!=', $exceptKey))
            ->orderBy('order')
            ->first();
    }
}
