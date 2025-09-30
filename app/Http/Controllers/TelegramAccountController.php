<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TelegramAccount;
use danog\MadelineProto\API;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Cache;
use App\Http\Responses\JsonResponder;
use App\Models\TelegramChatList;

class TelegramAccountController extends Controller
{

    public function index()
    {
        $userId = Auth::id();
        $accounts = TelegramAccount::where('user_id', $userId)->get();
        return JsonResponder::success($accounts, 'Accounts fetched successfully');
    }

    public function testChatList()
    {
        $userId = Auth::id() ?? 1;
        $account = TelegramAccount::where('user_id', $userId)->first();
        event(new \App\Events\TelegramChatListFetchRequested($account->id));

        dd('Done fetching and storing chat list.');
    }

    public function testChatHistory($id)
    {
        $userId = Auth::id();
        $account = TelegramAccount::where('user_id', $userId)->first();
        $api = new API(getSessionDir() . $account->session_file, getMadelineSettings());

        $messages = $api->messages->getHistory([
            'peer' => $id,
            'limit' => 50, // Adjust as needed
        ]);

        dd($messages['messages'][0]);
        return view('chat_history', ['messages' => $messages['messages']]);
    }

    // Phone login step 1
    public function startLogin(Request $request)
    {
        $request->validate(['phone' => 'required|string']);
        $phone = $request->phone;
        $sessionPath = getSessionDir() . uniqid() . ".madeline";

        try {
            $api = new API($sessionPath, getMadelineSettings());
            $api->phoneLogin($phone);
            Cache::put($request->unique_key . 'phone_session_' . (Auth::id()), $sessionPath);
            Cache::put($request->unique_key . 'phone_number_' . (Auth::id()), $phone);

            return JsonResponder::success([], 'Code sent');
        } catch (\Exception $e) {
            return JsonResponder::error($e->getMessage(), null, 500);
        }
    }

    // Phone login step 2
    public function confirmLogin(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);
        $sessionPath = Cache::get($request->unique_key . 'phone_session_' . (Auth::id()));
        $phone = Cache::get($request->unique_key . 'phone_number_' . (Auth::id()));

        $api = new API($sessionPath, getMadelineSettings());
        $api->completePhoneLogin($request->code);
        $me = $api->getSelf();

