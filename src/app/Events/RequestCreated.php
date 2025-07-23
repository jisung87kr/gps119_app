<?php

namespace App\Events;

use App\Models\Request;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Request $request;

    /**
     * Create a new event instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('requests'),
            new PrivateChannel('rescuers'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
            'user' => [
                'id' => $this->request->user->id,
                'name' => $this->request->user->name,
                'phone' => $this->request->user->phone,
            ],
            'latitude' => $this->request->latitude,
            'longitude' => $this->request->longitude,
            'address' => $this->request->address,
            'description' => $this->request->description,
            'status' => $this->request->status->value,
            'priority' => $this->request->priority->value,
            'contact_phone' => $this->request->contact_phone,
            'requested_at' => $this->request->requested_at->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'request.created';
    }
}
