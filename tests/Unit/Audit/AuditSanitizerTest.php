<?php

use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\Audit\AuditSanitizer;
use Illuminate\Config\Repository;

beforeEach(function () {
    $this->sanitizer = new AuditSanitizer(new Repository([
        'audit' => [
            'fields' => [
                User::class => [
                    AuditEventType::UserUpdated->value => [
                        'name',
                        'email',
                        'password',
                        'profile',
                        'headers',
                        'payload',
                    ],
                ],
            ],
            'metadata' => [
                '*' => ['source', 'context', 'headers', 'reason', 'correlation_id', 'role', 'permission'],
            ],
            'metadata_values' => [
                'source' => ['http', 'console', 'test'],
                'origin' => ['user', 'system', 'automated'],
            ],
            'metadata_identifier_pattern' => '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/',
            'metadata_technical_identifier_pattern' => '/^[A-Za-z0-9][A-Za-z0-9_.:\\-]{0,199}$/',
            'reason_max_length' => 60,
            'reasons' => [
                'Motif autorisé',
                'Création d’un utilisateur',
            ],
            'sensitive_keys' => [
                'password',
                'remember_token',
                'access_token',
                'api_key',
                'private_key',
                'authorization',
                'cookie',
                'session',
            ],
            'sensitive_patterns' => [
                '/password|passwd|passphrase/i',
                '/token|secret|api_?key|private_?key|authorization|cookie|session|credential/i',
                '/iban|bic|bank_?account|rib|account_?number|routing_?number|nir|ssn|social_?security/i',
                '/medical|health|diagnosis|sick_?leave|card_?number|cvv|cvc/i',
            ],
            'max_depth' => 4,
        ],
    ]));
});

test('applies the model event allow-list and drops unknown fields', function () {
    $result = $this->sanitizer->sanitizeChanges(
        User::class,
        AuditEventType::UserUpdated,
        ['name' => 'Old name', 'salary' => 1000],
        ['name' => 'New name', 'salary' => 2000],
    );

    expect($result)->toBe([
        'old' => ['name' => 'Old name'],
        'attributes' => ['name' => 'New name'],
    ]);
});

test('applies the sensitive blacklist even when a field is allow-listed', function () {
    $result = $this->sanitizer->sanitizeChanges(
        User::class,
        AuditEventType::UserUpdated,
        ['name' => 'Old', 'password' => 'old-secret'],
        ['name' => 'New', 'password' => 'new-secret', 'remember_token' => 'token'],
    );

    expect($result)->toBe([
        'old' => ['name' => 'Old'],
        'attributes' => ['name' => 'New'],
    ])
        ->and(json_encode($result))->not->toContain('secret')
        ->and(json_encode($result))->not->toContain('token');
});

test('drops sensitive keys regardless of case and naming convention', function (string $key) {
    $result = $this->sanitizer->sanitizeValues(
        [$key => 'sensitive-value', 'name' => 'allowed'],
        [$key, 'name'],
    );

    expect($result)->toBe(['name' => 'allowed']);
})->with([
    'password' => 'password',
    'upper password' => 'PASSWORD',
    'mixed case password' => 'PassWord',
    'password confirmation' => 'password_confirmation',
    'current password' => 'current_password',
    'remember token' => 'remember_token',
    'access token' => 'access_token',
    'refresh token' => 'refresh_token',
    'api key' => 'api_key',
    'secret' => 'secret',
    'private key' => 'private_key',
    'authorization header' => 'authorization',
    'cookie header' => 'cookie',
    'session' => 'session',
    'camel case token' => 'accessToken',
    'kebab case token' => 'access-token',
    'dotted token' => 'access.token',
    'unknown secret' => 'mystery_secret',
]);

