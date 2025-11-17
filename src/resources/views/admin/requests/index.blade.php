<x-layouts.admin title="구조요청 관리 - GPS119 관리자">
    <!-- Vue.js 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Axios CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Kakao Map API -->
    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=509c2656c00fa9af4782197a888763f6&libraries=services,clusterer,drawing&autoload=false"></script>

    <div id="requestsApp" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">구조요청 관리</h1>
                <p class="mt-2 text-gray-600">모든 구조 요청을 관리하세요.</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- View Toggle -->
                <div class="flex bg-gray-100 rounded-md p-1">
                    <button @click="viewMode = 'table'" type="button"
                            :class="[
                                'px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 flex items-center gap-2',
                                viewMode === 'table'
                                    ? 'bg-white text-gray-900 shadow'
                                    : 'text-gray-600 hover:text-gray-900'
                            ]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span>테이블</span>
                    </button>
                    <button @click="viewMode = 'map'" type="button"
                            :class="[
                                'px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 flex items-center gap-2',
                                viewMode === 'map'
                                    ? 'bg-white text-gray-900 shadow'
                                    : 'text-gray-600 hover:text-gray-900'
                            ]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span>지도</span>
                    </button>
                </div>

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

        <!-- Map View -->
        <div v-show="viewMode === 'map'" class="bg-white rounded-lg shadow overflow-hidden">
            <div id="map" class="w-full" style="height: 700px;"></div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-bold text-gray-900">구조요청 상세 정보</h3>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div v-if="selectedRequest" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">요청 ID</label>
                                    <p class="mt-1 text-sm text-gray-900">#@{{ selectedRequest.id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">상태</label>
                                    <span class="mt-1 inline-flex px-3 py-1 text-sm font-semibold rounded-full" :class="getStatusClass(selectedRequest.status)">
                                        @{{ getStatusText(selectedRequest.status) }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">요청자</label>
                                <p class="mt-1 text-sm text-gray-900">@{{ selectedRequest.user?.name || '알 수 없음' }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">연락처</label>
                                <p class="mt-1 text-sm text-gray-900">@{{ selectedRequest.user?.formatted_phone || '-' }}</p>
                            </div>

                            <div v-if="selectedRequest.description">
                                <label class="block text-sm font-medium text-gray-700">상세 설명</label>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">@{{ selectedRequest.description }}</p>
                            </div>

                            <div v-if="selectedRequest.assigned_rescuer">
                                <label class="block text-sm font-medium text-gray-700">담당 구조대원</label>
                                <p class="mt-1 text-sm text-gray-900">@{{ selectedRequest.assigned_rescuer.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">요청 일시</label>
                                <p class="mt-1 text-sm text-gray-900">@{{ formatDate(selectedRequest.created_at) }} @{{ formatTime(selectedRequest.created_at) }}</p>
                            </div>

                            <div v-if="selectedRequest.latitude && selectedRequest.longitude">
                                <label class="block text-sm font-medium text-gray-700">위치</label>
                                <p class="mt-1 text-sm text-gray-900">위도: @{{ selectedRequest.latitude }}, 경도: @{{ selectedRequest.longitude }}</p>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                            <p class="mt-2 text-sm text-gray-500">로딩 중...</p>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <a v-if="selectedRequest" :href="`/admin/requests/${selectedRequest.id}`"
                           class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                            전체 상세보기
                        </a>
                        <button @click="closeModal" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                            닫기
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requests Table -->
        <div v-show="viewMode === 'table'" class="bg-white rounded-lg shadow overflow-hidden">
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

        const vueInstance = createApp({
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
                    updatingRequests: {}, // 업데이트 중인 요청 추적
                    viewMode: 'table', // 'table' or 'map'
                    map: null,
                    markers: [],
                    infowindow: null,
                    initialBoundsSet: false, // 최초 지도 범위 설정 여부
                    showModal: false,
                    selectedRequest: null
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
                },

                // 지도 초기화
                initMap() {
                    if (this.map) return;

                    kakao.maps.load(() => {
                        const container = document.getElementById('map');
                        if (!container) {
                            console.error('Map container not found');
                            return;
                        }

                        const options = {
                            center: new kakao.maps.LatLng(37.5665, 126.9780), // 서울 중심
                            level: 20
                        };

                        this.map = new kakao.maps.Map(container, options);
                        this.infowindow = new kakao.maps.InfoWindow({ zIndex: 1 });

                        // 지도 컨트롤 추가
                        // 일반 지도와 스카이뷰로 지도 타입을 전환할 수 있는 컨트롤
                        const mapTypeControl = new kakao.maps.MapTypeControl();
                        this.map.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                        // 지도 확대 축소를 제어할 수 있는 줌 컨트롤
                        const zoomControl = new kakao.maps.ZoomControl();
                        this.map.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                        // 마커 표시
                        this.$nextTick(() => {
                            this.updateMarkers();
                        });
                    });
                },

                // 마커 색상 반환
                getMarkerColor(status) {
                    const colorMap = {
                        'pending': 'yellow',
                        'in_progress': 'blue',
                        'completed': 'green',
                        'cancelled': 'red'
                    };
                    return colorMap[status] || 'gray';
                },

                // 마커 업데이트
                updateMarkers() {
                    if (!this.map) return;

                    try {
                        // 기존 마커들 제거
                        this.markers.forEach(marker => {
                            marker.setMap(null);
                        });
                        this.markers = [];

                        // 위치 정보가 있는 요청만 필터링
                        const validRequests = this.requests.filter(req => req.latitude && req.longitude);

                        if (validRequests.length === 0) return;

                        // 새 마커 생성
                        const bounds = new kakao.maps.LatLngBounds();

                    validRequests.forEach(request => {
                        const position = new kakao.maps.LatLng(request.latitude, request.longitude);

                        // 마커 이미지 설정 (상태별 색상)
                        // const imageSrc = `https://t1.daumcdn.net/localimg/localimages/07/mapapidoc/marker_${this.getMarkerColor(request.status)}.png`;
                        const imageSize = new kakao.maps.Size(36, 37);
                        // const markerImage = new kakao.maps.MarkerImage(imageSrc, imageSize);

                        const marker = new kakao.maps.Marker({
                            position: position,
                            // image: markerImage,
                            map: this.map
                        });

                        // 마커 클릭 이벤트
                        kakao.maps.event.addListener(marker, 'click', () => {
                            const content = `
                                <div style="padding: 15px; min-width: 280px;">
                                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 10px;">
                                        #${request.id} - ${request.user?.name || '알 수 없음'}
                                    </div>
                                    <div style="margin-bottom: 8px; color: #666;">
                                        <strong>연락처:</strong> ${request.user?.formatted_phone || '-'}
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <span style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; background-color: ${this.getStatusBgColor(request.status)}; color: ${this.getStatusTextColor(request.status)};">
                                            ${this.getStatusText(request.status)}
                                        </span>
                                    </div>
                                    ${request.description ? `
                                        <div style="margin-bottom: 8px; color: #666;">
                                            <strong>상세:</strong> ${request.description.substring(0, 50)}${request.description.length > 50 ? '...' : ''}
                                        </div>
                                    ` : ''}
                                    ${request.assigned_rescuer ? `
                                        <div style="margin-bottom: 8px; color: #666;">
                                            <strong>담당자:</strong> ${request.assigned_rescuer.name}
                                        </div>
                                    ` : ''}
                                    <div style="margin-top: 12px;">
                                        <button onclick="window.vueApp.openModal(${request.id})" style="color: #2563eb; background: none; border: none; cursor: pointer; font-weight: 500; text-decoration: none; padding: 0;">
                                            상세보기 →
                                        </button>
                                    </div>
                                </div>
                            `;
                            this.infowindow.setContent(content);
                            this.infowindow.open(this.map, marker);
                        });

                        this.markers.push(marker);
                        bounds.extend(position);
                    });

                        // 최초 로드 시에만 모든 마커가 보이도록 지도 범위 조정
                        if (validRequests.length > 0 && !this.initialBoundsSet) {
                            this.map.setBounds(bounds);
                            this.initialBoundsSet = true;
                        }
                    } catch (error) {
                        console.error('Failed to update markers:', error);
                    }
                },

                // 상태 배경색
                getStatusBgColor(status) {
                    const colorMap = {
                        'pending': '#fef3c7',
                        'in_progress': '#dbeafe',
                        'completed': '#d1fae5',
                        'cancelled': '#fee2e2'
                    };
                    return colorMap[status] || '#f3f4f6';
                },

                // 상태 텍스트 색상
                getStatusTextColor(status) {
                    const colorMap = {
                        'pending': '#92400e',
                        'in_progress': '#1e40af',
                        'completed': '#065f46',
                        'cancelled': '#991b1b'
                    };
                    return colorMap[status] || '#374151';
                },

                // 모달 열기
                async openModal(requestId) {
                    this.showModal = true;
                    this.selectedRequest = null;

                    try {
                        const response = await axios.get(`/admin/requests/${requestId}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (response.data) {
                            this.selectedRequest = response.data;
                        }
                    } catch (error) {
                        console.error('Failed to fetch request details:', error);
                        alert('상세 정보를 불러오는데 실패했습니다.');
                        this.closeModal();
                    }
                },

                // 모달 닫기
                closeModal() {
                    this.showModal = false;
                    this.selectedRequest = null;
                }
            },
            watch: {
                viewMode(newMode) {
                    if (newMode === 'map') {
                        this.$nextTick(() => {
                            this.initMap();
                        });
                    }
                },
                requests: {
                    handler(newVal, oldVal) {
                        // 지도 모드이고 지도가 로드되었을 때만
                        if (this.viewMode === 'map' && this.map) {
                            // 요청 배열의 길이가 변경되었을 때만 마커 업데이트
                            // (자동 갱신으로 인한 불필요한 업데이트 방지)
                            if (!oldVal || newVal.length !== oldVal.length) {
                                this.$nextTick(() => {
                                    this.updateMarkers();
                                });
                            }
                        }
                    },
                    deep: true
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
        });

        const app = vueInstance.mount('#requestsApp');

        // 전역 함수로 노출 (인포윈도우에서 사용)
        window.vueApp = {
            openModal: (requestId) => {
                const instance = app;
                if (instance && instance.openModal) {
                    instance.openModal(requestId);
                }
            }
        };
    </script>
</x-layouts.admin>
