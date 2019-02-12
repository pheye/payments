<?php

namespace Pheye\Payments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CreditUsedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $credit;
    public $client;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $credit, array $client)
    {
        $this->credit = $credit;
        $this->client = $client;
    }
}
