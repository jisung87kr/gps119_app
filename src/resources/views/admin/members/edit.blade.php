<x-layouts.admin title="회원 정보 수정 - GPS119 관리자">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">회원 정보 수정</h1>
            <p class="mt-2 text-gray-600">{{ $member->name }}님의 정보를 수정하세요.</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('admin.members.update', $member->id) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $member->name) }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">이메일 (선택사항)</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $member->email) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                               placeholder="admin@example.com">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">연락처 (선택사항)</label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="{{ old('phone', $member->raw_phone) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                               placeholder="010-1234-5678">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">역할</label>
                        <div class="space-y-2">
                            @foreach($roles as $role)
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           name="roles[]"
                                           value="{{ $role }}"
                                           {{ $member->hasRole($role) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">
                                        @if($role === 'admin') 관리자
                                        @elseif($role === 'rescuer') 구조대
                                        @else {{ $role }}
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('roles')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- 비밀번호 변경 섹션 (소셜 로그인이 아닌 경우에만) -->
                @if(!$member->provider)
                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">비밀번호 변경</h3>
                        <p class="text-sm text-gray-600 mb-4">비밀번호를 변경하려면 아래 필드를 입력하세요. 변경하지 않으려면 비워두세요.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호 (선택사항)</label>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror"
                                       placeholder="최소 8자 이상">
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호 확인</label>
                                <input type="password"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="비밀번호를 다시 입력하세요">
                            </div>
                        </div>
                    </div>
                @else
                    <div class="pt-6 border-t border-gray-200">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">소셜 로그인 계정</h3>
                                    <p class="mt-1 text-sm text-yellow-700">
                                        이 회원은 {{ ucfirst($member->provider) }} 계정으로 가입했습니다. 소셜 로그인 계정은 비밀번호를 변경할 수 없습니다.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">주의사항</h3>
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

                <div class="flex gap-4">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                        변경사항 저장
                    </button>
                    <a href="{{ route('admin.members.show', $member->id) }}" 
                       class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 text-center">
                        취소
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>