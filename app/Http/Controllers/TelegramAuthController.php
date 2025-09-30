<?php

namespace App\Http\Controllers;

use danog\MadelineProto\API;
use Illuminate\Http\Request;
use App\Models\TelegramAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\MadelineProtoService;

class TelegramAuthController extends Controller
{
    protected MadelineProtoService $madelineService;

    public function __construct(MadelineProtoService $madelineService)
    {
        $this->madelineService = $madelineService;
    }

    protected function getAccountId(Request $request): string
    {
        return $request->account_id ?? uniqid();
    }

    protected function getPhoneCacheKey(string $accountId): string
    {
        return $accountId . '_phone_number_' . Auth::id();
    }

    protected function updateTelegramAccount(string $accountId, array $authorization): void
    {
        $phone = $authorization['phone'] ?? Cache::get($this->getPhoneCacheKey($accountId));

        $dir = storage_path("app/telegram/photos/");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $photoPath = null;
        $telegramAccount = TelegramAccount::where('user_id', Auth::id())
            ->where('phone', $phone)
            ->first();

        if (
            isset($authorization['photo']) && is_array($authorization['photo']) && count($authorization['photo']) > 1 &&
            (!$telegramAccount || !$telegramAccount->photo)
        ) {
            $proto = $this->madelineService->getInstance($accountId);
            $photoPath = $proto->downloadToDir($authorization['photo'], $dir);
            $photoPath = basename($photoPath);
        }

        TelegramAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'phone' => $phone,
            ],
            [
                'session_file' => $accountId,
                'username' => $authorization['username'] ?? null,
            ]
        );
    }

    public function startLogin(Request $request)
    {
        $accountId = $this->getAccountId($request);
        $proto = $this->madelineService->getInstance($accountId);
        $me = $proto->getSelf();
        if ($me) {
            return response()->json([
                'status' => 'logged_in',
                'account_id' => $accountId
            ]);
        }
        try {
            $qr = $proto->qrLogin();
            if (!$qr) {
                if ($proto->getAuthorization() === API::WAITING_PASSWORD) {
                    return response()->json([
                        'status' => 'need_password',
                        'hint' => $proto->getHint(),
                        'account_id' => $accountId
                    ]);
                }
                return response()->json([
                    'status' => 'already_logged_in',
                    'account_id' => $accountId
                ]);
            }

            return response()->json([
                'status' => 'show_qr',
                'qrSvg' => $qr->getQRSvg(),
                'expires_at' => (int) $qr->expiresIn(),
                'account_id' => $accountId
            ]);
        } catch (\danog\MadelineProto\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal error',
                'account_id' => $accountId
            ]);
        }
    }

    public function checkLogin(Request $request)
    {
        $accountId = $this->getAccountId($request);
        $proto = $this->madelineService->getInstance($accountId);

        $me = $proto->getSelf();
        if ($me) {
            return response()->json([
                'status' => 'logged_in',
                'account_id' => $accountId
            ]);
        }

        try {
            $qr = $proto->qrLogin();
            $qr = $qr?->waitForLoginOrQrCodeExpiration(new \Amp\TimeoutCancellation(5.0));
        } catch (\Amp\CancelledException) {
            $qr = $proto->qrLogin();
        } catch (\danog\MadelineProto\Exception $e) {
            if ($e->getMessage() === 'QR code expired') {
                return response()->json([
                    'status' => 'expired',
                    'account_id' => $accountId
                ]);
            }
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'account_id' => $accountId
            ], 500);
        }

        logger("QR Login check: " . ($qr ? 'waiting_for_qr' : 'not waiting') . ", auth state: " . $proto->getAuthorization());
        if ($qr) {
            return response()->json([
                'status' => 'waiting_for_qr',
                'account_id' => $accountId
            ]);
        } else {
            if ($proto->getAuthorization() === API::WAITING_PASSWORD) {
                return response()->json([
                    'status' => 'need_password',
                    'hint' => $proto->getHint(),
                    'account_id' => $accountId
                ]);
            }
            if ($proto->getAuthorization() === API::LOGGED_IN) {
                $authorization = $proto->getSelf();
                $this->updateTelegramAccount($accountId, $authorization);
                return response()->json([
                    'status' => 'logged_in',
                    'account_id' => $accountId
                ]);
            }
        }
    }

    public function submitPhone(Request $request)
    {
        $accountId = $this->getAccountId($request);
        $proto = $this->madelineService->getInstance($accountId);
        $phone = $request->phone;
        $proto->phoneLogin($phone);

        // Cache the phone number expire time 1 hour
        Cache::put($this->getPhoneCacheKey($accountId), $phone, 3600);

        return response()->json([
            'status' => 'code_sent',
            'account_id' => $accountId
        ]);
    }

    public function submitCode(Request $request)
    {
        $accountId = $request->account_id;
        $proto = $this->madelineService->getInstance($accountId);
        $code = $request->code;

        $authorization = $proto->completePhoneLogin($code);

        if ($authorization['_'] === 'account.password') {
            return response()->json([
                'status' => 'need_password',
                'hint' => $authorization['hint'],
            ]);
        }
        if ($authorization['_'] === 'account.needSignup') {
            return response()->json([
                'status' => 'need_signup',
            ]);
        }

        $authorization = $proto->getSelf();
        $this->updateTelegramAccount($accountId, $authorization);

        return response()->json(['status' => 'logged_in']);
    }

    public function submitPassword(Request $request)
    {
        $accountId = $request->account_id;
        $proto = $this->madelineService->getInstance($accountId);
        $password = trim($request->password);
        $proto->complete2faLogin($password);
        $authorization = $proto->getSelf();
        $this->updateTelegramAccount($accountId, $authorization);

        return response()->json(['status' => 'logged_in']);
    }

    public function submitSignup(Request $request)
    {
        $accountId = $request->account_id;
        $proto = $this->madelineService->getInstance($accountId);
        $first = $request->first_name;
        $last = $request->last_name;
        $proto->completeSignup($first, $last);
        $authorization = $proto->getSelf();
        $this->updateTelegramAccount($accountId, $authorization);

        return response()->json(['status' => 'signed_up']);
    }
}
