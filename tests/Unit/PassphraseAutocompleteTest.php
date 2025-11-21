<?php

namespace Tests\Unit;

use Tests\TestCase;

class PassphraseAutocompleteTest extends TestCase
{
    public function test_passphrase_input_has_autocomplete_off(): void
    {
        $path = base_path('resources/js/Pages/Esign/Sign.vue');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('autocomplete="off"', $content);
    }
}