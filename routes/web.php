<?php

use Illuminate\Support\Facades\Route;
use danog\MadelineProto\API;
use App\Http\Responses\JsonResponder;

Route::get('/', function () {

    $sessionFile = storage_path('app/telegram/telegram.session');

    $settings = new \danog\MadelineProto\Settings;
    $settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)
            ->setApiId(2024)
            ->setApiHash('b18441a1ff607e10a989891a5462e627')
    );
    $MadelineProto = new API($sessionFile, $settings);
    dd($MadelineProto->getAuthorization());
    // If not authorized, supply phone and code from request
    if (!$MadelineProto->getAuthorization()) {
        $phone = $request->input('phone');
        $MadelineProto->phone_login($phone); // Sends code to phone

        $code = $request->input('code'); // User submits this after receiving
        $MadelineProto->complete_phone_login($code);
    }

    return response()->json(['status' => 'Session created!']);

    $sessionDir = storage_path('app/madeline/manual-session');
    if (!file_exists($sessionDir)) {
        mkdir($sessionDir, 0777, true);
    }
    $session_path = $sessionDir . DIRECTORY_SEPARATOR . '.17058297103.session';

    $api = new API($session_path, getMadelineSettings());

    $api->start();
    dd('Done');

    try {
        $qr = $api->qrLogin();
        $qr = $qr?->waitForLoginOrQrCodeExpiration(new \Amp\TimeoutCancellation(5.0));
    } catch (\Amp\CancelledException) {
        $qr = $api->qrLogin();
    }

    if ($qr) {
        $result = [
            'logged_in' => false,
            'svg' => $qr->getQRSvg(400, 2)
        ];
    } else {
        $result = [
            'logged_in' => true,
            'needs_2fa' => $api->getAuthorization() === API::WAITING_PASSWORD
        ];
    }
    dd($result);
});
