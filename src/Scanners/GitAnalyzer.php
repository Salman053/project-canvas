<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

class GitAnalyzer
{
    private string $repoPath;

    public function __construct(?string $repoPath = null)
    {
        $this->repoPath = $repoPath ?? base_path();
    }

    public function getCommitHeatmap(): array
    {
        $heatmap = [];

        $log = $this->runGitCommand(
            ['log', '--name-only', '--pretty=format:COMMIT:%H:%ai', '-100'],
        );

        if ($log === null) {
            return $heatmap;
        }

        $lines = explode("\n", $log);
        $currentDate = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'COMMIT:')) {
                $parts = explode(':', $line);
                $currentDate = $parts[2] ?? null;
            } elseif ($line !== '' && $currentDate) {
                $heatmap[$line] = ($heatmap[$line] ?? 0) + 1;
            }
        }

        return $heatmap;
    }

    public function getTimelineSnapshots(int $count = 10): array
    {
        $snapshots = [];

        $log = $this->runGitCommand(
            ['log', '--pretty=format:%H|%ai|%s', '-50'],
        );

        if ($log === null) {
            return $snapshots;
        }

        $commits = explode("\n", trim($log));
        $step = max(1, (int) floor(count($commits) / $count));

        for ($i = 0; $i < count($commits); $i += $step) {
            $parts = explode('|', $commits[$i], 3);

            $snapshots[] = [
                'hash' => $parts[0],
                'date' => $parts[1] ?? null,
                'message' => $parts[2] ?? null,
            ];
        }

        return $snapshots;
    }

    public function getRecentCommitCount(int $days = 30): int
    {
        $since = now()->subDays($days)->format('Y-m-d');

        $result = $this->runGitCommand([
            'rev-list', '--count', 'HEAD',
            '--since='.$since,
        ]);

        return $result ? (int) trim($result) : 0;
    }

    private function runGitCommand(array $args): ?string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            'git -C '.escapeshellarg($this->repoPath).' '.implode(' ', array_map('escapeshellarg', $args)),
            $descriptors,
            $pipes,
        );

        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        return $output !== false ? $output : null;
    }
}