test('filters nested arrays and preserves non-sensitive nested values', function () {
    $result = $this->sanitizer->sanitizeValues([
        'profile' => [
            'name' => 'Alice',
            'password' => 'secret',
            'nested' => [
                'access_token' => 'token',
                'label' => 'ok',
            ],
        ],
        'headers' => [
            'Authorization' => 'Bearer secret',
            'Accept' => 'application/json',
        ],
    ], ['profile', 'headers']);

    expect($result)->toBe([
        'profile' => [
            'name' => 'Alice',
            'nested' => [
                'label' => 'ok',
            ],
        ],
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);
});

test('filters metadata through its own allow-list', function () {
    $result = $this->sanitizer->sanitizeMetadata(
        AuditEventType::UserUpdated,
        [
            'source' => 'http',
            'context' => [
                'password' => 'secret',
                'label' => 'allowed',
            ],
            'headers' => [
                'Authorization' => 'Bearer secret',
                'Accept' => 'application/json',
            ],
            'unknown' => 'dropped',
        ],
    );

    expect($result)->toBe([
        'source' => 'http',
        'context' => ['label' => 'allowed'],
        'headers' => ['Accept' => 'application/json'],
    ]);
});

test('accepts only allow-listed reason values', function () {
    expect($this->sanitizer->sanitizeMetadata(
        AuditEventType::UserUpdated,
        ['reason' => 'Motif autorisé'],
    ))->toBe(['reason' => 'Motif autorisé'])
        ->and($this->sanitizer->sanitizeMetadata(
            AuditEventType::UserUpdated,
            ['reason' => 'Motif inconnu'],
        ))->toBe([]);
});

test('rejects long, structured or secret reason values without leaking them', function (string $reason) {
    $result = $this->sanitizer->sanitizeMetadata(
        AuditEventType::UserUpdated,
        ['reason' => $reason],
    );

    expect($result)->toBe([])
        ->and(json_encode($result))->not->toContain('password')
        ->and(json_encode($result))->not->toContain('token')
        ->and(json_encode($result))->not->toContain('IBAN');
})->with([
    'too long' => str_repeat('a', 61),
    'token' => 'Motif token=abc123',
    'password' => 'Motif password=secret',
    'bank data' => 'Motif IBAN FR7630006000011234567890189',
]);

test('rejects structured reason values', function () {
    expect($this->sanitizer->sanitizeMetadata(
        AuditEventType::UserUpdated,
        ['reason' => ['password' => 'secret']],
    ))->toBe([]);
});

test('validates technical identifiers and controlled metadata values', function () {
    expect($this->sanitizer->sanitizeMetadata(
        AuditEventType::UserUpdated,
        [
            'correlation_id' => '4b7f1e7a-9d8e-4d8d-8a4f-2b1f4a7c9e11',
            'role' => 'administrator',
            'permission' => 'administration.panel.access',
            'source' => 'http',
        ],
    ))->toBe([
        'source' => 'http',
        'correlation_id' => '4b7f1e7a-9d8e-4d8d-8a4f-2b1f4a7c9e11',
        'role' => 'administrator',
        'permission' => 'administration.panel.access',
    ])
        ->and($this->sanitizer->sanitizeMetadata(
            AuditEventType::UserUpdated,
            [
                'correlation_id' => 'invalid correlation with spaces',
                'role' => 'role with spaces',
                'permission' => 'permission/with/slash',
                'source' => 'unknown-source',
            ],
        ))->toBe([]);
});

test('filters JSON serialisable objects and dates', function () {
    $payload = new class implements JsonSerializable
    {
        /**
         * @return array<string, mixed>
         */
        public function jsonSerialize(): array
        {
            return [
                'name' => 'Alice',
                'password' => 'secret',
                'created_at' => new DateTimeImmutable('2026-11-08T12:00:00+00:00'),
            ];
        }
    };

    $result = $this->sanitizer->sanitizeValues(['payload' => $payload], ['payload']);

    expect($result)->toBe([
        'payload' => [
            'name' => 'Alice',
            'created_at' => '2026-11-08T12:00:00+00:00',
        ],
    ]);
});

test('filters arrays containing serialisable objects', function () {
    $result = $this->sanitizer->sanitizeValues([
        'payload' => [
            new class implements JsonSerializable
            {
                /**
                 * @return array<string, mixed>
                 */
                public function jsonSerialize(): array
                {
                    return ['name' => 'Alice', 'password' => 'secret'];
                }
            },
            new class implements JsonSerializable
            {
                /**
                 * @return array<string, mixed>
                 */
                public function jsonSerialize(): array
                {
                    return ['name' => 'Bob', 'access_token' => 'token'];
                }
            },
        ],
    ], ['payload']);

    expect($result)->toBe([
        'payload' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
    ]);
});

test('rejects unsupported objects instead of serialising them', function () {
    expect(fn () => $this->sanitizer->sanitizeValues(
        ['payload' => new stdClass],
        ['payload'],
    ))->toThrow(InvalidArgumentException::class);
});

test('rejects values nested beyond the configured depth', function () {
    expect(fn () => $this->sanitizer->sanitizeValues([
        'payload' => ['a' => ['b' => ['c' => ['d' => ['e' => 'too deep']]]]],
    ], ['payload']))->toThrow(InvalidArgumentException::class);
});
