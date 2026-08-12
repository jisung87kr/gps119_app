<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 행사 «운영진» 사전명단 CSV 일괄 등록.
 *
 * 운영 흐름(2026-08-12 확정):
 *   ① 프로젝트 생성 → ②-1 관리자가 «운영진» 명단을 올린다(이 클래스)
 *                  → ②-2 참가자·운영진 모두 «같은 입장 QR» 로 들어온다.
 *                        명단에 있으면 그 역할, 없으면 일반 참가자.
 *
 * 컬럼: 이름, 전화번호, 역할  (헤더 행 유무 무관)
 *
 * 설계상 지켜야 하는 것 3가지 —
 *  1. **역할 배정의 단일 writer 는 EventParticipantService::assignRole** 이다.
 *     여기서 event_participants 를 직접 쓰지 않는다(멱등 upsert 가 거기 있다).
 *  2. **전화번호는 조회 «전에» 정규화한다.** User::setPhoneAttribute 가 숫자만 저장하므로
 *     원문 `010-1234-5678` 로 조회하면 이미 있는 `01012345678` 을 못 찾고
 *     DB unique 제약에서 터진다 — 같은 사람을 두 번 만들려다 500 이 난다.
 *  3. **행 단위 처리.** 한 행이 틀렸다고 전체를 롤백하지 않는다(100명 중 1명 오타로
 *     99명이 안 들어가면 현장에서 못 쓴다). 단 «한 행 안의» 회원생성+역할배정은 원자적이다.
 */
class ParticipantImportService
{
    /** 데이터 행 상한. 넘으면 조용히 자르지 않고 거부한다. */
    public const MAX_ROWS = 1000;

    /** 리포트에 담을 실패 행 최대 개수(세션 플래시 크기 방어). 초과분은 개수만 알린다. */
    public const MAX_REPORTED_ERRORS = 100;

    public function __construct(private EventParticipantService $participants) {}

    /**
     * CSV 원문 → 리포트.
     *
     * @return array{total:int,success:int,joined:int,pending:int,controllers:int,failed:int,errors:list<array{line:int,reason:string,raw:string}>,hidden_errors:int}
     *
     * @throws RuntimeException 행 수 상한 초과 / 빈 파일 (전량 거부)
     */
    public function import(Project $project, string $csv): array
    {
        $rows = $this->parse($csv);

        if ($rows === []) {
            throw new RuntimeException('CSV에 처리할 행이 없습니다. 이름·전화번호·역할 순서로 입력했는지 확인해 주세요.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException(sprintf(
                '한 번에 올릴 수 있는 행은 최대 %d행입니다. (올린 파일: %d행) 파일을 나눠서 올려주세요.',
                self::MAX_ROWS,
                count($rows)
            ));
        }

        $report = [
            'total' => count($rows),
            'success' => 0,
            // 이미 회원이라 «지금» 역할이 붙은 사람 / 명단에만 올라간 사람.
            'joined' => 0,
            'pending' => 0,
            // 🔴 상황실(controller)은 전원의 실시간 위치와 신고자 연락처를 보는 자리다.
            //    엑셀로 부여하는 것을 허용하기로 했으므로(2026-08-12 결정), 최소한
            //    «몇 명에게 줬는지»는 결과 화면에서 바로 보이게 한다. 스프레드시트
            //    붙여넣기 사고는 사후에 발견할 단서가 없으면 영영 발견되지 않는다.
            'controllers' => 0,
            'failed' => 0,
            'errors' => [],
            'hidden_errors' => 0,
        ];

        foreach ($rows as $row) {
            try {
                $result = $this->importRow($project, $row);
                $report['success']++;
                $report[$result['outcome']]++;
                if ($result['role'] === EventRole::CONTROLLER) {
                    $report['controllers']++;
                }
            } catch (RuntimeException $e) {
                $report['failed']++;
                if (count($report['errors']) < self::MAX_REPORTED_ERRORS) {
                    $report['errors'][] = [
                        'line' => $row['line'],
                        'reason' => $e->getMessage(),
                        'raw' => $row['raw'],
                    ];
                } else {
                    $report['hidden_errors']++;
                }
            }
        }

