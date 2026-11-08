<?php

/**
 * Real MySQL/InnoDB concurrency harness for BP-191.
 *
 * It uses two independent PHP child processes, synchronized through pipes.
 * The target database is guarded by an exact name. Run only against the
 * dedicated test database; the harness applies migrate:fresh.
 */

use App\Exceptions\AdministratorInvitationException;
use App\Models\AdministratorInvitation;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const TEST_DATABASE = 'batipaie_bp191_concurrency_test';
const DEFAULT_ITERATIONS = 5;

$database = getenv('BP191_MYSQL_TEST_DATABASE');
$database = is_string($database) && $database !== '' ? $database : TEST_DATABASE;

if ($database !== TEST_DATABASE) {
    fwrite(STDERR, 'Refusing database other than '.TEST_DATABASE."\n");
    exit(2);
}

$iterations = (int) (getenv('BP191_MYSQL_ITERATIONS') ?: DEFAULT_ITERATIONS);
$iterations = max(1, min($iterations, 20));

putenv('DB_DATABASE='.$database);
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDatabaseName() !== $database) {
    fwrite(STDERR, "Unexpected database after bootstrap\n");
    exit(2);
}

$password = 'Aa1!'.bin2hex(random_bytes(12));

/**
 * Reset only the dedicated test database.
 */
function resetTestDatabase(): void
{
    $migrationStatus = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrationStatus !== 0) {
        throw new RuntimeException('migrate:fresh failed.');
    }

    $seedStatus = Artisan::call('db:seed', [
        '--class' => RbacSeeder::class,
        '--force' => true,
    ]);

    if ($seedStatus !== 0) {
        throw new RuntimeException('RBAC seed failed.');
    }

    if (User::query()->role('administrator', 'web')->exists()) {
        throw new RuntimeException('An administrator exists after the test reset.');
    }
}

function invitationToken(): string
{
    return bin2hex(random_bytes(32));
}

function createInvitation(string $token, string $email): AdministratorInvitation
{
    return AdministratorInvitation::query()->create([
        'name' => 'Concurrency Test',
        'email' => $email,
        'token_hash' => AdministratorInvitation::hashToken($token),
        'expires_at' => now()->addHour(),
    ]);
}

/**
 * @param  list<array{token: string, email: string}>  $participants
 * @return list<array{ok: bool, class?: string, reason?: string|null, user_id?: int|string}>
 */
