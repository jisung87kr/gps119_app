<x-layouts.admin title="회원 등록 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">회원 등록</h1>
                <p class="mt-1 text-sm text-slate-500">새로운 회원을 등록하고 역할을 부여하세요.</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.members') }}"
                   class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    목록으로
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Member Registration Form -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <form method="POST" action="{{ route('admin.members.store') }}" class="p-6 space-y-6">
                @csrf

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">
                            이름 <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name') }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                               required>
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">
                            이메일
                        </label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email') }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                               placeholder="관리자는 이메일로 로그인">
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 mb-1.5">
                            연락처
                        </label>
                        <input type="text"
                               name="phone"
                               id="phone"
                               value="{{ old('phone') }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                               placeholder="01012345678">
                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Login Information Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>로그인 방법:</strong> 이메일 또는 연락처 중 하나는 필수입니다.
                                관리자는 이메일로, 일반 회원은 연락처로 로그인합니다.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Password Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">
                            비밀번호 <span class="text-red-500">*</span>
                        </label>
                        <input type="password"
                               name="password"
                               id="password"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                               required>
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">
                            비밀번호 확인 <span class="text-red-500">*</span>
                        </label>
                        <input type="password"
                               name="password_confirmation"
                               id="password_confirmation"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400"
                               required>
                    </div>
                </div>

                <!-- Role Assignment -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-3">역할 부여</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($roles as $role)
                            <div class="flex items-center">
                                <input type="checkbox"
                                       name="roles[]"
                                       value="{{ $role }}"
                                       id="role_{{ $role }}"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded"
                                       {{ in_array($role, old('roles', [])) ? 'checked' : '' }}>
                                <label for="role_{{ $role }}" class="ml-2 block text-sm text-slate-700">
                                    @if($role === 'admin')
                                        관리자
                                    @elseif($role === 'rescuer')
                                        구조대원
                                    @else
                                        {{ $role }}
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('roles')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Role Information -->
                <div class="bg-slate-50 rounded-xl p-4">
                    <h4 class="text-sm font-semibold text-slate-900 mb-2">역할 안내</h4>
                    <div class="text-sm text-slate-600 space-y-1">
                        <p><strong>관리자:</strong> 모든 기능에 접근 가능하며, 회원 관리와 구조 요청 관리 권한을 가집니다.</p>
                        <p><strong>구조대원:</strong> 구조 요청을 확인하고 처리할 수 있는 권한을 가집니다.</p>
                        <p><strong>역할 미선택:</strong> 일반 회원으로 등록되어 구조 요청만 생성할 수 있습니다.</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-2 pt-6 border-t border-slate-100">
                    <a href="{{ route('admin.members') }}"
                       class="inline-flex items-center bg-white text-slate-700 border border-slate-200 px-5 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                        취소
                    </a>
                    <button type="submit"
                            class="inline-flex items-center bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                        회원 등록
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
