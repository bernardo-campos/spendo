<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Services\WebPushConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ExpenseCreatedFromEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public Transaction $transaction)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return app(WebPushConfiguration::class)->isConfigured()
            ? [WebPushChannel::class]
            : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return app(WebPushConfiguration::class)->isConfigured()
            && $notifiable->pushSubscriptions()->exists();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $amount = number_format((float) $this->transaction->amount, 2, ',', '.');

        return (new WebPushMessage)
            ->title('Gasto registrado desde un recibo')
            ->body("{$this->transaction->place} · {$this->transaction->currency->value} {$amount}")
            ->tag('receipt-expense-'.$this->transaction->id)
            ->data([
                'url' => route('app', ['screen' => 'transactions/'.$this->transaction->id.'/edit'], absolute: false),
            ])
            ->options(['TTL' => 3600]);
    }
}
