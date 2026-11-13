<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class ProjectStage extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'color', 'key', 'order', 'is_default'
    ];

    public function projects()
    {
        return $this->hasMany(Project::class, 'current_stage', 'key');
    }

    public static function ensureNewProject(int $companyId): self
    {
        $stage = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(`key`) = ?', ['new-project'])
                    ->orWhereRaw('LOWER(name) = ?', ['new project']);
            })
            ->orderByRaw("CASE WHEN LOWER(`key`) = 'new-project' THEN 0 WHEN LOWER(name) = 'new project' THEN 1 ELSE 2 END")
            ->first();

        if ($stage) {
            $stage->forceFill([
                'name' => 'New Project',
                'key' => 'new-project',
                'is_default' => true,
                'order' => 0,
            ])->save();

            return $stage;
        }

        return self::create([
            'company_id' => $companyId,
            'name' => 'New Project',
            'color' => '#4f46e5',
            'key' => 'new-project',
            'order' => 0,
            'is_default' => true,
        ]);
    }

    public static function removeLegacyDrawingStages(int $companyId): void
    {
        self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(`key`) = ?', ['drawing'])
                    ->orWhereRaw('LOWER(name) = ?', ['drawing']);
            })
            ->delete();
    }

    public static function ensureCompleted(int $companyId): self
    {
        $stage = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(`key`) = ?', ['completed'])
                    ->orWhereRaw('LOWER(name) = ?', ['completed']);
            })
            ->orderByRaw("CASE WHEN LOWER(`key`) = 'completed' THEN 0 ELSE 1 END")
            ->first();

        if ($stage) {
            $stage->forceFill([
                'name' => 'Completed',
                'key' => 'completed',
                'is_default' => true,
            ])->save();

            return $stage;
        }

        $maxOrder = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->max('order') ?? 0;

        return self::create([
            'company_id' => $companyId,
            'name' => 'Completed',
            'color' => '#10b981',
            'key' => 'completed',
            'order' => $maxOrder + 1,
            'is_default' => true,
        ]);
    }

    public static function ensureInProgress(int $companyId): self
    {
        $stage = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(`key`) = ?', ['in-progress'])
                    ->orWhereRaw('LOWER(name) = ?', ['in progress']);
            })
            ->orderByRaw("CASE WHEN LOWER(`key`) = 'in-progress' THEN 0 ELSE 1 END")
            ->first();

        if ($stage) {
            $stage->forceFill([
                'name' => 'In Progress',
                'key' => 'in-progress',
                'is_default' => true,
            ])->save();

            return $stage;
        }

        return self::create([
            'company_id' => $companyId,
            'name' => 'In Progress',
            'color' => '#0ea5e9',
            'key' => 'in-progress',
            'order' => 1,
            'is_default' => true,
        ]);
    }

    public static function ensureDefaults(int $companyId): void
    {
        static::ensureNewProject($companyId);
        static::removeLegacyDrawingStages($companyId);
        static::ensureInProgress($companyId);
        $completed = static::ensureCompleted($companyId);

        $completedOrder = (self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(`key`) != ?', ['completed'])
            ->max('order') ?? 1) + 1;

        if ((int) $completed->order !== (int) $completedOrder) {
            $completed->forceFill(['order' => $completedOrder])->save();
        }
    }

    public function isSystemStage(): bool
    {
        return $this->is_default
            || strtolower((string) $this->key) === 'new-project'
            || strtolower((string) $this->name) === 'new project'
            || strtolower((string) $this->key) === 'in-progress'
            || strtolower((string) $this->name) === 'in progress'
            || strtolower((string) $this->key) === 'completed'
            || strtolower((string) $this->name) === 'completed';
    }

    public function isReorderLocked(): bool
    {
        return $this->isSystemStage();
    }
}
