// app/sw.js
// Version 1.1
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installed');
});

self.addEventListener('fetch', (event) => {
    // 캐싱 로직 등을 나중에 여기에 추가
});