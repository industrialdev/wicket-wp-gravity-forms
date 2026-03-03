<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('GFWicketFieldUserMdpTags')]
class GFWicketFieldUserMdpTagsTest extends AbstractTestCase
{
    private object $field;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('GFWicketFieldUserMdpTags')) {
            $this->markTestSkipped('GFWicketFieldUserMdpTags class not available');
        }

        $this->field = new \GFWicketFieldUserMdpTags();
    }

    public function test_field_type_is_correct(): void
    {
        $this->assertSame('wicket_user_mdp_tags', $this->field->type);
    }

    public function test_field_supports_conditional_logic(): void
    {
        $this->assertTrue($this->field->is_conditional_logic_supported());
    }

    public function test_field_settings_include_tag_source_setting(): void
    {
        $settings = $this->field->get_form_editor_field_settings();

        $this->assertContains('wicket_mdp_tag_source_setting', $settings);
    }

    public function test_get_user_tags_by_source_uses_combined_by_default(): void
    {
        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'segment_tags') {
                    return ['active-a', 'active-b'];
                }

                if ($key === 'tags') {
                    return ['legacy-a'];
                }

                return null;
            }
        });

        $value = \GFWicketFieldUserMdpTags::get_user_tags_by_source();

        $this->assertSame('active-a,active-b,legacy-a', $value);
    }

    public function test_get_user_tags_by_source_can_use_tags_only(): void
    {
        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'segment_tags') {
                    return ['active-a'];
                }

                if ($key === 'tags') {
                    return ['legacy-a', 'legacy-b'];
                }

                return null;
            }
        });

        $value = \GFWicketFieldUserMdpTags::get_user_tags_by_source('tags');

        $this->assertSame('legacy-a,legacy-b', $value);
    }

    public function test_get_user_tags_by_source_can_combine_and_deduplicate(): void
    {
        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'segment_tags') {
                    return ['shared', 'active-only'];
                }

                if ($key === 'tags') {
                    return ['shared', 'legacy-only'];
                }

                return null;
            }
        });

        $value = \GFWicketFieldUserMdpTags::get_user_tags_by_source('combined');

        $this->assertSame('shared,active-only,legacy-only', $value);
    }

    public function test_get_user_tags_by_source_falls_back_to_current_person_when_by_id_is_empty(): void
    {
        Functions\when('wicket_current_person_uuid')->justReturn('person-uuid');
        Functions\when('wicket_get_person_by_id')->justReturn(false);
        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'tags') {
                    return ['fallback-current'];
                }

                if ($key === 'segment_tags') {
                    return [];
                }

                return null;
            }
        });

        $value = \GFWicketFieldUserMdpTags::get_user_tags_by_source('tags');

        $this->assertSame('fallback-current', $value);
    }

    public function test_get_user_tags_by_source_falls_back_to_profile_array_when_other_sources_empty(): void
    {
        Functions\when('wicket_current_person_uuid')->justReturn('person-uuid');
        Functions\when('wicket_get_person_by_id')->justReturn(false);
        Functions\when('wicket_current_person')->justReturn(null);
        Functions\when('wicket_get_person_profile')->justReturn([
            'data' => [
                'attributes' => [
                    'tags' => ['profile-tag-a', 'profile-tag-b'],
                    'segment_tags' => [],
                ],
            ],
        ]);

        $value = \GFWicketFieldUserMdpTags::get_user_tags_by_source('tags');

        $this->assertSame('profile-tag-a,profile-tag-b', $value);
    }

    public function test_get_field_input_hidden_mode_renders_hidden_input_only(): void
    {
        Functions\when('wicket_current_person_uuid')->justReturn('');
        Functions\when('wicket_get_person_by_id')->justReturn(false);

        Functions\when('wicket_current_person')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'tags') {
                    return ['hidden-tag'];
                }

                if ($key === 'segment_tags') {
                    return [];
                }

                return null;
            }
        });

        $field = new class extends \GFWicketFieldUserMdpTags {
            public string $mdpTagSource = 'combined';
            public string $mdpTagDisplayMode = 'hidden';

            public function is_form_editor(): bool
            {
                return false;
            }
        };
        $field->id = 9;
        $field->mdpTagSource = 'tags';
        $field->mdpTagDisplayMode = 'hidden';

        $html = $field->get_field_input(['id' => 1], '', null);

        $this->assertStringContainsString("type='hidden'", $html);
        $this->assertStringContainsString('display:none', $html);
        $this->assertStringNotContainsString('_debug', $html);
    }

    public function test_get_field_input_debug_mode_renders_disabled_debug_input_and_summary(): void
    {
        Functions\when('wicket_current_person_uuid')->justReturn('9605cc05-7551-4711-8ab0-73327b351b2d');
        Functions\when('wicket_get_person_by_id')->justReturn(new class {
            public function getAttribute($key)
            {
                if ($key === 'tags') {
                    return ['wicket', 'wicket-devs'];
                }

                if ($key === 'segment_tags') {
                    return [];
                }

                return null;
            }
        });
        Functions\when('wicket_current_person')->justReturn(null);
        Functions\when('wicket_get_person_profile')->justReturn(null);

        $field = new class extends \GFWicketFieldUserMdpTags {
            public string $mdpTagSource = 'combined';
            public string $mdpTagDisplayMode = 'hidden';

            public function is_form_editor(): bool
            {
                return false;
            }
        };
        $field->id = 9;
        $field->mdpTagSource = 'tags';
        $field->mdpTagDisplayMode = 'debug_disabled';

        $html = $field->get_field_input(['id' => 1], '', null);

        $this->assertStringContainsString('input_1_9_debug', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('src=tags', $html);
        $this->assertStringContainsString('resolved=wicket,wicket-devs', $html);
    }
}
