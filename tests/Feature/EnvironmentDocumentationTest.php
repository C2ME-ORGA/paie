<?php

/**
 * Variables lues par `config/*.php` mais volontairement absentes des modèles
 * d'environnement : options des intégrations non activées (Redis, SQS, Slack,
 * AWS, DynamoDB, Memcached, Beanstalkd, PaperTrail, Postmark, Resend) ou
 * réglages avancés documentés directement dans les fichiers de configuration.
 *
 * Toute nouvelle variable ajoutée à `config/` doit être soit documentée dans
 * `.env.example`, soit ajoutée explicitement à cette liste.
 */
const OPTIONAL_FRAMEWORK_VARIABLES = [
    'AUTH_GUARD',
    'AUTH_MODEL',
    'AUTH_PASSWORD_BROKER',
    'AUTH_PASSWORD_RESET_TOKEN_TABLE',
    'AUTH_PASSWORD_TIMEOUT',
    'AWS_ENDPOINT',
    'AWS_URL',
    'BEANSTALKD_QUEUE',
    'BEANSTALKD_QUEUE_HOST',
    'BEANSTALKD_QUEUE_RETRY_AFTER',
    'CACHE_STORAGE_DISK',
    'CACHE_STORAGE_PATH',
    'DB_CACHE_CONNECTION',
    'DB_CACHE_LOCK_CONNECTION',
    'DB_CACHE_LOCK_TABLE',
    'DB_CACHE_TABLE',
    'DB_CHARSET',
    'DB_COLLATION',
    'DB_ENCRYPT',
    'DB_FOREIGN_KEYS',
    'DB_QUEUE',
    'DB_QUEUE_CONNECTION',
    'DB_QUEUE_RETRY_AFTER',
    'DB_QUEUE_TABLE',
    'DB_SOCKET',
    'DB_SSLMODE',
    'DB_TRUST_SERVER_CERTIFICATE',
    'DB_URL',
    'DYNAMODB_CACHE_TABLE',
    'DYNAMODB_ENDPOINT',
    'LOG_DAILY_DAYS',
    'LOG_DEPRECATIONS_TRACE',
    'LOG_PAPERTRAIL_HANDLER',
    'LOG_SLACK_EMOJI',
    'LOG_SLACK_USERNAME',
    'LOG_SLACK_WEBHOOK_URL',
    'LOG_STDERR_FORMATTER',
    'LOG_SYSLOG_FACILITY',
    'MAIL_EHLO_DOMAIN',
    'MAIL_LOG_CHANNEL',
    'MAIL_SENDMAIL_PATH',
    'MAIL_URL',
    'MEMCACHED_PASSWORD',
    'MEMCACHED_PERSISTENT_ID',
    'MEMCACHED_PORT',
    'MEMCACHED_USERNAME',
    'MYSQL_ATTR_SSL_CA',
    'PAPERTRAIL_PORT',
    'PAPERTRAIL_URL',
    'POSTMARK_API_KEY',
    'POSTMARK_MESSAGE_STREAM_ID',
    'QUEUE_FAILED_DRIVER',
    'REDIS_BACKOFF_ALGORITHM',
    'REDIS_BACKOFF_BASE',
    'REDIS_BACKOFF_CAP',
    'REDIS_CACHE_CONNECTION',
    'REDIS_CACHE_DB',
    'REDIS_CACHE_LOCK_CONNECTION',
    'REDIS_CLUSTER',
    'REDIS_DB',
    'REDIS_MAX_RETRIES',
    'REDIS_PERSISTENT',
    'REDIS_PREFIX',
    'REDIS_QUEUE',
    'REDIS_QUEUE_CONNECTION',
    'REDIS_QUEUE_RETRY_AFTER',
    'REDIS_URL',
    'REDIS_USERNAME',
    'RESEND_API_KEY',
    'SESSION_CONNECTION',
    'SESSION_EXPIRE_ON_CLOSE',
    'SESSION_PARTITIONED_COOKIE',
    'SESSION_STORE',
    'SESSION_TABLE',
    'SLACK_BOT_USER_DEFAULT_CHANNEL',
    'SLACK_BOT_USER_OAUTH_TOKEN',
    'SQS_PREFIX',
    'SQS_QUEUE',
    'SQS_SUFFIX',
];

/**
 * @return list<string>
 */
function batipaieDocumentedEnvironmentKeys(): array
{
    $content = (string) file_get_contents(base_path('.env.example'));

    preg_match_all('/^\s*#?\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $content, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * @return list<string>
 */
function batipaieConfigurationEnvironmentKeys(): array
{
    $keys = [];

    foreach (glob(config_path('*.php')) ?: [] as $file) {
        $content = (string) file_get_contents($file);

        preg_match_all('/env\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $content, $matches);

        foreach ($matches[1] as $key) {
            $keys[] = $key;
        }
    }

    return array_values(array_unique($keys));
}

test('every environment variable used by the configuration is documented', function () {
    $missing = array_values(array_diff(
        batipaieConfigurationEnvironmentKeys(),
        batipaieDocumentedEnvironmentKeys(),
        OPTIONAL_FRAMEWORK_VARIABLES,
    ));

    sort($missing);

    expect($missing)->toBe([]);
});

test('the environment files are excluded from version control', function () {
    $gitignore = (string) file_get_contents(base_path('.gitignore'));

    expect($gitignore)->toMatch('/^\.env$/m')
        ->and($gitignore)->toMatch('/^\.env\.\*$/m')
        ->and($gitignore)->toMatch('/^!\.env\.example$/m')
        ->and($gitignore)->toMatch('/^!\.env\.production\.example$/m');
});

test('the environment templates do not contain secrets', function () {
    $secretPatterns = [
        '/AKIA[0-9A-Z]{16}/',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        '/ghp_[A-Za-z0-9]{20,}/',
        '/github_pat_[A-Za-z0-9_]{20,}/',
        '/sk_(live|test)_[A-Za-z0-9]+/',
        '/xox[baprs]-[A-Za-z0-9-]+/',
    ];

    foreach ([base_path('.env.example'), base_path('.env.production.example')] as $template) {
        $content = (string) file_get_contents($template);

        expect($content)->not->toBe('');

        foreach ($secretPatterns as $pattern) {
            expect($content)->not->toMatch($pattern);
        }
    }
});

test('the local template does not propose a database superuser account', function () {
    $content = (string) file_get_contents(base_path('.env.example'));

    expect($content)->toMatch('/^DB_USERNAME=batipaie$/m')
        ->and($content)->not->toMatch('/^DB_USERNAME=root$/m');
});

test('the production template documents the required secure settings', function () {
    $content = (string) file_get_contents(base_path('.env.production.example'));

    expect($content)
        ->toMatch('/^APP_ENV=production$/m')
        ->toMatch('/^APP_DEBUG=false$/m')
        ->toMatch('/^APP_KEY=$/m')
        ->toMatch('/^DB_USERNAME=batipaie$/m')
        ->toMatch('/^SESSION_DRIVER=database$/m')
        ->toMatch('/^SESSION_SECURE_COOKIE=true$/m')
        ->toMatch('/^SESSION_HTTP_ONLY=true$/m')
        ->toMatch('/^LOG_LEVEL=warning$/m')
        ->toMatch('/^MAIL_MAILER=smtp$/m');
});
