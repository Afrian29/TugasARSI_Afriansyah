<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dependency extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'predecessor_id',
        'successor_id',
        'type',
        'lag',
    ];

    protected $casts = [
        'lag' => 'integer',
    ];

    protected $hidden = [
        'project_id',
        'predecessor_id',
        'successor_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }
}
