<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('\Wicket_Gf_Main')]
class WicketGfMainTest extends AbstractTestCase
{
    public function test_get_instance_returns_singleton(): void
    {
        if (!class_exists('\Wicket_Gf_Main')) {
            $this->markTestSkipped('\Wicket_Gf_Main class not available');
        }

        $instance1 = \Wicket_Gf_Main::get_instance();
        $instance2 = \Wicket_Gf_Main::get_instance();

        $this->assertSame($instance1, $instance2, '\Wicket_Gf_Main::get_instance() should return the same instance');
    }

    public function test_instance_is_object(): void
    {
        if (!class_exists('\Wicket_Gf_Main')) {
            $this->markTestSkipped('\Wicket_Gf_Main class not available');
        }

        $instance = \Wicket_Gf_Main::get_instance();

        $this->assertIsObject($instance);
    }

    public function test_plugin_url_property_exists(): void
    {
        if (!class_exists('\Wicket_Gf_Main')) {
            $this->markTestSkipped('\Wicket_Gf_Main class not available');
        }

        $instance = \Wicket_Gf_Main::get_instance();

        $this->assertObjectHasProperty('plugin_url', $instance);
    }

    public function test_plugin_path_property_exists(): void
    {
        if (!class_exists('\Wicket_Gf_Main')) {
            $this->markTestSkipped('\Wicket_Gf_Main class not available');
        }

        $instance = \Wicket_Gf_Main::get_instance();

        $this->assertObjectHasProperty('plugin_path', $instance);
    }
}
