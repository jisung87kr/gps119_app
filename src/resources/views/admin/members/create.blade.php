<x-layouts.admin title="회원 등록 - GPS119 관리자">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">회원 등록</h1>
                <p class="mt-2 text-gray-600">새로운 회원을 등록하고 역할을 부여하세요.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.members') }}" 
                   class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                    목록으로
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Member Registration Form -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('admin.members.store') }}" class="space-y-6">
                @csrf

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            이름 <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            이메일
                        </label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               value="{{ old('email') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="관리자는 이메일로 로그인">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                            연락처
                        </label>
                        <input type="text" 
                               name="phone" 
                               id="phone" 
                               value="{{ old('phone') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="01012345678">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Login Information Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
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
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            비밀번호 <span class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                            비밀번호 확인 <span class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               name="password_confirmation" 
                               id="password_confirmation" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>
                </div>

                <!-- Role Assignment -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">역할 부여</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($roles as $role)
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="roles[]" 
                                       value="{{ $role }}" 
                                       id="role_{{ $role }}"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       {{ in_array($role, old('roles', [])) ? 'checked' : '' }}>
                                <label for="role_{{ $role }}" class="ml-2 block text-sm text-gray-900">
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
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Role Information -->
                <div class="bg-gray-50 rounded-md p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">역할 안내</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>관리자:</strong> 모든 기능에 접근 가능하며, 회원 관리와 구조 요청 관리 권한을 가집니다.</p>
                        <p><strong>구조대원:</strong> 구조 요청을 확인하고 처리할 수 있는 권한을 가집니다.</p>
                        <p><strong>역할 미선택:</strong> 일반 회원으로 등록되어 구조 요청만 생성할 수 있습니다.</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-6">
                    <a href="{{ route('admin.members') }}" 
                       class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                        취소
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                        회원 등록
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>