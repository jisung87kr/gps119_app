<?php

namespace App\Jobs;

use App\Models\LocationPing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * location_pings INSERT 큐 잡 (SPEC-04c).
 *
 * 고빈도 위치 ping 의 이력 적재를 요청 응답 경로에서 분리한다(append-only).
 * 캐시 갱신·브로드캐스트는 LocationService 가 동기로 처리하고, 영구 적재만 여기서 비동기.
 */
class PersistLocationPing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $attributes  location_pings 행 데이터
     */
    public function __construct(
        public array $attributes
    ) {}

    public function handle(): void
    {
        LocationPing::create($this->attributes);
    }
}
