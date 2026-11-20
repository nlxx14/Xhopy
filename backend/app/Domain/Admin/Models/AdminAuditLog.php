<?php

namespace App\Domain\Admin\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail of administrative and staff back-office actions.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $actor_role
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string $summary
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property CarbonInterface $occurred_at
 * @property-read User|null $actor
 */
class AdminAuditLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'target_type',
        'target_id',
        'summary',
        'metadata',
        'ip_address',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
