<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AdministratorInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One-time invitation used to bootstrap the first administrator.
 *
 * Only the SHA-256 hash of the token is persisted. The raw token exists only
 * in memory while the invitation e-mail is being sent.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $token_hash
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AdministratorInvitation extends Model
{
    /** @use HasFactory<AdministratorInvitationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'token_hash',
        'expires_at',
        'consumed_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()
            ->where('token_hash', self::hashToken($token))
            ->first();
    }

    public function isUsableAt(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->consumed_at === null
            && $this->revoked_at === null
            && $this->expires_at->greaterThan($moment);
    }
}
