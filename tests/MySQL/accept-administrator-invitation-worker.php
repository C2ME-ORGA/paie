<?php

/**
 * Isolated worker for the MySQL concurrency test.
 *
 * The database, token and password are received on stdin, never on the command
 * line, so they do not appear in the process list. The worker prints only
 * READY and a result without any secret.
 */

use App\Actions\Administration\AcceptAdministratorInvitation;
use App\Exceptions\AdministratorInvitationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$configLine = fgets(STDIN);

if ($configLine === false) {
    exit(2);
}

$config = json_decode($configLine, true);

if (! is_array($config)) {
    exit(2);
}

$database = (string) ($config['database'] ?? '');
$token = (string) ($config['token'] ?? '');
$password = (string) ($config['password'] ?? '');

if ($database !== 'batipaie_bp191_concurrency_test' || $token === '' || $password === '') {
    exit(2);
}

putenv('DB_DATABASE='.$database);
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDatabaseName() !== $database) {
    fwrite(STDERR, "Unexpected database\n");
    exit(2);
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);

if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}

try {
    $user = app(AcceptAdministratorInvitation::class)->handle($token, $password);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'user_id' => $user->getKey(),
    ], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'class' => $exception::class,
        'reason' => $exception instanceof AdministratorInvitationException
            ? $exception->reason
            : null,
    ], JSON_THROW_ON_ERROR)."\n");
}

exit(0);
