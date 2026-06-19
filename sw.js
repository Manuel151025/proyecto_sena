const CACHE_NAME = "sena-cache-v2";
const ASSETS_TO_CACHE = [
  "./assets/css/theme.css",
  "./assets/css/picker.css",
  "./assets/img/sena_logo.png",
  "./assets/js/app.js"
];

// Instalar el Service Worker y guardar en caché los recursos estáticos
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(ASSETS_TO_CACHE))
      .then(() => self.skipWaiting())
  );
});

// Activar el Service Worker y eliminar cachés antiguas
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar peticiones y decidir estrategias de caché
self.addEventListener("fetch", event => {
  const url = new URL(event.request.url);

  // Excluir de caché: Peticiones que no sean GET, páginas PHP dinámicas y llamadas de API
  if (
    event.request.method !== "GET" ||
    url.pathname.endsWith(".php") ||
    url.pathname.includes("/api/") ||
    url.pathname.includes("/includes/")
  ) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Estrategia: Cache First para archivos estáticos (CSS, JS, Imágenes)
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request).then(fetchResponse => {
          // Si el recurso es estático y del mismo origen, guardarlo en caché
          if (
            event.request.url.startsWith(self.location.origin) &&
            (url.pathname.includes("/css/") || url.pathname.includes("/js/") || url.pathname.includes("/img/"))
          ) {
            return caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, fetchResponse.clone());
              return fetchResponse;
            });
          }
          return fetchResponse;
        });
      })
  );
});
