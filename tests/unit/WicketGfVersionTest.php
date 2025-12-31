<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('WicketGF\Constants')]
class WicketGfVersionTest extends AbstractTestCase
{
    public function test_wicket_wp_gf_version_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_WP_GF_VERSION'), 'WICKET_WP_GF_VERSION constant should be defined');
    }

    public function test_wicket_wp_gf_version_is_string(): void
    {
        $this->assertIsString(WICKET_WP_GF_VERSION, 'WICKET_WP_GF_VERSION should be a string');
    }

    public function test_wicket_wp_gf_version_follows_semver(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', WICKET_WP_GF_VERSION, 'Version should follow semver format');
    }
}
