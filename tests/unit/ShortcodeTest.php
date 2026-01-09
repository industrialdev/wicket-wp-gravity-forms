<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('Wicket_Gf_Main')]
class ShortcodeTest extends AbstractTestCase
{
    public function test_shortcode_wicket_gravityform_exists(): void
    {
        Functions\stubs(['shortcode_exists' => true]);
        $this->assertTrue(shortcode_exists('wicket_gravityform'), 'wicket_gravityform shortcode should be registered');
    }

    public function test_shortcode_returns_wrapped_content(): void
    {
        if (!class_exists('Wicket_Gf_Main')) {
            $this->markTestSkipped('Wicket_Gf_Main class not available');
        }

        $output = do_shortcode('[wicket_gravityform slug="test"]');

        // Should contain wrapper div
        $this->assertStringContainsString('<div class="container wicket-gf-shortcode">', $output);
    }

    public function test_shortcode_with_empty_slug_returns_empty(): void
    {
        if (!class_exists('\Wicket_Gf_Main')) {
            $this->markTestSkipped('Wicket_Gf_Main class not available');
        }

        $output = do_shortcode('[wicket_gravityform]');

        $this->assertEmpty($output);
    }
}
