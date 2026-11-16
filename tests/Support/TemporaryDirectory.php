<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Support;

/**
 * Gives each test an empty directory of its own in $dir, removed with its files after the test. It's created
 * before setUp() runs, so setUp() can use it
 */
trait TemporaryDirectory
{
    private string $dir;

    /**
     * @before
     */
    protected function createTemporaryDirectory(): void
    {
        $this->dir = sys_get_temp_dir() . '/setono-sylius-conversion-attribution-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
    }

    /**
     * @after
     */
    protected function removeTemporaryDirectory(): void
    {
        $files = glob($this->dir . '/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->dir);
    }
}
