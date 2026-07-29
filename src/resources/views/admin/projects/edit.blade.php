<x-layouts.admin title="프로젝트 수정 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">프로젝트 수정</h1>
                <p class="mt-1 text-sm text-slate-500">프로젝트 정보를 수정하세요.</p>
            </div>
            <a href="{{ route('admin.projects.show', $project->id) }}"
               class="sm:ml-auto inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                상세로
            </a>
        </div>

        <!-- 현재 상태 정보 -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
            <h3 class="text-base font-semibold text-slate-900 mb-4">현재 프로젝트 상태</h3>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">상태</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">
                        @if($project->status === 'pending') 시작 대기
                        @elseif($project->status === 'active') 진행중
                        @elseif($project->status === 'completed') 완료
                        @else {{ $project->status }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">전체 요청</dt>
                    <dd class="mt-0.5 font-medium text-slate-800 tabular-nums">{{ $project->requests->count() }}건</dd>
                </div>
                <div>
                    <dt class="text-slate-500">생성자</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $project->creator->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">생성일</dt>
                    <dd class="mt-0.5 font-medium text-slate-800 tabular-nums">{{ $project->created_at->format('Y-m-d') }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <form action="{{ route('admin.projects.update', $project->id) }}" method="POST">
                @csrf
                @method('PATCH')

                <div class="p-6 space-y-6">
                    <!-- 프로젝트 이름 -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">
                            프로젝트 이름 <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $project->name) }}"
                               required
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Slug (읽기 전용) -->
                    <div>
                        <label for="slug" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Slug
                        </label>
                        <input type="text"
                               id="slug"
                               value="{{ $project->slug }}"
                               disabled
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-sm text-slate-400 cursor-not-allowed">
                        <p class="mt-1.5 text-xs text-slate-400">프로젝트 생성 시 자동으로 생성되며 수정할 수 없습니다.</p>
                    </div>

                    <!-- 설명 -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">
                            설명
                        </label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 @error('description') border-red-400 @enderror">{{ old('description', $project->description) }}</textarea>
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
                                   value="{{ old('start_date', $project->start_date->format('Y-m-d')) }}"
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
                                   value="{{ old('end_date', $project->end_date->format('Y-m-d')) }}"
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
                                   {{ old('is_active', $project->is_active) ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-2 focus:ring-blue-500/40">
                            <span class="ml-2 text-sm font-medium text-slate-700">프로젝트 활성화</span>
                        </label>
                        <p class="mt-1.5 text-xs text-slate-400">비활성화하면 사용자가 이 프로젝트로 요청을 생성할 수 없습니다.</p>
                    </div>

                    <!-- 경고 메시지 -->
                    @if($project->requests->count() > 0)
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-amber-900">주의사항</h4>
                                    <p class="text-sm text-amber-700 mt-1">
                                        이 프로젝트에는 {{ $project->requests->count() }}건의 구조요청이 있습니다.
                                        날짜를 변경하면 프로젝트 상태가 자동으로 재계산됩니다.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- 버튼 -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-100">
                    <a href="{{ route('admin.projects.show', $project->id) }}"
                       class="inline-flex items-center bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                        취소
                    </a>
                    <button type="submit"
                            class="inline-flex items-center bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                        변경사항 저장
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
