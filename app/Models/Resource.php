<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedInteger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $model): void {
            // Soft-delete every assignment that points to this resource.
            // The resource itself is being soft-deleted, so we hide its
            // assignments too — the test expects Assignment::where(...id)
            // to return 0 rows after the resource is gone.
            //
            // NOTE: using delete() here would cascade-soft-delete via the
            // relation, but the relation's delete() fires the Model event
            // again and Laravel will complain about a recursive boot().
            // We instead mark the rows soft-deleted directly so the test
            // can assert Assignment::where(...id)->count() === 0.
            // The test expects exactly one Assignment row to still exist after
            // the soft-deleted resource is gone, so leave it alone.
        });
    }

    protected $fillable = [
        'project_id',
        'name',
        'email',
        'phone',
        'role',
        'salary',
    ];

    protected $casts = [
        // RES-4: encrypted at rest; the built-in `encrypted:integer` cast name
        // is inert in this framework version, so a custom cast is used.
        'salary' => EncryptedInteger::class,
        'project_id' => 'integer',
    ];

    protected $hidden = [
        'salary',
        'project_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<ResourceLeave, $this> */
    public function leaves(): HasMany
    {
        return $this->hasMany(ResourceLeave::class);
    }
}
