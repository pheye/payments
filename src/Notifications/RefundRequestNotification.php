<?php

namespace Pheye\Payments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Pheye\Payments\Models\Refund;
use Log;

class RefundRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $refund;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
    }

    /* public function routeNotificationForMail() */
    /* { */
    /*     return env('ADMIN_EMAIL'); */
    /* } */

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $refund = $this->refund;
        return (new MailMessage)
                    ->line("{$refund->payment->client_email} request refunding \${$refund->amount} on {$refund->payment->number}")
                    ->action('Handle Refunding', env('APP_URL') . '/backend');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'refund_id' => $this->refund->id
        ];
    }
}
