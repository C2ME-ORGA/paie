<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tests in the Feature suite are bound to the Laravel base test case so they
| can boot the application. RefreshDatabase is intentionally not applied
| globally: tests opt in explicitly when they need a database.
|
*/

pest()->extend(TestCase::class)->in('Feature');
