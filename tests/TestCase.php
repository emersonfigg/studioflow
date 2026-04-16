<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $compiledViewsPath = implode(DIRECTORY_SEPARATOR, [
            sys_get_temp_dir(),
            'studioflow-testing-views',
            (string) getmypid(),
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->name()),
        ]);

        File::ensureDirectoryExists($compiledViewsPath);

        config([
            'view.compiled' => $compiledViewsPath,
        ]);
    }
}
