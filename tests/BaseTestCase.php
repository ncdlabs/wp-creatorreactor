<?php

declare(strict_types=1);

namespace CreatorReactor\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        \Brain\Monkey\Functions\when('wp_get_referer')->justReturn('https://example.com/');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
