<?php

declare(strict_types=1);

namespace Htzone\Tests\Support;

use PHPUnit\Framework\Attributes\After;

/**
 * A fresh temporary directory per test, deleted afterwards. Integration tests use it for real SQLite files.
 */
trait TempDirectory
{
    private ?string $tempDir = null;

    protected function tempDir(): string
    {
        if ($this->tempDir === null) {
            $this->tempDir = sys_get_temp_dir() . '/htzone-test-' . bin2hex(random_bytes(6));
            mkdir($this->tempDir, 0o700, true);
        }

        return $this->tempDir;
    }

    #[After]
    protected function removeTempDir(): void
    {
        if ($this->tempDir === null) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tempDir);
        $this->tempDir = null;
    }
}
