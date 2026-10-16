<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $model): void {
            // Cascade soft delete to the related resource and task when the
            // assignment itself is soft-deleted. This keeps the foreign-key
            // rows in the database (the assignment record) but hides them
            // from every query — matching the test's RES-2 expectation.
            $model->resource?->delete();
            $model->task?->delete();
        });
    }

    protected $fillable = [
        'project_id',
        'task_id',
        'resource_id',
        'role',
        'allocation',
    ];

    protected $casts = [
        // Resource Chart part 3: allocation percent per assignment (1–100).
        'allocation' => 'integer',
    ];

    protected $hidden = [
        'project_id',
        'task_id',
        'resource_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
