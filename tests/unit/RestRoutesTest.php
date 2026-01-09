<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('Wicket_Gf_Main')]
class RestRoutesTest extends AbstractTestCase
{
    public function test_rest_route_resync_member_fields_is_registered(): void
    {
        $mock_server = new class {
            public function get_routes()
            {
                return [
                    '/wicket-gf/v1/resync-member-fields' => [],
                ];
            }
        };

        Functions\stubs(['rest_get_server' => $mock_server]);

        $routes = rest_get_server()->get_routes();

        $this->assertArrayHasKey('/wicket-gf/v1/resync-member-fields', $routes);
    }

    public function test_resync_route_uses_post_method(): void
    {
        $mock_route = new class {
            public function get_methods()
            {
                return ['POST', 'GET'];
            }
        };

        $mock_server = new class {
            public function get_routes()
            {
                return [
                    '/wicket-gf/v1/resync-member-fields' => [
                        new class {
                            public function get_methods()
                            {
                                return ['POST'];
                            }
                        },
                    ],
                ];
            }
        };

        Functions\stubs(['rest_get_server' => $mock_server]);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/wicket-gf/v1/resync-member-fields'];

        $this->assertIsArray($route);
        $this->assertNotEmpty($route);

        $route_methods = $route[0]->get_methods();
        $this->assertContains('POST', $route_methods);
    }
}
