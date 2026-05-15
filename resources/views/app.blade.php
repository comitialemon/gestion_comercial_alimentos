<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- 🔥 HEADERS ANTI-CACHÉ HTML -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    @vite('resources/js/app.js')
    @inertiaHead
    
    <style>
        /* 🔥 ESTILOS PARA EL LOADER SUAVE */
        .session-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #f9fafb;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.2s ease-out;
        }
        .session-loader.hide {
            opacity: 0;
            pointer-events: none;
        }
        .session-loader.hidden-permanent {
            display: none;
        }
        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid #e5e7eb;
            border-top-color: #61131a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loader-text {
            margin-top: 16px;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        /* 🔥 EVITAR QUE SE VEA EL CONTENIDO ANTES DE TIEMPO */
        .inertia-content {
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .inertia-content.visible {
            opacity: 1;
        }
    </style>
  </head>
  <body class="font-sans antialiased">
    <!-- 🔥 LOADER ELEGANTE (siempre visible al inicio) -->
    <div id="session-loader" class="session-loader">
        <div class="text-center">
            <div class="loader-spinner mx-auto"></div>
            <p class="loader-text">Cargando...</p>
        </div>
    </div>
    
    <script>
        (function() {
            const ruta = window.location.pathname;
            const esLogin = ruta === '/login';
            const esContexto = ruta === '/contexto' || ruta === '/contexto/pdv';
            const loader = document.getElementById('session-loader');
            
            // 🔥 Función para ocultar loader permanentemente
            function hideLoaderPermanent() {
                if (loader) {
                    loader.classList.add('hidden-permanent');
                }
            }
            
            // 🔥 Función para ocultar loader suavemente
            function hideLoader() {
                if (loader && !loader.classList.contains('hidden-permanent')) {
                    loader.classList.add('hide');
                    setTimeout(() => {
                        if (loader && !loader.classList.contains('hidden-permanent')) {
                            loader.style.display = 'none';
                        }
                    }, 200);
                }
            }
            
            // 🔥 Verificar sesión ANTES de mostrar la página (solo en páginas protegidas)
            if (!esLogin && !esContexto) {
                fetch('/check-session', {
                    method: 'GET',
                    headers: { 'Cache-Control': 'no-cache, no-store' },
                    cache: 'no-store'
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.has_session) {
                        window.location.replace('/login');
                    } else {
                        // Sesión válida, ocultar loader
                        hideLoaderPermanent();
                    }
                })
                .catch(() => {
                    window.location.replace('/login');
                });
            } else {
                // En login o contexto, ocultar loader
                hideLoaderPermanent();
            }
        })();
    </script>
    
    <div id="inertia-content" class="inertia-content">
        @inertia
    </div>
    
    <script>
        (function() {
            const ruta = window.location.pathname;
            const esLogin = ruta === '/login';
            const esContexto = ruta === '/contexto' || ruta === '/contexto/pdv';
            const loader = document.getElementById('session-loader');
            const inertiaContent = document.getElementById('inertia-content');
            
            // 🔥 Mostrar contenido con fade in
            function showContent() {
                if (inertiaContent) {
                    setTimeout(() => {
                        inertiaContent.classList.add('visible');
                    }, 50);
                }
            }
            
            // 🔥 Mostrar loader durante navegación
            function showLoader() {
                if (loader && loader.style.display === 'none') {
                    loader.style.display = 'flex';
                    loader.classList.remove('hide');
                    loader.classList.remove('hidden-permanent');
                } else if (loader && loader.classList.contains('hidden-permanent')) {
                    loader.classList.remove('hidden-permanent');
                    loader.classList.remove('hide');
                }
            }
            
            // 🔥 Ocultar loader después de navegación
            function hideLoader() {
                if (loader) {
                    loader.classList.add('hide');
                    setTimeout(() => {
                        if (loader) {
                            loader.style.display = 'none';
                        }
                    }, 200);
                }
            }
            
            // 🔥 Escuchar eventos de Inertia
            document.addEventListener('inertia:start', () => {
                showLoader();
            });
            
            document.addEventListener('inertia:finish', () => {
                hideLoader();
                showContent();
            });
            
            // Ocultar loader después de carga inicial
            setTimeout(() => {
                hideLoader();
                showContent();
            }, 100);
            
            // =============================================
            // 1. SI ESTÁ EN LOGIN - BLOQUEAR CUALQUIER POSIBILIDAD DE VOLVER
            // =============================================
            if (esLogin) {
                window.history.replaceState(null, "", window.location.href);
                
                window.onpopstate = function() {
                    window.history.go(1);
                };
                
                fetch('/check-session', {
                    method: 'GET',
                    headers: { 'Cache-Control': 'no-cache, no-store' },
                    cache: 'no-store'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.has_session) {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        fetch('/logout', {
                            method: 'POST',
                            headers: { 
                                'X-CSRF-TOKEN': token, 
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        }).finally(() => {
                            window.location.reload();
                        });
                    }
                })
                .catch(() => {});
            }
            
            // =============================================
            // 2. FUNCIÓN FORZAR LOGOUT
            // =============================================
            function forzarLogout() {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                if (window._logoutEnProgreso) return;
                window._logoutEnProgreso = true;
                
                localStorage.clear();
                sessionStorage.clear();
                
                const logoutUrl = '/logout';
                const redirectUrl = '/login';
                
                if (token) {
                    fetch(logoutUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' }
                    }).finally(() => {
                        window.location.replace(redirectUrl);
                    });
                } else {
                    window.location.replace(redirectUrl);
                }
            }
            
            // =============================================
            // 3. VERIFICAR SESIÓN ACTIVA
            // =============================================
            function verificarSesion(redirigirSiNoHay = true) {
                return fetch('/check-session', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' },
                    cache: 'no-store'
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
                        window.location.replace('/login');
                    }
                    return false;
                });
            }
            
            // =============================================
            // 4. VERIFICAR AL CARGAR LA PÁGINA
            // =============================================
            verificarSesion(true);
            
            // =============================================
            // 5. DETECTAR CUANDO LA PÁGINA VIENE DE CACHÉ
            // =============================================
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    showLoader();
                    fetch('/check-session', {
                        method: 'GET',
                        headers: { 'Cache-Control': 'no-cache, no-store' },
                        cache: 'no-store'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.has_session) {
                            forzarLogout();
                        } else {
                            if (esLogin) {
                                forzarLogout();
                            } else {
                                hideLoader();
                            }
                        }
                    })
                    .catch(() => {
                        if (!esLogin) forzarLogout();
                    });
                }
            });
            
            // =============================================
            // 6. VERIFICACIÓN PERIÓDICA
            // =============================================
            let ultimoEstado = true;
            
            setInterval(() => {
                if (!esLogin) {
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
                }
            }, 3000);
            
            // =============================================
            // 7. MANEJAR EVENTO POPSTATE
            // =============================================
            window.addEventListener('popstate', function() {
                verificarSesion(true);
            });
            
            // =============================================
            // 8. DETECTAR VISIBILIDAD
            // =============================================
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    verificarSesion(true);
                }
            });
            
        })();
        
        // =============================================
        // 9. ACTUALIZAR TOKEN CSRF
        // =============================================
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
                        if (window.axios) {
                            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                        }
                    }
                    return response;
                });
            };
        })();
        
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem('last_visit', Date.now());
        });
    </script>
  </body>
</html>