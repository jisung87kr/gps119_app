<x-layouts.admin title="프로젝트 수정 - GPS119 관리자">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">프로젝트 수정</h1>
            <p class="mt-2 text-gray-600">프로젝트 정보를 수정하세요.</p>
        </div>

        <!-- 현재 상태 정보 -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-blue-900 mb-2">현재 프로젝트 상태</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <span class="text-blue-700">상태:</span>
                            <span class="font-medium text-blue-900">
                                @if($project->status === 'pending') 시작 대기
                                @elseif($project->status === 'active') 진행중
                                @elseif($project->status === 'completed') 완료
                                @else {{ $project->status }}
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="text-blue-700">전체 요청:</span>
                            <span class="font-medium text-blue-900">{{ $project->requests->count() }}건</span>
                        </div>
                        <div>
                            <span class="text-blue-700">생성자:</span>
                            <span class="font-medium text-blue-900">{{ $project->creator->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-blue-700">생성일:</span>
                            <span class="font-medium text-blue-900">{{ $project->created_at->format('Y-m-d') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <form action="{{ route('admin.projects.update', $project->id) }}" method="POST">
                @csrf
                @method('PATCH')

                <!-- 프로젝트 이름 -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        프로젝트 이름 <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name', $project->name) }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Slug (읽기 전용) -->
                <div class="mb-6">
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                        Slug
                    </label>
                    <input type="text"
                           id="slug"
                           value="{{ $project->slug }}"
                           disabled
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500">
                    <p class="mt-1 text-sm text-gray-500">프로젝트 생성 시 자동으로 생성되며 수정할 수 없습니다.</p>
                </div>

                <!-- 설명 -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        설명
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $project->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 시작일 / 종료일 -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                            시작일 <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               id="start_date"
                               name="start_date"
                               value="{{ old('start_date', $project->start_date->format('Y-m-d')) }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('start_date') border-red-500 @enderror">
                        @error('start_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                            종료일 <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               id="end_date"
                               name="end_date"
                               value="{{ old('end_date', $project->end_date->format('Y-m-d')) }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('end_date') border-red-500 @enderror">
                        @error('end_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- 활성화 여부 -->
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $project->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">프로젝트 활성화</span>
                    </label>
                    <p class="mt-1 text-sm text-gray-500">비활성화하면 사용자가 이 프로젝트로 요청을 생성할 수 없습니다.</p>
                </div>

                <!-- 경고 메시지 -->
                @if($project->requests->count() > 0)
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-yellow-900">주의사항</h4>
                                <p class="text-sm text-yellow-700 mt-1">
                                    이 프로젝트에는 {{ $project->requests->count() }}건의 구조요청이 있습니다.
                                    날짜를 변경하면 프로젝트 상태가 자동으로 재계산됩니다.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 버튼 -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.projects.show', $project->id) }}"
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                        취소
                    </a>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                        변경사항 저장
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
