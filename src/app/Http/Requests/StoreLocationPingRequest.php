<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 위치 ping 검증 (SPEC-06b).
 *
 * lat∈[-90,90], lng∈[-180,180], accuracy/heading/speed 정수≥0, heading≤359,
 * recorded_at ≤ now(미래 거부). 인가는 라우트 미들웨어(event.member)가 담당.
 */
class StoreLocationPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 인가는 event.member 미들웨어
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'heading' => ['nullable', 'integer', 'min:0', 'max:359'],
            'speed' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'recorded_at.before_or_equal' => '기록 시각은 미래일 수 없습니다.',
        ];
    }
}
