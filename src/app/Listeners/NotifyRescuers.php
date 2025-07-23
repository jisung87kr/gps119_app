<?php

namespace App\Listeners;

use App\Events\RequestCreated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue; // 임시로 큐 사용 중지
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyRescuers
{
     //use InteractsWithQueue; // 임시로 큐 사용 중지

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RequestCreated $event): void
    {
        $request = $event->request;

        Log::info('New rescue request created', [
            'request_id' => $request->id,
            'user_id' => $request->user_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'description' => $request->description,
        ]);

        $rescuers = User::role('rescuer')->get();

        foreach ($rescuers as $rescuer) {
            $this->sendNotificationToRescuer($rescuer, $request);
        }

        $admins = User::role('admin')->get();

        foreach ($admins as $admin) {
            $this->sendNotificationToRescuer($admin, $request);
        }

        // 디스코드 알림
        $url = env('DISCORD_WEBHOOK_URL');
        $requestUrl = config('app.url') . '/requests/' . $request->id;
        $message = "[{$request->description}] 공유됨\n" .
            "요청자: {$request->user->name}\n" .
            "연락처: {$request->user->formatted_phone}\n" .
            "위치정보: {$request->latitude}/{$request->longitude}\n" .
            "주소: {$request->address}\n" .
            "{$requestUrl}";

         $data = [
             'content' => "{$message}",
             'username' => 'gps119 Bot',
             'avatar_url' => 'https://example.com/avatar.png',
         ];

         $options = [
             'http' => [
                 'header' => "Content-Type: application/json\r\n",
                 'method' => 'POST',
                 'content' => json_encode($data),
             ],
         ];
         $context = stream_context_create($options);
         file_get_contents($url, false, $context);

        Log::info('NotifyRescuers listener completed', [
            'request_id' => $request->id,
        ]);
    }

    /**
     * Send notification to a specific rescuer.
     */
    private function sendNotificationToRescuer(User $rescuer, $request): void
    {
        Log::info('Notifying rescuer about new request', [
            'rescuer_id' => $rescuer->id,
            'rescuer_name' => $rescuer->name,
            'request_id' => $request->id,
        ]);

        // TODO: Implement actual notification logic (email, SMS, push notification, etc.)
        // For now, we just log the notification
    }

    /**
     * Handle a job failure.
     */
    public function failed(RequestCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to notify rescuers about new request', [
            'request_id' => $event->request->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
