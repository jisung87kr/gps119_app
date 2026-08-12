<x-layouts.admin title="회원 정보 수정 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">
        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">회원 정보 수정</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $member->name }}님의 정보를 수정하세요.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <form method="POST" action="{{ route('admin.members.update', $member->id) }}" class="p-6 space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">이름</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $member->name) }}"
                               required
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">이메일 (선택사항)</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $member->email) }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('email') border-red-400 @enderror"
                               placeholder="admin@example.com">
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 mb-1.5">연락처 (선택사항)</label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="{{ old('phone', $member->raw_phone) }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('phone') border-red-400 @enderror"
                               placeholder="010-1234-5678">
                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">역할</label>
                        <div class="space-y-2">
                            @foreach($roles as $role)
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           name="roles[]"
                                           value="{{ $role }}"
                                           {{ $member->hasRole($role) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded">
                                    <span class="ml-2 text-sm text-slate-700">
                                        @if($role === 'admin') 관리자
                                        @else {{ $role }}
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('roles')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- 비밀번호 변경 섹션 (소셜 로그인이 아닌 경우에만) -->
                @if(!$member->provider)
                    <div class="pt-6 border-t border-slate-100">
                        <h3 class="text-base font-semibold text-slate-900 mb-1">비밀번호 변경</h3>
                        <p class="text-sm text-slate-500 mb-4">비밀번호를 변경하려면 아래 필드를 입력하세요. 변경하지 않으려면 비워두세요.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">새 비밀번호 (선택사항)</label>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('password') border-red-400 @enderror"
                                       placeholder="최소 8자 이상">
                                @error('password')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">새 비밀번호 확인</label>
                                <input type="password"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                                       placeholder="비밀번호를 다시 입력하세요">
                            </div>
                        </div>
                    </div>
                @else
                    <div class="pt-6 border-t border-slate-100">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-amber-800">소셜 로그인 계정</h3>
                                    <p class="mt-1 text-sm text-amber-700">
                                        이 회원은 {{ ucfirst($member->provider) }} 계정으로 가입했습니다. 소셜 로그인 계정은 비밀번호를 변경할 수 없습니다.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-blue-800">주의사항</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>관리자 역할을 제거하면 관리자 페이지에 접근할 수 없습니다.</li>
                                    <li>구조대 역할을 부여하면 구조 요청을 처리할 수 있습니다.</li>
                                    <li>이메일과 연락처는 중복될 수 없습니다.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-6 border-t border-slate-100">
                    <button type="submit"
                            class="flex-1 inline-flex items-center justify-center bg-blue-600 text-white py-2.5 px-4 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                        변경사항 저장
                    </button>
                    <a href="{{ route('admin.members.show', $member->id) }}"
                       class="flex-1 inline-flex items-center justify-center bg-white text-slate-700 border border-slate-200 py-2.5 px-4 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors text-center">
                        취소
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
