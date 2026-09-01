<?php

namespace App\Actions\Fortify;

use App\Enums\ConsentType;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private ConsentService $consents) {}

    /**
     * Validate and create a newly registered user.
     *
     * 🔴 **필수 약관 동의 없이는 계정을 만들지 않는다.** 위치정보법은 개인위치정보
     *    수집에 위치기반서비스 약관의 «별도» 동의를 요구한다.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $required = array_map(fn (ConsentType $t) => $t->value, ConsentType::required());

        Validator::make($input, [
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            // 필수 항목이 «전부» 들어와야 한다. 하나만 체크한 제출을 통과시키지 않는다.
            'consents' => ['required', 'array'],
            'consents.*' => [Rule::in($required)],
        ], [], [
            'consents' => '약관 동의',
        ])->after(function ($validator) use ($input, $required) {
            $given = (array) ($input['consents'] ?? []);
            $missing = array_diff($required, $given);

            if ($missing) {
                $validator->errors()->add('consents', '필수 약관에 모두 동의해야 가입할 수 있습니다.');
            }
        })->validate();

        // 🔑 계정과 동의는 «같이» 생기거나 «같이» 안 생긴다. 중간에 실패해서
        //    동의 없는 계정이 남으면, 그 계정은 위치를 수집할 근거가 없다.
        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['phone'],
                'phone' => $input['phone'],
                'password' => Hash::make($input['password']),
            ]);

            $this->consents->record($user, ConsentType::required(), Request::ip());

            return $user;
        });
    }
}
