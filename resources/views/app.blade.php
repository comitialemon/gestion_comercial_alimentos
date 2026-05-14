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
            
            // 🔥 Si NO está en login, verificar la sesión
            if (!esLogin) {
                // Verificar sesión cada vez que se carga la página
                fetch('/check-session')
                    .then(res => res.json())
                    .then(data => {
                        // Si no hay sesión y no está en contexto, redirigir a login
                        if (!data.has_session && !esContexto) {
                            forzarLogout();
                        }
                    })
                    .catch(() => {
                        if (!esContexto) forzarLogout();
                    });
            }
            
            // 🔥 Detectar cuando se usa el botón "adelante"
            let paginaCargadaDesdeCache = false;
            
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    paginaCargadaDesdeCache = true;
                    // Si la página vino de caché, verificar sesión
                    fetch('/check-session')
                        .then(res => res.json())
                        .then(data => {
                            if (!data.has_session) {
                                forzarLogout();
                            }
                        })
                        .catch(() => forzarLogout());
                }
            });
            
            // 🔥 También prevenir que el usuario pueda volver con el ratón (popstate)
            window.addEventListener('popstate', function() {
                if (!esContexto) {
                    fetch('/check-session')
                        .then(res => res.json())
                        .then(data => {
                            if (!data.has_session) {
                                forzarLogout();
                            }
                        });
                }
            });
        })();

        // Actualizar el token CSRF en el meta tag después de cada navegación
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args).then(response => {
                    // Si la respuesta tiene un nuevo token en los headers, actualizar el meta tag
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