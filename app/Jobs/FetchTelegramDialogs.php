<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchTelegramDialogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $accountId;
    protected $proto;
    protected $offset_date;
    protected $offset_id;
    protected $offset_peer;
    protected $limit;

    /**
     * Create a new job instance.
     */
    public function __construct($accountId, $proto, $offset_date = 0, $offset_id = 0, $offset_peer = null, $limit = 100)
    {
        $this->accountId = $accountId;
        $this->proto = $proto;
        $this->offset_date = $offset_date;
        $this->offset_id = $offset_id;
        $this->offset_peer = $offset_peer;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Instantiate MadelineProto
        $MadelineProto = $this->proto;

        try {
            $result = $MadelineProto->messages->getDialogs([
                'exclude_pinned' => null,
                'folder_id' => null,
                'offset_date' => $this->offset_date,
                'offset_id' => $this->offset_id,
                'offset_peer' => $this->offset_peer,
                'limit' => $this->limit,

            ]);
            foreach (($result['chats'] ?? []) as $chat) {
                new ProcessTelegramChat($chat, $this->accountId, $this->proto);
            }
            foreach (($result['users'] ?? []) as $user) {
                new ProcessTelegramUser($user, $this->accountId, $this->proto);
            }

            // Paginate if there are more dialogs to fetch
            $dialogs = $result['dialogs'] ?? [];
            if (count($dialogs) === $this->limit) {
                $last_dialog = end($dialogs);
                $next_offset_date = $last_dialog['date'] ?? $this->offset_date;
                $next_offset_id = $last_dialog['top_message'] ?? $this->offset_id;
                $next_offset_peer = $last_dialog['peer'] ?? $this->offset_peer;

                self::dispatch($this->accountId, $this->proto, $next_offset_date, $next_offset_id, $next_offset_peer, $this->limit)
                    ->delay(now()->addSeconds(2));
            }
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if (preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $matches)) {
                $waitSeconds = (int)$matches[1];
                self::dispatch($this->accountId, $this->proto, $this->offset_date, $this->offset_id, $this->offset_peer, $this->limit)
                    ->delay(now()->addSeconds($waitSeconds + 1));
            } else {
                Log::error($e);
            }
        }
    }
}
