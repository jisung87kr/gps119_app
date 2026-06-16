<x-layouts.app>
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Page Header -->
        <div class="mb-10 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">계정 삭제</h1>
                <p class="text-sm text-slate-500 font-medium mt-1 text-red-600/70">계정을 영구적으로 삭제합니다. 이 작업은 되돌릴 수 없습니다.</p>
            </div>
            <a href="{{ route('profile.show') }}" class="text-xs font-bold text-slate-400 hover:text-slate-900 transition-colors">돌아가기</a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-3xl border border-red-100 shadow-xl shadow-red-900/5 overflow-hidden">
                <div class="p-8 sm:p-10">
                    <!-- Warning Section -->
                    <div class="bg-red-50 rounded-2xl p-6 border border-red-100 mb-8">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" stroke-width="2.5"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-red-900 uppercase tracking-widest mb-2">Warning</h3>
                                <ul class="text-[11px] font-bold text-red-700/80 space-y-1.5 list-disc list-inside">
                                    <li>모든 개인 데이터와 구조 요청 기록이 영구적으로 삭제됩니다.</li>
                                    <li>삭제된 데이터는 어떤 방법으로도 복구할 수 없습니다.</li>
                                    <li>탈퇴 후 동일한 연락처로 재가입은 가능하나, 이전 데이터는 연동되지 않습니다.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Profile to be deleted summary -->
                    <div class="mb-10 grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Account</p>
                            <p class="text-sm font-bold text-slate-900">{{ auth()->user()->name }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Requests</p>
                            <p class="text-sm font-bold text-slate-900">{{ auth()->user()->requests()->count() }} 건</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-8">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label for="password" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">본인 확인 비밀번호</label>
                            <input type="password" 
                                   class="appearance-none block w-full px-4 py-3.5 border border-slate-200 rounded-2xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-red-500/10 focus:border-red-500 transition-all text-sm font-bold text-slate-900"
                                   id="password" name="password" required placeholder="현재 비밀번호를 입력하세요">
                            @error('password')
                                <p class="text-red-500 text-[10px] font-bold mt-2 ml-1 uppercase tracking-tight">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-start gap-3 px-1">
                            <div class="flex items-center h-5">
                                <input id="confirm_delete" name="confirm_delete" type="checkbox" required
                                       class="h-4 w-4 text-red-600 focus:ring-red-500 border-slate-300 rounded-md">
                            </div>
                            <label for="confirm_delete" class="text-[11px] font-bold text-slate-500 leading-tight select-none">
                                모든 내용을 확인했으며, 데이터 영구 삭제에 동의합니다.
                            </label>
                        </div>

                        <div class="pt-4 flex flex-col sm:flex-row gap-3">
                            <button type="submit" 
                                    class="flex-1 py-4 px-6 bg-red-600 text-white font-black text-sm rounded-2xl shadow-lg shadow-red-500/20 hover:bg-red-700 transition-all active:scale-[0.98] uppercase tracking-widest"
                                    onclick="return confirm('정말로 계정을 삭제하시겠습니까?')">
                                Delete Account
                            </button>
                            <a href="{{ route('profile.show') }}" class="flex-1 py-4 px-6 bg-slate-100 text-slate-600 font-black text-sm rounded-2xl hover:bg-slate-200 transition-all text-center uppercase tracking-widest">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
