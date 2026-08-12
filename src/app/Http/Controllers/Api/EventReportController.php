<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\LocationPing;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 행사 기록 다운로드 (BE-4.1, SPEC-06b 리포트).
 *
 * 가드: event.role:controller (+시스템 admin). 전부 스트리밍 CSV(메모리 안전) + UTF-8 BOM(엑셀 한글).
 * 보존기간 자동파기·아카이브는 범위 외(Q2 결정 대기).
 */
class EventReportController extends Controller
{
    /**
     * GET /api/events/{id}/report/requests.csv — 신고 리포트.
     */
    public function requests(int $id): StreamedResponse
    {
        $project = Project::findOrFail($id);

        $header = [
            'ID', '유형', '우선순위', '상태',
            '요청시각', '응답시각', '완료시각', '처리시간(분)',
            '위도', '경도', '주소', '신고자', '연락처', '담당자',
        ];

        return $this->stream($this->filename($project, 'requests'), $header, function ($file) use ($project) {
            RescueRequest::where('project_id', $project->id)
                ->with(['user:id,name,phone', 'activeDispatch.paramedic:id,name'])
                ->orderBy('requested_at')
                ->chunk(500, function ($rows) use ($file) {
                    foreach ($rows as $r) {
                        fputcsv($file, [
                            $r->id,
                            $r->type?->label() ?? '',
                            $r->priority?->label() ?? '',
                            $r->status?->label() ?? '',
                            $this->dt($r->requested_at),
                            $this->dt($r->responded_at),
                            $this->dt($r->completed_at),
                            $this->durationMin($r->requested_at, $r->completed_at),
                            $r->latitude,
                            $r->longitude,
                            $r->address ?? '',
                            $r->user->name ?? '',
                            $r->user->phone ?? '',
                            $r->activeDispatch?->paramedic?->name ?? '',
                        ]);
                    }
                });
        });
    }

    /**
     * GET /api/events/{id}/report/dispatches.csv — 지령 타임라인.
     */
    public function dispatches(int $id): StreamedResponse
    {
        $project = Project::findOrFail($id);

        // 「구분」이 없으면 한 신고에 지령이 여러 건일 때 사후 분석에서 «누가 그 환자를
        // 책임졌는지»를 복원할 수 없다 — 다중 배차(ADR-0007 D4)의 핵심 정보다.
        $header = [
            '지령ID', '신고ID', '구분', '구급대원', '발령자', '상태',
            '배정시각', '수락시각', '출동시각', '도착시각', '완료시각', '거절시각',
            '거절사유', '메모',
        ];

        return $this->stream($this->filename($project, 'dispatches'), $header, function ($file) use ($project) {
            Dispatch::where('project_id', $project->id)
                ->with(['paramedic:id,name', 'assignedBy:id,name'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($file) {
                    foreach ($rows as $d) {
                        fputcsv($file, [
                            $d->id,
                            $d->request_id,
                            $d->is_primary ? '주담당' : '보조',
                            $d->paramedic->name ?? '',
                            $d->assignedBy->name ?? '',
                            $d->status?->label() ?? '',
                            $this->dt($d->assigned_at),
                            $this->dt($d->accepted_at),
                            $this->dt($d->en_route_at),
                            $this->dt($d->arrived_at),
                            $this->dt($d->completed_at),
                            $this->dt($d->rejected_at),
                            $d->reject_reason ?? '',
                            $d->note ?? '',
                        ]);
                    }
                });
        });
    }

    /**
     * GET /api/events/{id}/report/tracks.csv — 동선(location_pings, 시간순).
     *
     * 대용량 가능 → 스트리밍 필수. 매우 큰 행사는 후속 비동기 생성(08, Q4 규모결정 후)으로 전환 예정.
     */
    public function tracks(int $id): StreamedResponse
    {
        $project = Project::findOrFail($id);

        $header = ['참가자ID', '참가자', '역할', '위도', '경도', '정확도(m)', '기록시각'];

        // 역할 라벨 조회용 맵(참가자 1쿼리) — ping 마다 조회 방지
        $participants = $project->participants()->with('user:id,name')->get()
            ->keyBy('user_id');

        return $this->stream($this->filename($project, 'tracks'), $header, function ($file) use ($project, $participants) {
            LocationPing::where('project_id', $project->id)
                ->orderBy('recorded_at')
                ->chunk(1000, function ($rows) use ($file, $participants) {
                    foreach ($rows as $p) {
                        $participant = $participants->get($p->user_id);
                        fputcsv($file, [
                            $p->user_id,
                            $participant?->user?->name ?? '',
                            $participant?->role?->label() ?? '',
                            $p->latitude,
                            $p->longitude,
                            $p->accuracy ?? '',
                            $this->dt($p->recorded_at),
                        ]);
                    }
                });
        });
    }

    // ── 헬퍼 ────────────────────────────────────────────────────

    /**
     * BOM + 헤더행 + 본문 콜백을 스트리밍 CSV 로 반환.
     */
    private function stream(string $filename, array $header, callable $writeRows): StreamedResponse
    {
        $callback = function () use ($header, $writeRows) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM(엑셀 한글 깨짐 방지)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $header);
            $writeRows($file);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filename(Project $project, string $kind): string
    {
        $slug = Str::slug($project->name) ?: 'event';

        return sprintf('%s_%s_%s.csv', $slug, $kind, now()->format('Y-m-d'));
    }

    private function dt($value): string
    {
        return $value ? $value->format('Y-m-d H:i:s') : '';
    }

    /**
     * 처리시간(분): 접수→완료. 미완료면 빈 값.
     */
    private function durationMin($from, $to): string
    {
        if (! $from || ! $to) {
            return '';
        }

        return (string) $from->diffInMinutes($to);
    }
}
