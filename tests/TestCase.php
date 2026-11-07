<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Features\SupportTesting\Testable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Body of the file a Livewire action returned as a download.
     */
    protected function downloadedContent(Testable $component): string
    {
        $download = $component->effects['download'] ?? null;

        $this->assertIsArray($download, 'The action did not return a file download.');

        return (string) base64_decode($download['content']);
    }
}
