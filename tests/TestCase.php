<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert server-side behaviour; compiled frontend assets
        // are irrelevant and may not exist (e.g. before `npm run build`).
        $this->withoutVite();
    }
}
