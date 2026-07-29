import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// 실시간 관제: Echo(Reverb) 초기화 — window.Echo 노출 (FE-0.1)
import { createEcho } from './echo';
createEcho();
