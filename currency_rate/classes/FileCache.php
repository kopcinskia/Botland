<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class FileCache
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->filePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $payload = unserialize(file_get_contents($path));

        if (!is_array($payload) || !isset($payload['expires_at'], $payload['data'])) {
            return null;
        }

        if (time() > $payload['expires_at']) {
            @unlink($path);
            return null;
        }

        return $payload['data'];
    }

    public function set(string $key, mixed $data, int $ttl = 3600): void
    {
        file_put_contents(
            $this->filePath($key),
            serialize(['expires_at' => time() + $ttl, 'data' => $data]),
            LOCK_EX
        );
    }

    public function invalidate(string $key): void
    {
        $path = $this->filePath($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function clear(): void
    {
        foreach (glob($this->cacheDir . '*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    private function filePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
