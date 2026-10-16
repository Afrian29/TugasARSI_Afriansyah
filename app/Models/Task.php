<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'parent_id',
        'order',
        'name',
        'description',
        'start_input',
        'duration',
        'priority',
        'percent_complete',
        'task_type',
        'effective_start',
        'effective_end',
        'early_start',
        'early_finish',
        'late_start',
        'late_finish',
        'slack',
        'is_critical',
    ];

    protected $casts = [
        'order' => 'integer',
        'duration' => 'integer',
        'percent_complete' => 'integer',
        'slack' => 'integer',
        'is_critical' => 'boolean',
        'start_input' => 'date',
        'effective_start' => 'date',
        'effective_end' => 'date',
        'early_start' => 'date',
        'early_finish' => 'date',
        'late_start' => 'date',
        'late_finish' => 'date',
    ];

    protected $hidden = [
        'project_id',
        'parent_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /** @return HasMany<Dependency, $this> */
    public function dependencies(): HasMany
    {
        return $this->hasMany(Dependency::class, 'predecessor_id');
    }

    /** @return HasMany<Dependency, $this> */
    public function dependents(): HasMany
    {
        return $this->hasMany(Dependency::class, 'successor_id');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * CPM-2: scope of the tasks whose slack is zero — the critical path.
     *
     * Every computed value (effective dates, LS/LF, slack, critical flag) is
     * persisted by the recalculator, so this scope is a pure DB read; the
     * caller renders the path by detail (icon + thicker border), never by a
     * second, JS-calculated pass.
     *
     * @return Builder<static>
     */
    public function scopeCriticalQuery(?Project $project = null): Builder
    {
        $query = static::query()
            ->where('slack', 0)
            ->orderBy('order');

        if ($project !== null) {
            $query->where('project_id', $project->id);
        }

        return $query;
    }
}