        $account = TelegramAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'phone' => $phone,
            ],
            [
                'session_file' => basename($sessionPath),
                'username' => $me['username'] ?? null,
            ]
        );
        Cache::forget($request->unique_key . 'phone_session_' . (Auth::id()));
        Cache::forget($request->unique_key . 'phone_number_' . (Auth::id()));

        return JsonResponder::success($account, 'Login successful');
    }

    // QR login step 1 - get QR image
    public function qrLogin(Request $request)
    {
        $userId = Auth::id();
        $sessionPath = getSessionDir() . uniqid() . ".madeline";
        $api = new API($sessionPath, getMadelineSettings());
        $qrLogin = $api->qrLogin();
        $qrString = $qrLogin->link;
        Cache::put($request->unique_key . 'qr_session_' . $userId, $sessionPath);
        Cache::put($request->unique_key . 'qr_login_' . $userId, serialize($qrLogin));

        $qrCode = new QrCode($qrString);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return response($result->getString(), 200)
            ->header('Content-Type', $result->getMimeType()); // Keep as is, not JSON
    }

    // QR login step 2 - poll for scan and login
    public function qrPoll(Request $request)
    {
        $userId = Auth::id();
        $sessionPath = Cache::get($request->unique_key . 'qr_session_' . $userId);
        $serializedLogin = Cache::get($request->unique_key . 'qr_login_' . $userId);

        $api = new API($sessionPath, getMadelineSettings());
        // Now logged in, get user info
        $qr = $api->qrLogin();
        dd($qr);

        if (!$me || !isset($me['id'])) {
            Cache::forget($request->unique_key . 'qr_session_' . $userId);
            Cache::forget($request->unique_key . 'qr_login_' . $userId);
            return JsonResponder::error('Failed to retrieve user info after login.', null, 500);
        }

        $account = TelegramAccount::updateOrCreate(
            [
                'user_id' => $userId,
                'phone' => $me['phone'] ?? null,
            ],
            [
                'session_file' => basename($sessionPath),
                'username' => $me['username'] ?? null,
            ]
        );

        Cache::forget($request->unique_key . 'qr_session_' . $userId);
        Cache::forget($request->unique_key . 'qr_login_' . $userId);

        return JsonResponder::success(['status' => 'logged_in', 'account' => $account], 'Login successful');
    }

    // Send message
    public function sendMessage(Request $request)
    {
        $request->validate([
            'telegram_account_id' => 'required|exists:telegram_accounts,id',
            'chat_id' => 'required|string',
            'message' => 'required|string|max:4096',
        ]);
        $account = TelegramAccount::findOrFail($request->telegram_account_id);

        $sessionPath = storage_path("app/telegram_sessions/" . $account->session_file);
        $settings = getMadelineSettings();
        $api = new API($sessionPath, $settings);
        $api->messages->sendMessage([
            'peer' => $request->chat_id,
            'message' => $request->message,
        ]);

        $account->messages()->create([
            'chat_id' => $request->chat_id,
            'message' => $request->message,
            'is_outgoing' => true,
            'date' => now(),
        ]);

        return JsonResponder::success(['status' => 'sent'], 'Message sent');
    }

    // Poll for latest messages (per account)
    public function pollMessages(Request $request)
    {
        $request->validate([
            'telegram_account_id' => 'required|exists:telegram_accounts,id',
        ]);
        $account = TelegramAccount::findOrFail($request->telegram_account_id);
        $sessionPath = storage_path("app/telegram_sessions/" . $account->session_file);
        $api = new API($sessionPath);

        // Fetch latest 10 dialogs
        $dialogs = $api->messages->getDialogs(['limit' => 10]);
        $newMessages = [];
        foreach ($dialogs['chats'] as $chat) {
            $chatId = $chat['id'];
            // Fetch last 5 messages from each chat
            $history = $api->messages->getHistory([
                'peer' => $chatId,
                'limit' => 5
            ]);
            foreach ($history['messages'] as $msg) {
                // Only store if not already in DB
                // if (!Message::where('telegram_account_id', $account->id)->where('chat_id', $chatId)->where('date', date('Y-m-d H:i:s', $msg['date']))->exists()) {
                //     $messageText = is_array($msg['message']) ? json_encode($msg['message']) : ($msg['message'] ?? '');
                //     $isOutgoing = $msg['out'] ?? false;
                //     $date = date('Y-m-d H:i:s', $msg['date']);
                //     $message = $account->messages()->create([
                //         'chat_id' => $chatId,
                //         'message' => $messageText,
                //         'is_outgoing' => $isOutgoing,
                //         'date' => $date,
                //     ]);
                //     $newMessages[] = $message;
                // }
            }
        }
        // Return latest messages for this account, sorted by date
        $messages = $account->messages()->orderBy('date', 'desc')->take(50)->get();
        return JsonResponder::success($messages, 'Messages fetched successfully');
    }

    public function checkSession(Request $request)
    {
        $request->validate([
            'telegram_account_id' => 'required|exists:telegram_accounts,id',
        ]);
        $account = TelegramAccount::findOrFail($request->telegram_account_id);
        $sessionPath = storage_path("app/telegram_sessions/" . $account->session_file);
        if (!file_exists($sessionPath)) {
            return JsonResponder::error('Session file not found', null, 404);
        }
        try {
            $api = new API($sessionPath, getMadelineSettings());
            $me = $api->getSelf();
            if ($me && isset($me['id'])) {
                return JsonResponder::success(['status' => 'connected', 'username' => $me['username'] ?? null], 'Session is active');
            } else {
                return JsonResponder::error('Failed to retrieve user info. Session may be invalid.', null, 500);
            }
        } catch (\Exception $e) {
            return JsonResponder::error('Error checking session: ' . $e->getMessage(), null, 500);
        }
    }

    public function getTelegramPhoto($filename)
    {
        $path = storage_path('app/telegram/photos/' . $filename);
        return response()->file($path);
    }
}
