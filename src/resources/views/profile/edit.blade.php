<x-layouts.app>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="w-full max-w-2xl mx-auto mt-8">
            <div class="">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">프로필 수정</h1>
                <p class="text-xl text-gray-600 mb-6">개인 정보를 수정하세요.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                @if(session('status'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                        <input type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                               id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                        @error('name')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                               id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required placeholder="010-1234-5678">
                        @error('phone')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                            변경사항 저장
                        </button>
                        <a href="{{ route('profile.show') }}" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 text-center">
                            취소
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
