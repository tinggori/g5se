// 캐시 이름 설정 (버전을 올리면 캐시가 새로 갱신됩니다)
const CACHE_NAME = 'g5se-cache-v3';
// 오프라인일 때 보여줄 페이지 경로
const OFFLINE_URL = '/offline.php';

// 설치할 때 반드시 캐시해둘 파일 목록
const urlsToCache = [
    OFFLINE_URL,
    '/img/icons/icon-192x192.png',
    '/img/icons/icon-512x512.png'
];

// 1. 서비스 워커 설치 (캐시 저장)
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
    self.skipWaiting(); // 즉시 활성화
});

// 2. 서비스 워커 활성화 (이전 버전 캐시 삭제)
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// 3. 네트워크 요청 가로채기 (오프라인 지원 핵심)
self.addEventListener('fetch', (event) => {
    // HTML 페이지(navigate)를 요청할 때만 동작
    if (event.request.mode === 'navigate') {
        event.respondWith(
            // 먼저 항상 네트워크(서버)에서 최신 데이터를 가져오려고 시도
            fetch(event.request)
                .catch(() => {
                    // 네트워크 연결이 끊겨서 에러가 나면, 캐시해둔 오프라인 페이지를 보여줌
                    return caches.match(OFFLINE_URL);
                })
        );
    } else {
        // 이미지, CSS 등 다른 파일들은 캐시를 먼저 확인하고 없으면 네트워크 요청
        event.respondWith(
            caches.match(event.request)
                .then((response) => response || fetch(event.request))
        );
    }
});