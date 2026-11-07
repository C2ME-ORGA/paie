<?php

function batipaieConfigSource(string $file): string
{
    return (string) file_get_contents(config_path($file));
}

test('the application defaults to a production-safe environment', function () {
    $app = batipaieConfigSource('app.php');

    expect($app)
        ->toMatch("/'env'\s*=>\s*env\('APP_ENV',\s*'production'\)/")
        ->toMatch("/'debug'\s*=>\s*\(bool\)\s*env\('APP_DEBUG',\s*false\)/")
        ->toMatch("/'timezone'\s*=>\s*'Europe\/Paris'/")
        ->toMatch("/'cipher'\s*=>\s*'AES-256-CBC'/");
});

test('the session configuration defaults to secure cookie attributes', function () {
    $session = batipaieConfigSource('session.php');

    expect($session)
        ->toMatch("/'http_only'\s*=>\s*env\('SESSION_HTTP_ONLY',\s*true\)/")
        ->toMatch("/'same_site'\s*=>\s*env\('SESSION_SAME_SITE',\s*'lax'\)/")
        ->toMatch("/'secure'\s*=>\s*env\('SESSION_SECURE_COOKIE'\)/");
});

test('the database configuration uses strict mode and utf8mb4', function () {
    $database = batipaieConfigSource('database.php');

    expect($database)
        ->toMatch("/'strict'\s*=>\s*true/")
        ->toMatch("/'charset'\s*=>\s*env\('DB_CHARSET',\s*'utf8mb4'\)/")
        ->toMatch("/'collation'\s*=>\s*env\('DB_COLLATION',\s*'utf8mb4_unicode_ci'\)/");
});

test('the sanctum defaults are consistent with the documented environment values', function () {
    $sanctum = batipaieConfigSource('sanctum.php');
    $example = (string) file_get_contents(base_path('.env.example'));

    $expirationMatches = preg_match("/env\('SANCTUM_EXPIRATION',\s*(-?\d+)\)/", $sanctum, $expiration);
    $prefixMatches = preg_match("/env\('SANCTUM_TOKEN_PREFIX',\s*'([^']*)'\)/", $sanctum, $prefix);

    expect($expirationMatches)->toBe(1)
        ->and($prefixMatches)->toBe(1);

    $expiration = (string) ($expiration[1] ?? '');
    $prefix = (string) ($prefix[1] ?? '');

    // Le modèle ne fixe aucune règle arbitraire : il documente exactement la
    // valeur par défaut définie par la configuration du projet.
    expect($example)
        ->toMatch('/^SANCTUM_EXPIRATION='.preg_quote($expiration, '/').'$/m')
        ->toMatch('/^SANCTUM_TOKEN_PREFIX='.preg_quote($prefix, '/').'$/m');
});
