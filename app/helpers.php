<?php

if (!function_exists('getSessionDir')) {
    function getSessionDir()
    {
        $dir = storage_path("app/telegram_sessions/");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('getMadelineSettings')) {
    function getMadelineSettings()
    {
        $settings = new \danog\MadelineProto\Settings;
        $settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId(20724149)
                ->setApiHash('d919f276e10b80ab0b5bf4dad0121663')
        );

        // Use database for session storage
        $settings->setDb((new \danog\MadelineProto\Settings\Database\Mysql)
                ->setUri('tcp://localhost')
                ->setDatabase(config('database.connections.mysql.database'))
                ->setUsername(config('database.connections.mysql.username'))
                ->setPassword(config('database.connections.mysql.password'))
        );
        return $settings;
    }
}
