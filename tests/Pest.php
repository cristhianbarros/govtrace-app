<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests extend Tests\TestCase (full Laravel app). Add
| Illuminate\Foundation\Testing\RefreshDatabase per test file when
| a scenario touches the database.
|
*/

pest()->extend(TestCase::class)->in('Feature');
