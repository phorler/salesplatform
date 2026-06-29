<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level multi-seller isolation for shared-DB tables. When a user is
 * authenticated (web requests), queries are automatically constrained to that
 * user's rows and new records are stamped with their id.
 *
 * IMPORTANT: in console/queue contexts there is no authenticated user, so NO
 * scope is applied — background jobs must constrain by user/account explicitly
 * (e.g. via the owning MarketplaceAccount). This is intentional: jobs act on
 * behalf of a specific seller resolved from the job payload, not the session.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });

        static::addGlobalScope('user', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.user_id', Auth::id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
