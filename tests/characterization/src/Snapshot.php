<?php

declare(strict_types=1);

namespace Library\Characterization;

/**
 * Snapshot assertions for test cases. Files live in snapshots/NAME.html.
 * With UPDATE_SNAPSHOTS=1 they are rewritten and the test is marked incomplete.
 */
trait Snapshot
{
    private bool $snapshotsUpdated = false;

    /** @param string $normalizedHtml output of Html::normalize() */
    protected function assertMatchesSnapshot(string $name, string $normalizedHtml): void
    {
        $dir = dirname(__DIR__) . '/snapshots';
        $file = $dir . '/' . $name . '.html';
        $actual = $dir . '/_actual/' . $name . '.html';

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($file, $normalizedHtml);
            $this->snapshotsUpdated = true;

            return;
        }

        if (!is_file($file)) {
            $this->writeActual($actual, $normalizedHtml);
            self::fail("Snapshot {$name} does not exist. Run with UPDATE_SNAPSHOTS=1 to create it.");
        }

        $expected = (string) file_get_contents($file);
        if ($expected !== $normalizedHtml) {
            $this->writeActual($actual, $normalizedHtml);
        } elseif (is_file($actual)) {
            unlink($actual);
        }

        self::assertSame($expected, $normalizedHtml, "Snapshot {$name} differs");
    }

    /** Runs after each test: a run that rewrote snapshots never counts as a pass. */
    protected function assertPostConditions(): void
    {
        if ($this->snapshotsUpdated) {
            $this->snapshotsUpdated = false;
            self::markTestIncomplete('Snapshots were rewritten (UPDATE_SNAPSHOTS=1).');
        }
    }

    private function writeActual(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }
}
