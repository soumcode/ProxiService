<?php

namespace App\Jobs;

use App\Services\OrangeSmsService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrangeSmsMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Nombre de tentatives avant échec définitif.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Délai en secondes avant nouvelle tentative.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Timeout en secondes.
     *
     * @var int
     */
    public $timeout = 30;

    public function __construct(
        public string $recipient,
        public string $message,
    ) {}

    public function handle(OrangeSmsService $orangeSms): void
    {
        $orangeSms->send($this->recipient, $this->message);
    }
}
