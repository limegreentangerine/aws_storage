<?php

namespace AwsStorage\Logger;

use ClassKit\Log\Logger;

class AwsStorageLogger extends Logger
{
    private bool $isEnabled;

    public function __construct()
    {
        parent::__construct('aws_storage');
        $this->isEnabled = $this->checkIfLoggingEnabled();
    }

    private function checkIfLoggingEnabled(): bool
    {
        $envFile = $this->findEnvFile();
        if ($envFile && file_exists($envFile)) {
            $this->loadEnvFile($envFile);
        }

        return strtolower((string) ($_ENV['AWS_STORAGE_LOG'] ?? getenv('AWS_STORAGE_LOG'))) === 'true';
    }

    private function findEnvFile(): ?string
    {
        $paths = [
            getcwd() . '/.env',
            dirname(__DIR__, 3) . '/.env',
            dirname(__DIR__, 4) . '/.env',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function loadEnvFile(string $filePath): void
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }

    public function isLoggingEnabled(): bool
    {
        return $this->isEnabled;
    }
}
