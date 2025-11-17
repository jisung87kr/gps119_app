<x-layouts.admin title="구조요청 관리 - GPS119 관리자">
    <!-- Vue.js 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Axios CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <div id="requestsApp" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">구조요청 관리</h1>
                <p class="mt-2 text-gray-600">모든 구조 요청을 관리하세요.</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="toggleAutoRefresh" type="button"
                        :class="[
                            'px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-offset-2',
                            autoRefresh
                                ? 'bg-green-100 text-green-700 hover:bg-green-200 focus:ring-green-500 border-2 border-green-500'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-400 border-2 border-gray-300'
                        ]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="autoRefresh" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>자동 갱신</span>
                    <span :class="['px-2 py-0.5 rounded text-xs font-semibold', autoRefresh ? 'bg-green-600 text-white' : 'bg-gray-500 text-white']">
                        @{{ autoRefresh ? 'ON' : 'OFF' }}
                    </span>
                </button>

                <button @click="manualRefresh" type="button" :disabled="isLoading"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed font-medium text-sm">
                    <svg :class="['w-4 h-4', {'animate-spin': isLoading}]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>@{{ isLoading ? '갱신중...' : '새로고침' }}</span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('admin.requests') }}" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-0">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="요청자 이름, 연락처로 검색..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">모든 상태</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>대기중</option>
                            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>진행중</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>완료</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>취소됨</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            검색
                        </button>
                        <a href="{{ route('admin.requests') }}" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                            초기화
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-yellow-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-600">대기중</p>
                        <p class="text-lg font-semibold text-yellow-900">@{{ stats.pending }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-600">진행중</p>
                        <p class="text-lg font-semibold text-blue-900">@{{ stats.in_progress }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-600">완료</p>
                        <p class="text-lg font-semibold text-green-900">@{{ stats.completed }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-600">취소됨</p>
                        <p class="text-lg font-semibold text-red-900">@{{ stats.cancelled }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">요청자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">담당자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">요청일시</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="requests.length === 0">
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                검색된 구조 요청이 없습니다.
                            </td>
                        </tr>
                        <tr v-for="request in requests" :key="request.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #@{{ request.id }}
                            </td>
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">@{{ request.user?.name || '알 수 없음' }}</div>
                                    <div class="text-sm text-gray-500">@{{ request.user?.formatted_phone || '-' }}</div>
                                    <div v-if="request.description" class="mt-2">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 max-w-xs">
                                            <svg class="w-3 h-3 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="line-clamp-2 break-all">@{{ request.description }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select @change="updateRequest(request.id, 'status', $event.target.value)"
                                        v-model="request.status"
                                        :disabled="updatingRequests[request.id]"
                                        class="text-xs font-medium rounded-full px-2.5 py-1 border-0 focus:ring-2 focus:ring-offset-2 min-w-[100px]"
                                        :class="[
                                            getStatusClass(request.status),
                                            updatingRequests[request.id] ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                        ]">
                                    <option value="pending">대기중</option>
                                    <option value="in_progress">진행중</option>
                                    <option value="completed">완료</option>
                                    <option value="cancelled">취소됨</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select @change="updateRequest(request.id, 'assigned_rescuer_id', $event.target.value || null)"
                                        v-model="request.assigned_rescuer_id"
                                        :disabled="updatingRequests[request.id]"
                                        class="text-sm text-gray-900 rounded-md px-2 py-1 border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[140px]"
                                        :class="updatingRequests[request.id] ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'">
                                    <option value="">미배정</option>
                                    <option v-for="rescuer in rescuers" :key="rescuer.id" :value="rescuer.id">
                                        @{{ rescuer.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>@{{ formatDate(request.created_at) }}</div>
                                <div>@{{ formatTime(request.created_at) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a :href="`/admin/requests/${request.id}`" class="text-blue-600 hover:text-blue-900">상세보기</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($requests->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $requests->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    requests: @json($requests->items()),
                    rescuers: @json($rescuers),
                    stats: {
                        pending: {{ $requests->where('status', 'pending')->count() }},
                        in_progress: {{ $requests->where('status', 'in_progress')->count() }},
                        completed: {{ $requests->where('status', 'completed')->count() }},
                        cancelled: {{ $requests->where('status', 'cancelled')->count() }}
                    },
                    autoRefresh: false,
                    refreshInterval: 10000, // 10초
                    refreshTimer: null,
                    isLoading: false,
                    updatingRequests: {} // 업데이트 중인 요청 추적
                }
            },
            methods: {
                // 상태 한글 변환
                getStatusText(status) {
                    const statusMap = {
                        'pending': '대기중',
                        'in_progress': '진행중',
                        'completed': '완료',
                        'cancelled': '취소됨'
                    };
                    return statusMap[status] || status;
                },

                // 상태 색상 클래스
                getStatusClass(status) {
                    const classMap = {
                        'pending': 'bg-yellow-100 text-yellow-800',
                        'in_progress': 'bg-blue-100 text-blue-800',
                        'completed': 'bg-green-100 text-green-800',
                        'cancelled': 'bg-red-100 text-red-800'
                    };
                    return classMap[status] || 'bg-gray-100 text-gray-800';
                },

                // 날짜 포맷
                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('ko-KR');
                },

                // 시간 포맷
                formatTime(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
                },

                // 데이터 가져오기
                async fetchRequests() {
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const params = {
                            status: urlParams.get('status') || '',
                            search: urlParams.get('search') || '',
                        };

                        const queryString = new URLSearchParams(params).toString();
                        const response = await axios.get(`/admin/requests?${queryString}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (response.data.success) {
                            this.requests = response.data.data;
                            this.stats = response.data.stats;
                        }
                    } catch (error) {
                        console.error('Failed to fetch requests:', error);
                    }
                },

                // 자동 갱신 시작
                startAutoRefresh() {
                    if (this.refreshTimer) {
                        clearInterval(this.refreshTimer);
                    }
                    this.refreshTimer = setInterval(() => {
                        this.fetchRequests();
                    }, this.refreshInterval);
                },

                // 자동 갱신 중지
                stopAutoRefresh() {
                    if (this.refreshTimer) {
                        clearInterval(this.refreshTimer);
                        this.refreshTimer = null;
                    }
                },

                // 자동 갱신 토글
                toggleAutoRefresh() {
                    this.autoRefresh = !this.autoRefresh;

                    if (this.autoRefresh) {
                        this.startAutoRefresh();
                    } else {
                        this.stopAutoRefresh();
                    }
                },

                // 수동 새로고침
                async manualRefresh() {
                    this.isLoading = true;
                    try {
                        await this.fetchRequests();
                    } finally {
                        this.isLoading = false;
                    }
                },

                // 요청 업데이트
                async updateRequest(requestId, field, value) {
                    // 이미 업데이트 중인 경우 무시
                    if (this.updatingRequests[requestId]) {
                        return;
                    }

                    // 업데이트 상태 설정
                    this.updatingRequests[requestId] = true;

                    try {
                        const data = {};
                        data[field] = value;

                        const response = await axios.patch(`/admin/requests/${requestId}/quick-update`, data, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (response.data.success) {
                            // 업데이트된 데이터로 요청 객체 갱신
                            const index = this.requests.findIndex(r => r.id === requestId);
                            if (index !== -1) {
                                this.requests[index] = response.data.data;
                            }

                            // 통계 업데이트를 위해 데이터 다시 가져오기
                            await this.fetchRequests();
                        }
                    } catch (error) {
                        console.error('Failed to update request:', error);
                        alert('업데이트에 실패했습니다.');
                        // 에러 발생 시 데이터 다시 가져오기
                        await this.fetchRequests();
                    } finally {
                        // 업데이트 상태 해제
                        delete this.updatingRequests[requestId];
                    }
                }
            },
            mounted() {
                // 자동 갱신 시작
                this.startAutoRefresh();
            },
            beforeUnmount() {
                // 컴포넌트 제거 전 자동 갱신 중지
                this.stopAutoRefresh();
            }
        }).mount('#requestsApp');
    </script>
</x-layouts.admin>
