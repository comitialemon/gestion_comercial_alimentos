<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    @vite('resources/js/app.js')
    @inertiaHead
  </head>
  <body class="font-sans antialiased">
    @inertia
    
    <script>
        (function() {
            const ruta = window.location.pathname;
            const esLogin = ruta === '/login';
            const esContexto = ruta === '/contexto' || ruta === '/contexto/pdv';
            
            // Función para cerrar sesión
            function forzarLogout() {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                // Evitar múltiples redirecciones
                if (window._logoutEnProgreso) return;
                window._logoutEnProgreso = true;
                
                // Limpiar localStorage y sessionStorage
                localStorage.clear();
                sessionStorage.clear();
                
                if (token) {
                    fetch('/logout', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' }
                    }).finally(() => {
                        window.location.replace('/login');
                    });
                } else {
                    window.location.replace('/login');
                }
            }
            
            // 🔥 Verificar sesión activa
            function verificarSesion(redirigirSiNoHay = true) {
                return fetch('/check-session', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' },
                    cache: 'no-store' // No usar caché
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.has_session && redirigirSiNoHay && !esLogin) {
                        forzarLogout();
                        return false;
                    }
                    return data.has_session;
                })
                .catch(() => {
                    if (redirigirSiNoHay && !esLogin) {
                        forzarLogout();
                        return false;
                    }
                    return false;
                });
            }
            
            // 🔥 Verificar al cargar la página (sin caché)
            verificarSesion(true);
            
            // 🔥 Detectar cuando la página se carga desde caché (botón atrás/adelante)
            let paginaCargadaDesdeCache = false;
            
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    paginaCargadaDesdeCache = true;
                    // Forzar verificación (sin caché)
                    fetch('/check-session', {
                        headers: { 'Cache-Control': 'no-cache' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.has_session && !esLogin) {
                            forzarLogout();
                        }
                    })
                    .catch(() => {
                        if (!esLogin) forzarLogout();
                    });
                }
            });
            
            // 🔥 Prevenir que el usuario navegue con atrás/adelante si no hay sesión
            let ultimoEstado = true;
            
            setInterval(() => {
                fetch('/check-session', { cache: 'no-store' })
                    .then(res => res.json())
                    .then(data => {
                        if (ultimoEstado !== data.has_session) {
                            ultimoEstado = data.has_session;
                            if (!data.has_session && !esLogin) {
                                forzarLogout();
                            }
                        }
                    })
                    .catch(() => {
                        if (!esLogin) forzarLogout();
                    });
            }, 2000); // Verificar cada 2 segundos
            
            // 🔥 Manejar evento popstate (navegación atrás/adelante)
            window.addEventListener('popstate', function() {
                verificarSesion(true);
            });
            
            // 🔥 Prevenir que la página se almacene en bfcache (back-forward cache)
            window.addEventListener('beforeunload', function() {
                // No hacer nada, solo asegurar que la verificación funcione
            });
            
            // 🔥 Detectar si la página ya no tiene sesión visiblemente
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    verificarSesion(true);
                }
            });
        })();

        // Actualizar el token CSRF en el meta tag después de cada navegación
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args).then(response => {
                    const newToken = response.headers.get('X-CSRF-TOKEN');
                    if (newToken) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) {
                            meta.setAttribute('content', newToken);
                        }
                    }
                    return response;
                });
            };
        })();
    </script>
  </body>
</html>