        return $report;
    }

    /**
     * 한 행 처리: 검증 → 회원 조회/생성 → 역할 upsert.
     *
     * 🔑 계정을 «만들지 않는다». 예전에는 여기서 User 를 생성했는데, 그러면 그 사람은
     *    임의 비밀번호라 로그인할 수 없고(재설정은 이메일 기반), 전화번호가 점유돼
     *    본인이 회원가입도 못 한다 — 명단은 들어가는데 사람이 못 들어온다.
     *    명단만 남기고, 본인이 입장할 때 역할이 붙는다(EventParticipantService::joinByCode).
     *
     * 이미 회원인 사람은 그 자리에서 역할을 부여한다. 기다릴 이유가 없고,
     * 「업로드했는데 아직 아무 일도 안 일어난다」는 인상을 주지 않는다.
     *
     * @param  array{line:int,raw:string,name:string,phone:string,role:string}  $row
     * @return array{outcome:'joined'|'pending',role:EventRole}
     *
     * @throws RuntimeException 행 단위 검증 실패 (호출부가 리포트로 수집)
     */
    private function importRow(Project $project, array $row): array
    {
        $name = trim($row['name']);
        if ($name === '') {
            throw new RuntimeException('이름이 비어 있습니다.');
        }

        // 🔑 조회·중복판정 «전에» 정규화. (클래스 주석 2번)
        $phone = self::normalizePhone($row['phone']);
        if ($phone === null) {
            // 「비어 있다」와 「숫자가 하나도 없다」를 구분한다. 100행을 고치는 사람에게
            // 셀에 글자가 있는데 «비어 있습니다» 는 찾을 수 없는 단서다.
            throw new RuntimeException(trim($row['phone']) === ''
                ? '전화번호가 비어 있습니다.'
                : "전화번호에 숫자가 없습니다: {$row['phone']}");
        }
        if (! self::isValidPhone($phone)) {
            throw new RuntimeException("전화번호 형식이 올바르지 않습니다: {$row['phone']}");
        }

        $role = self::resolveRole($row['role']);
        if ($role === null) {
            throw new RuntimeException("알 수 없는 역할입니다: {$row['role']}");
        }

        return DB::transaction(function () use ($project, $name, $phone, $role) {
            // 명단은 «전화번호» 기준 1행. 재업로드하면 이름·역할이 최신값으로 덮인다.
            $roster = EventRoster::updateOrCreate(
                ['project_id' => $project->id, 'phone' => $phone],
                ['name' => $name, 'role' => $role],
            );

            $user = User::where('phone', $phone)->first();

            if (! $user) {
                return ['outcome' => 'pending', 'role' => $role];
            }

            // 이미 있는 회원의 «이름»은 CSV 로 덮지 않는다 — 본인이 정한 값이 우선이다.
            $this->participants->assignRole($project, $user, $role, ParticipantStatus::ACTIVE);

            $roster->forceFill([
                'user_id' => $user->id,
                'claimed_at' => $roster->claimed_at ?? now(),
            ])->save();

            return ['outcome' => 'joined', 'role' => $role];
        });
    }

    /**
     * CSV 원문 → 데이터 행 목록 (순수 함수).
     *
     * - UTF-8 BOM 제거, CP949(엑셀 기본 "CSV") 자동 변환
     * - 헤더 행 자동 감지 후 스킵
     * - 완전 빈 행 스킵
     * - line 은 «파일의 물리 행 번호»(1부터, 헤더 포함) — 엑셀에서 그 줄을 바로 찾을 수 있어야 한다.
     *
     * @return list<array{line:int,raw:string,name:string,phone:string,role:string}>
     */
    public function parse(string $csv): array
    {
        $csv = self::decode($csv);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        $line = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;

            // fgetcsv 는 빈 줄을 [null] 로 준다.
            if ($cells === [null]) {
                continue;
            }

            $cells = array_map(fn ($c) => trim((string) $c), $cells);

            if (implode('', $cells) === '') {
                continue;
            }

            if ($line === 1 && self::looksLikeHeader($cells)) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'raw' => implode(', ', array_slice($cells, 0, 3)),
                'name' => $cells[0] ?? '',
                'phone' => $cells[1] ?? '',
                'role' => $cells[2] ?? '',
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * BOM 제거 + 인코딩 정규화.
     *
     * 엑셀의 "CSV UTF-8" 은 BOM 붙은 UTF-8, 그냥 "CSV" 는 CP949 를 뱉는다.
     * 후자를 그대로 읽으면 한글 이름이 전부 깨진 채 회원이 생성되므로 여기서 흡수한다.
     */
    private static function decode(string $csv): string
    {
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        if (str_starts_with($csv, $bom)) {
            return substr($csv, strlen($bom));
        }

        if (! mb_check_encoding($csv, 'UTF-8')) {
            return (string) mb_convert_encoding($csv, 'UTF-8', 'CP949');
        }

        return $csv;
    }

    /**
     * 첫 행이 헤더인지. 컬럼명 후보가 하나라도 그대로 들어있으면 헤더로 본다.
     * ("김이름, 01012345678, 참가자" 같은 데이터 행이 헤더로 오인되지 않게 완전일치만 본다.)
     *
     * @param  list<string>  $cells
     */
    private static function looksLikeHeader(array $cells): bool
    {
        $keywords = ['이름', '성명', 'name', '전화번호', '연락처', 'phone', '역할', 'role'];

        foreach ($cells as $cell) {
            if (in_array(mb_strtolower($cell), $keywords, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 전화번호 정규화 — 숫자만. 빈 값이면 null (순수 함수).
     *
     * User::setPhoneAttribute 와 «같은» 규칙이어야 조회가 맞는다.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $raw);

        return $digits === '' ? null : $digits;
    }

    /**
     * 국내 유선(9)~휴대(10~11)자리, 0 으로 시작. 엑셀이 앞의 0 을 날린 경우도 여기서 걸린다.
     */
    public static function isValidPhone(string $digits): bool
    {
        return (bool) preg_match('/^0\d{8,10}$/', $digits);
    }

    /**
     * 역할 문자열 → EventRole (순수 함수).
     *
     * enum value(`paramedic`) 와 한글 라벨(`구급대`) 둘 다 받는다 — 현장 명단에는 한글로 쓴다.
     * 라벨 안의 공백·괄호 형태 차이는 무시한다(`자원봉사자 (코스)` 도 매칭).
     * 빈 값은 «참가자»로 본다(역할 열을 비워둔 명단이 흔하다).
     */
    public static function resolveRole(?string $raw): ?EventRole
    {
        $key = self::roleKey((string) $raw);

        if ($key === '') {
            return EventRole::PARTICIPANT;
        }

        foreach (EventRole::cases() as $role) {
            if ($key === self::roleKey($role->value) || $key === self::roleKey($role->label())) {
                return $role;
            }
        }

        return null;
    }

    /** 역할 비교용 정규화 키: 소문자 + 공백 제거. */
    private static function roleKey(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtolower(trim($value)));
    }

    /**
     * 템플릿 CSV 본문 (UTF-8 BOM 포함).
     *
     * BOM 이 없으면 엑셀이 한글을 깨뜨린다 — 이 저장소의 다른 CSV 출력과 같은 규칙.
     */
    public static function templateCsv(): string
    {
        $handle = fopen('php://memory', 'r+');

        fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, ['이름', '전화번호', '역할']);
        fputcsv($handle, ['홍길동', '010-1234-5678', '구급대']);
        fputcsv($handle, ['김운영', '010-2222-3333', '운영진']);
        fputcsv($handle, ['이참가', '01033334444', '참가자']);

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
