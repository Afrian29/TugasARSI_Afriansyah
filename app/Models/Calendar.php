<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Calendar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'working_days',
        'weekend_days',
        'working_hours_per_day',
        'holidays',
    ];

    protected $casts = [
        'working_days' => 'array',
        'weekend_days' => 'array',
        'working_hours_per_day' => 'integer',
        'holidays' => 'array',
    ];

    protected $hidden = [
        'project_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Holiday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
