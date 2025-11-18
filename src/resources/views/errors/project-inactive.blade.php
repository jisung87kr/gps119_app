<x-layouts.app>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <svg class="mx-auto h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    프로젝트가 비활성화되었습니다
                </h2>
                <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-gray-700">
                        <strong>{{ $project->name }}</strong>
                    </p>
                    @if($project->description)
                        <p class="text-sm text-gray-600 mt-1">{{ $project->description }}</p>
                    @endif
                </div>
                <div class="mt-4">
                    @if($project->status === 'completed' && $project->end_date->isPast())
                        <p class="text-gray-600">
                            이 프로젝트는 <strong>{{ $project->end_date->format('Y년 m월 d일') }}</strong>에 종료되었습니다.
                        </p>
                    @elseif($project->status === 'pending')
                        <p class="text-gray-600">
                            이 프로젝트는 <strong>{{ $project->start_date->format('Y년 m월 d일') }}</strong>에 시작됩니다.
                        </p>
                    @elseif(!$project->is_active)
                        <p class="text-gray-600">
                            이 프로젝트는 현재 비활성화 상태입니다.
                        </p>
                    @endif
                </div>
                <div class="mt-6 space-y-3">
                    <a href="{{ route('request.create') }}" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        일반 구조요청 페이지로 이동
                    </a>
                    <a href="{{ route('dashboard') }}" class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        대시보드로 이동
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
