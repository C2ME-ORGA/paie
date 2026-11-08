<?php

namespace Database\Factories;

use App\Models\AdministratorInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministratorInvitation>
 */
class AdministratorInvitationFactory extends Factory
{
    /**
     * @var class-string<AdministratorInvitation>
     */
    protected $model = AdministratorInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => AdministratorInvitation::hashToken(bin2hex(random_bytes(32))),
            'expires_at' => now()->addHours(24),
            'consumed_at' => null,
            'revoked_at' => null,
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (): array => [
            'token_hash' => AdministratorInvitation::hashToken($token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'consumed_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now()->subMinute(),
        ]);
    }
}
