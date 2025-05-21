<?php

namespace FlowCore;

use Dotenv\Dotenv;

class FlowCore
{
    public static function initialize(): void
    {
        if (file_exists(getcwd() . '/.env')) {
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->load();
        }

        $libEnvPath = __DIR__ . '/../.env.defaults';
        if (file_exists($libEnvPath)) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
        }
    }
}