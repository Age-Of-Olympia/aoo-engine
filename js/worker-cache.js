const cacheName = "aoo-v1";
const contentToCache = ["/fallback.html","/css/main.min.css"];
self.addEventListener("install", (e) => {
    console.log("[Service Worker] Install");
    e.waitUntil(
      (async () => {
        const cache = await caches.open(cacheName);
        console.log("[Service Worker] Caching all: app shell and content");
        await cache.addAll(contentToCache);
      })(),
    );
  });

  self.addEventListener("fetch", (event) => {
    console.log("[Service Worker] Fetch " + event.request.url);
    if(!navigator.onLine){
      event.respondWith(
        cacheFirst({
          request: event.request,
          fallbackUrl:"/fallback.html",
        })
      );
  }
  });