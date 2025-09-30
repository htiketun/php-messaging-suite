<?php

namespace App\Services;

use danog\MadelineProto\API;


class MadelineProtoService
{
    /**
     * Get a MadelineProto API instance for a specific account.
     *
     * @param int|string $accountId
     * @return API
     */
    public function getInstance($accountId)
    {
        $sessionDir = storage_path('app/madeline/');
        if (!file_exists($sessionDir)) {
            mkdir($sessionDir, 0777, true);
        }

        $sessionFile = $sessionDir . $accountId . '.madeline';
        return new API($sessionFile, self::getMadelineSettings());
    }

    private static function getMadelineSettings(): \danog\MadelineProto\Settings
    {
        $settings = new \danog\MadelineProto\Settings;
        $settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId(20724149)
                ->setApiHash('d919f276e10b80ab0b5bf4dad0121663')
        );

        $settings->setDb((new \danog\MadelineProto\Settings\Database\Mysql)
                ->setUri('tcp://localhost')
                ->setPassword('')
            // ->setDatabase(config('database.connections.mysql.database'))
            // ->setUsername(config('database.connections.mysql.username'))
            // ->setPassword(config('database.connections.mysql.password'))
        );
        return $settings;
    }
}
