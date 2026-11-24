<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class SitesScoresRunner
{
    private const MAX_TARGETS = 10;
    private const MAX_TARGET_LENGTH = 500;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $pythonBinary,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validateTargets(string $rawTargets, bool $allowExternal): array
    {
        $lines = preg_split('/\R/u', $rawTargets) ?: [];
        $targets = [];

        foreach ($lines as $line) {
            $value = trim($line);
            if ($value === '' || str_starts_with($value, '#')) {
                continue;
            }
            if (strlen($value) > self::MAX_TARGET_LENGTH || preg_match('/[\x00-\x20\x7F]/', $value)) {
                throw new RuntimeException('Une cible contient des caracteres invalides ou depasse 500 caracteres.');
            }

            $candidate = str_contains($value, '://') ? $value : 'https://'.$value;
            $parts = parse_url($candidate);
            if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
                throw new RuntimeException('URL invalide : '.$value);
            }

            $scheme = strtolower($parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new RuntimeException('Seuls les schemes http et https sont autorises : '.$value);
            }
            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
                throw new RuntimeException('Identifiants et fragments interdits dans une cible : '.$value);
            }
            if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
                throw new RuntimeException('Port invalide dans la cible : '.$value);
            }

            $host = strtolower($parts['host']);
            if (!$allowExternal && !$this->isLocalTarget($host)) {
                throw new RuntimeException('Les clients ne peuvent auditer que des cibles locales ou privees : '.$value);
            }

            $authority = $parts['host'];
            if (isset($parts['port'])) {
                $authority .= ':'.$parts['port'];
            }
            $normalized = $scheme.'://'.$authority.($parts['path'] ?? '');
            if (isset($parts['query']) && $parts['query'] !== '') {
                $normalized .= '?'.$parts['query'];
            }
            $targets[] = $normalized;
        }

        if ($targets === []) {
            throw new RuntimeException('Indiquez au moins une cible.');
        }
        if (count($targets) > self::MAX_TARGETS) {
            throw new RuntimeException('Maximum '.self::MAX_TARGETS.' cibles par audit.');
        }

        return array_values(array_unique($targets));
    }

    public function run(array $targets): array
    {
        $project = realpath($this->projectDir);
        if ($project === false || !is_file($project.'/main.py')) {
            throw new RuntimeException('Projet Sites_Scores introuvable : '.$this->projectDir);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ss_audit_');
        if ($tmp === false) {
            throw new RuntimeException('Impossible de creer le fichier de rapport temporaire.');
        }

        $command = [$this->pythonBinary, $project.'/main.py', ...$targets, '--workers', '2', '--timeout', '5', '--json', $tmp];
        $process = new Process($command, $project, ['PYTHONIOENCODING' => 'utf-8'], null, 180);
        $raw = '';
        $output = '';
        $errorOutput = '';

        try {
            $process->run();
            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());
            if (!$process->isSuccessful()) {
                $message = $errorOutput ?: $output;
                throw new RuntimeException('Sites_Scores a echoue : '.mb_substr($message, 0, 800));
            }
            $raw = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        try {
            $report = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('Rapport Sites_Scores illisible : '.$e->getMessage()."\n\n".$output);
        }

        if (!is_array($report) || !array_is_list($report)) {
            throw new RuntimeException('Format de rapport Sites_Scores inattendu.'."\n\n".$output);
        }

        $this->logger->info('Sites_Scores audit executed', [
            'targets' => array_map(static fn (string $target): string => (string) (parse_url($target, PHP_URL_HOST) ?? ''), $targets),
            'count' => count($targets),
        ]);

        return [
            'report' => $report,
            'output' => $output,
            'error_output' => $errorOutput,
            'command' => implode(' ', array_map('escapeshellarg', $command)),
        ];
    }

    public function summarize(array $report): array
    {
        $scores = array_values(array_filter(
            array_column($report, 'score'),
            static fn ($score): bool => is_numeric($score)
        ));

        if ($scores === []) {
            return ['score' => null, 'grade' => 'N/A'];
        }

        $score = round(array_sum($scores) / count($scores), 1);
        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default => 'E',
        };

        return ['score' => $score, 'grade' => $grade];
    }

    private function isLocalTarget(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return true;
        }

        $ip = trim($host, '[]');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1' || str_starts_with($ip, '127.')) {
            return true;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        return str_starts_with($ip, '10.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1
            || str_starts_with($ip, '192.168.');
    }
}
