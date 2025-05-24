<?php

declare(strict_types=1);

namespace FlowCore;

use Dotenv\Dotenv;

final class FlowCore
{
    public static function initialize(): void
    {
        $cwd = getcwd();
        if ($cwd !== false && file_exists($cwd.'/.env')) {
            $dotenv = Dotenv::createImmutable($cwd);
            $dotenv->load();
        }

        $libEnvPath = __DIR__.'/../.env.defaults';
        if (file_exists($libEnvPath)) {
            $dotenv = Dotenv::createImmutable(__DIR__.'/../');
            $dotenv->load();
        }
    }
}
