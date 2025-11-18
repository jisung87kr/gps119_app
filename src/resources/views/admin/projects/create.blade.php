<x-layouts.admin title="프로젝트 생성 - GPS119 관리자">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">새 프로젝트 생성</h1>
            <p class="mt-2 text-gray-600">프로젝트 정보를 입력하여 새로운 프로젝트를 생성하세요.</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <form action="{{ route('admin.projects.store') }}" method="POST">
                @csrf

                <!-- 프로젝트 이름 -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        프로젝트 이름 <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 설명 -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        설명
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
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
                               value="{{ old('start_date') }}"
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
                               value="{{ old('end_date') }}"
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
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">프로젝트 활성화</span>
                    </label>
                    <p class="mt-1 text-sm text-gray-500">비활성화하면 사용자가 이 프로젝트로 요청을 생성할 수 없습니다.</p>
                </div>

                <!-- 버튼 -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.projects.index') }}"
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                        취소
                    </a>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                        프로젝트 생성
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
