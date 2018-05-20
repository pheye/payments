<?php

namespace Pheye\Payments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PayedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $payment;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(\Pheye\Payments\Models\Payment $payment)
    {
        $this->payment = $payment;
    }
}
