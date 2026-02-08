<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Browser Test Helpers
|--------------------------------------------------------------------------
*/

function orgss_scenario_1_path(): string
{
    $path = getenv('WICKET_BROWSER_ORGSS_SCENARIO_1_PATH') ?: '/orgss-scenario-1/';
    return $path;
}

function wicket_browser_base_url(): string
{
    $baseUrl = getenv('WICKET_BROWSER_BASE_URL') ?: 'https://localhost';

    return rtrim($baseUrl, '/');
}

function wicket_browser_options(): array
{
    $ignoreHttps = getenv('WICKET_BROWSER_IGNORE_HTTPS_ERRORS');

    if ($ignoreHttps === false || $ignoreHttps === '') {
        $host = (string) (parse_url(wicket_browser_base_url(), PHP_URL_HOST) ?: '');
        $ignoreHttps = in_array($host, ['localhost', '127.0.0.1'], true) ? '1' : '0';
    }

    $ignoreHttpsBool = in_array(strtolower((string) $ignoreHttps), ['1', 'true', 'yes', 'on'], true);

    return $ignoreHttpsBool ? ['ignoreHTTPSErrors' => true] : [];
}

/**
 * Login via external SSO and return page for chaining.
 */
function loginAndVisit(string $targetUrl)
{
    $username = (string) (getenv('WICKET_BROWSER_USERNAME') ?: '');
    $password = (string) (getenv('WICKET_BROWSER_PASSWORD') ?: '');

    return visit(wicket_browser_base_url(), wicket_browser_options())
        ->click('.login-button')
        ->type('#username', $username)
        ->type('#password', $password)
        ->assertScript(
            <<<'JS'
            (() => {
                const form = document.querySelector('#fm1');
                if (!form) return false;
                form.submit();
                return true;
            })()
            JS,
            true
        )
        ->wait(5)
        ->navigate($targetUrl);
}
