<?php

namespace Pheye\Payments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RefundedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $refund;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(\Pheye\Payments\Models\Refund $refund)
    {
        $this->refund = $refund;
    }
}
