<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('GFWicketFieldOrgSearchSelect')]
class GFWicketFieldOrgSearchSelectTest extends AbstractTestCase
{
    private object $field;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('GFWicketFieldOrgSearchSelect')) {
            $this->markTestSkipped('GFWicketFieldOrgSearchSelect class not available');
        }

        $this->field = new \GFWicketFieldOrgSearchSelect();
    }

    public function test_field_type_is_correct(): void
    {
        $this->assertSame('wicket_org_search_select', $this->field->type);
    }

    public function test_get_form_editor_field_title_returns_string(): void
    {
        $title = $this->field->get_form_editor_field_title();

        $this->assertIsString($title);
    }

    public function test_get_form_editor_button_returns_array(): void
    {
        $button = $this->field->get_form_editor_button();

        $this->assertIsArray($button);
        $this->assertArrayHasKey('group', $button);
        $this->assertArrayHasKey('text', $button);
    }

    public function test_field_button_group_is_advanced_fields(): void
    {
        $button = $this->field->get_form_editor_button();

        $this->assertSame('advanced_fields', $button['group']);
    }

    public function test_get_form_editor_field_settings_returns_array(): void
    {
        $settings = $this->field->get_form_editor_field_settings();

        $this->assertIsArray($settings);
    }

    public function test_field_settings_contains_wicket_orgss_setting(): void
    {
        $settings = $this->field->get_form_editor_field_settings();

        $this->assertContains('wicket_orgss_setting', $settings);
    }

    public function test_is_conditional_logic_supported_returns_true(): void
    {
        $this->assertTrue($this->field->is_conditional_logic_supported());
    }

    public function test_is_value_submission_array_returns_false(): void
    {
        $this->assertFalse($this->field->is_value_submission_array());
    }

    public function test_get_choices_returns_empty_array(): void
    {
        $choices = $this->field->get_choices();

        $this->assertIsArray($choices);
        $this->assertEmpty($choices);
    }

    public function test_get_field_size_settings_returns_array(): void
    {
        $size = $this->field->get_field_size_settings();

        $this->assertIsArray($size);
        $this->assertArrayHasKey('size', $size);
    }

    public function test_get_field_size_settings_size_is_medium(): void
    {
        $size = $this->field->get_field_size_settings();

        $this->assertSame('medium', $size['size']);
    }
}
