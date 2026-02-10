<?php

declare(strict_types=1);

pest()->browser()->timeout(20_000);

// TODO: Need to remove all relationships between the user and orgs to reduce test flakiness.

describe('guest', function () {
    it('does not show the org search select', function () {
        visit(wicket_browser_base_url() . orgss_scenario_1_path(), wicket_browser_options())
            ->assertNotPresent('.gfield--input-type-wicket_org_search_select');
    });
});

describe('logged in', function () {

    describe('orgss scenario 1', function () {

        it('shows the org search select', function () {
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->assertPresent('.gfield--input-type-wicket_org_search_select');
        });

        it('shows no orgs found', function () {
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->type('input[x-model="searchBox"]', 'non-existing-org')
                ->click('.component-org-search-select__search-button')
                ->assertSee('Sorry, no organizations match your search');
        });

        it('returns search results', function () {
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->clear('input[x-model="searchBox"]')
                ->fill('input[x-model="searchBox"]', 'Test Org')
                ->click('.component-org-search-select__search-button')
                ->wait(2)
                ->assertPresent('.component-org-search-select__matching-org-item')
                ->assertSee('Matching Organization(s)')
                ->assertPresent('.component-org-search-select__matching-org-item:has(button:text("Select"))');
        });

        it('clears search results', function () {
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->type('input[x-model="searchBox"]', 'Test Org')
                ->click('.component-org-search-select__search-button')
                ->wait(1)
                ->click('Clear')
                ->wait(1)
                ->assertNotPresent('.component-org-search-select__matching-org-item')
                ->assertDontSee('Matching Organization(s)');
        });

        it('selects an org from search results', function () {
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->clear('input[x-model="searchBox"]')
                ->type('input[x-model="searchBox"]', 'Org')
                ->click('.component-org-search-select__search-button')
                ->wait(2)
                ->click('.component-org-search-select__select-result-button:not([disabled]) >> nth=0')
                ->wait(1)
                ->assertSee('Selected Organization:')
                ->assertPresent('.component-org-search-select__card--selected')
                ->assertVisible('button:text("Clear Selection")');
        });

        it('creates a new org from search results', function () {
            $random_org_name = 'Newly Created Org ' . uniqid();
        
            loginAndCleanup(wicket_browser_base_url() . orgss_scenario_1_path())
                ->clear('input[x-model="searchBox"]')
                ->type('input[x-model="searchBox"]', 'Non Existing Org')
                ->click('.component-org-search-select__search-button')
                ->wait(2)
                ->assertSee("Can't find your organization?")
                ->type('.component-org-search-select__create-org-name-input', $random_org_name)
                ->select('.component-org-search-select__create-org-type-select', 'certification_authority')
                ->click('.component-org-search-select__create-org-button')
                ->wait(3)
                ->assertVisible('.component-org-search-select__card--selected')
                ->assertSee($random_org_name)
                ->assertMissing('.component-org-search-select__create-org-form');
        });
    });

});