function runWorkers(string $database, array $participants, string $password): array
{
    $worker = __DIR__.'/accept-administrator-invitation-worker.php';
    $projectRoot = dirname(__DIR__, 2);
    $processes = [];
    $results = [];

    foreach ($participants as $participant) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, $worker],
            $descriptors,
            $pipes,
            $projectRoot,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start a worker process.');
        }

        fwrite($pipes[0], json_encode([
            'database' => $database,
            'token' => $participant['token'],
            'password' => $password,
        ], JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);

        $ready = fgets($pipes[1]);

        if (trim((string) $ready) !== 'READY') {
            $stderr = stream_get_contents($pipes[2]);
            proc_close($process);
            throw new RuntimeException('Worker did not become ready: '.trim((string) $stderr));
        }

        $processes[] = [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    // Both workers are booted and blocked on stdin. Release them together.
    foreach ($processes as $item) {
        fwrite($item['pipes'][0], "GO\n");
        fflush($item['pipes'][0]);
        fclose($item['pipes'][0]);
    }

    foreach ($processes as $index => $item) {
        $stdout = stream_get_contents($item['pipes'][1]);
        $stderr = stream_get_contents($item['pipes'][2]);
        fclose($item['pipes'][1]);
        fclose($item['pipes'][2]);

        $exitCode = proc_close($item['process']);

        if ($exitCode !== 0) {
            throw new RuntimeException('Worker '.($index + 1).' failed: '.trim((string) $stderr));
        }

        $decoded = json_decode(trim((string) $stdout), true);

        if (! is_array($decoded) || ! is_bool($decoded['ok'] ?? null)) {
            throw new RuntimeException('Worker '.($index + 1).' returned invalid output.');
        }

        $result = ['ok' => $decoded['ok']];

        if ($decoded['ok'] === false) {
            $class = $decoded['class'] ?? null;
            $reason = $decoded['reason'] ?? null;

            if (! is_string($class) || ($reason !== null && ! is_string($reason))) {
                throw new RuntimeException('Worker '.($index + 1).' returned an invalid refusal.');
            }

            $result['class'] = $class;
            $result['reason'] = $reason;
        } else {
            $userId = $decoded['user_id'] ?? null;

            if (! is_int($userId) && ! is_string($userId)) {
                throw new RuntimeException('Worker '.($index + 1).' returned an invalid account identifier.');
            }

            $result['user_id'] = $userId;
        }

        $results[] = $result;
    }

    return $results;
}

/**
 * @param  list<array{ok: bool, class?: string, reason?: string|null, user_id?: int|string}>  $results
 */
function assertScenario(array $results, string $scenario, int $iteration): void
{
    $successes = array_values(array_filter($results, static fn (array $result): bool => $result['ok'] === true));
    $failures = array_values(array_filter($results, static fn (array $result): bool => $result['ok'] === false));

    $users = User::query()->count();
    $administrators = User::query()->role('administrator', 'web')->count();
    $consumed = AdministratorInvitation::query()->whereNotNull('consumed_at')->count();
    $openTransactions = (int) DB::selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.innodb_trx'
    )->total;

    if (count($successes) !== 1) {
        throw new RuntimeException($scenario.' #'.$iteration.': expected one success, got '.count($successes).'.');
    }

    if (count($failures) !== 1) {
        throw new RuntimeException($scenario.' #'.$iteration.': expected one failure, got '.count($failures).'.');
    }

    if (($failures[0]['class'] ?? null) !== AdministratorInvitationException::class) {
        throw new RuntimeException($scenario.' #'.$iteration.': failure was not a controlled invitation refusal.');
    }

    $allowedReasons = $scenario === 'distinct'
        ? ['administrator_exists']
        : ['invalid_invitation', 'administrator_exists'];

    if (! in_array($failures[0]['reason'] ?? null, $allowedReasons, true)) {
        throw new RuntimeException($scenario.' #'.$iteration.': unexpected refusal reason.');
    }

    if ($users !== 1 || $administrators !== 1) {
        throw new RuntimeException($scenario.' #'.$iteration.': expected exactly one administrator.');
    }

    if ($consumed !== 1) {
        throw new RuntimeException($scenario.' #'.$iteration.': expected exactly one consumed invitation.');
    }

    if ($openTransactions !== 0) {
        throw new RuntimeException($scenario.' #'.$iteration.': transactions were left open.');
    }

    fwrite(STDOUT, sprintf(
        "%s #%d: success=1 failure=1 administrators=1 consumed=1 open_transactions=0\n",
        $scenario,
        $iteration,
    ));
}

try {
    for ($iteration = 1; $iteration <= $iterations; $iteration++) {
        resetTestDatabase();

        $firstToken = invitationToken();
        $secondToken = invitationToken();

        createInvitation($firstToken, 'first@example.test');

        if ($firstToken === $secondToken) {
            throw new RuntimeException('Generated invitation tokens are not distinct.');
        }

        createInvitation($secondToken, 'second@example.test');

        $results = runWorkers($database, [
            ['token' => $firstToken, 'email' => 'first@example.test'],
            ['token' => $secondToken, 'email' => 'second@example.test'],
        ], $password);

        assertScenario($results, 'distinct', $iteration);
    }

    for ($iteration = 1; $iteration <= $iterations; $iteration++) {
        resetTestDatabase();

        $token = invitationToken();
        createInvitation($token, 'same@example.test');

        $results = runWorkers($database, [
            ['token' => $token, 'email' => 'same@example.test'],
            ['token' => $token, 'email' => 'same@example.test'],
        ], $password);

        assertScenario($results, 'same', $iteration);
    }

    fwrite(STDOUT, "MySQL concurrency validation passed.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'MySQL concurrency validation failed: '.$exception->getMessage()."\n");
    exit(1);
}
