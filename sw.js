const CACHE_NAME = 'g5se-pwa-cache-v4'; // 버전 v4로 업데이트
const OFFLINE_URL = '/offline.php';

const urlsToCache = [
    OFFLINE_URL,
    '/img/icons/icon-192x192.png',
    '/img/icons/icon-512x512.png'
];

// 1. 강제 개별 캐싱 (에러가 나도 멈추지 않음)
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] 캐싱을 하나씩 시도합니다...');
            // Promise.all을 써서 개별적으로 처리
            return Promise.all(
                urlsToCache.map((url) => {
                    return cache.add(url).then(() => {
                        console.log('✅ 캐시 성공:', url);
                    }).catch((err) => {
                        // 실패하더라도 에러만 띄우고 전체 설치를 멈추지 않음!
                        console.error('❌ 캐시 실패 (범인):', url, err);
                    });
                })
            );
        })
    );
    self.skipWaiting(); // 무조건 설치 완료 처리
});

// 2. 활성화 및 예전 캐시 삭제
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// 3. 오프라인 지원 라우팅
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
    } else {
        event.respondWith(
            caches.match(event.request).then((response) => {
                return response || fetch(event.request);
            })
        );
    }
});