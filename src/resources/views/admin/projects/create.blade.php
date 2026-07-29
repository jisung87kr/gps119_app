<x-layouts.admin title="프로젝트 생성 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">새 프로젝트 생성</h1>
                <p class="mt-1 text-sm text-slate-500">프로젝트 정보를 입력하여 새로운 프로젝트를 생성하세요.</p>
            </div>
            <a href="{{ route('admin.projects.index') }}"
               class="sm:ml-auto inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                목록으로
            </a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <form action="{{ route('admin.projects.store') }}" method="POST">
                @csrf

                <div class="p-6 space-y-6">
                    <!-- 프로젝트 이름 -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">
                            프로젝트 이름 <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 설명 -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">
                            설명
                        </label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 시작일 / 종료일 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-slate-700 mb-1.5">
                                시작일 <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   id="start_date"
                                   name="start_date"
                                   value="{{ old('start_date') }}"
                                   required
                                   class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('start_date') border-red-400 @enderror">
                            @error('start_date')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-slate-700 mb-1.5">
                                종료일 <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   id="end_date"
                                   name="end_date"
                                   value="{{ old('end_date') }}"
                                   required
                                   class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('end_date') border-red-400 @enderror">
                            @error('end_date')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- 활성화 여부 -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-2 focus:ring-blue-500/40">
                            <span class="ml-2 text-sm font-medium text-slate-700">프로젝트 활성화</span>
                        </label>
                        <p class="mt-1.5 text-xs text-slate-400">비활성화하면 사용자가 이 프로젝트로 요청을 생성할 수 없습니다.</p>
                    </div>
                </div>

                <!-- 버튼 -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-100">
                    <a href="{{ route('admin.projects.index') }}"
                       class="inline-flex items-center bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                        취소
                    </a>
                    <button type="submit"
                            class="inline-flex items-center bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                        프로젝트 생성
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
