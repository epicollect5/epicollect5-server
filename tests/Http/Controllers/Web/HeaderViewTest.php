<?php

namespace Tests\Http\Controllers\Web;

use Tests\TestCase;

/**
 * Regression test for resources/views/header.blade.php.
 *
 * The header view is included by every page that uses errors.gen_error or
 * app.blade.php. A single broken Blade directive there previously caused
 * 100+ tests to fail with 500 responses, since the JSON-LD block contained
 * literal @context and @type tokens that Blade tried to compile as
 * directives. These tests assert the header compiles and produces valid
 * output that downstream views can rely on.
 */
class HeaderViewTest extends TestCase
{
    public function test_header_view_compiles_and_contains_json_ld_payload()
    {
        $output = view('header', [
            'requestAttributes' => null,
            'nonce' => 'test-nonce',
        ])->render();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<meta charset="utf-8">', $output);
        $this->assertStringContainsString('application/ld+json', $output);
        $this->assertStringContainsString('"@context"', $output);
        $this->assertStringContainsString('"@type"', $output);
        $this->assertStringContainsString('schema.org', $output);
    }

    public function test_errors_gen_error_view_renders_with_header()
    {
        $output = view('errors.gen_error', [
            'errors' => new \Illuminate\Support\MessageBag(['errors' => ['ec5_91']]),
        ])->render();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('application/ld+json', $output);
    }
}
