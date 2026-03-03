<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('\Wicket_Gf_Main')]
class GFWicketFieldUserMdpTagsPopulationTest extends AbstractTestCase
{
    public function test_dynamic_population_uses_combined_source_by_default(): void
    {
        Functions\when('apply_filters')->alias(function ($tag, $value) {
            return $value;
        });

        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'tags') {
                    return ['tag-a'];
                }
                if ($key === 'segment_tags') {
                    return ['seg-a'];
                }

                return null;
            }
        });

        $main = \Wicket_Gf_Main::get_instance();
        $value = $main->populate_user_mdp_tags_dynamic_parameter('');

        $this->assertSame('seg-a,tag-a', $value);
    }

    public function test_dynamic_population_respects_filter_override_to_tags(): void
    {
        Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'wicket_gf_user_mdp_tags_default_source') {
                return 'tags';
            }

            return $value;
        });

        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'tags') {
                    return ['tag-only'];
                }
                if ($key === 'segment_tags') {
                    return ['seg-only'];
                }

                return null;
            }
        });

        $main = \Wicket_Gf_Main::get_instance();
        $value = $main->populate_user_mdp_tags_dynamic_parameter('');

        $this->assertSame('tag-only', $value);
    }
}
