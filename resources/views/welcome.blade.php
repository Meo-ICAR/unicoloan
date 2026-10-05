<<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unicooam - Piattaforma Gestionale Mediatori Creditizi</title>
    <!-- Tailwind CSS (assicurati che Vite sia configurato, o usa il CDN per test rapidi) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="bg-blue-600 text-white p-2 rounded-lg">
                    <!-- Icona Placeholder -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Unicooam</h1>
            </div>
            <div class="text-sm text-gray-500 font-medium">
                Versione 1.0
            </div>
        </div>
    </header>

    <!-- Main Content (Hero + Login) -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-12 items-center">

            <!-- Left Column: Value Proposition -->
            <div>
                <h2 class="text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl mb-6">
                    La compliance OAM,<br> <span class="text-blue-600">semplificata.</span>
                </h2>
                <p class="text-lg text-gray-600 mb-8">
                    Centralizza i dati operativi, gestisci audit e reclami, e genera la tua <strong>Relazione Semestrale OAM</strong> in pochi clic. Da giorni di lavoro a pochi minuti, senza errori.
                </p>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-green-500 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Automazione Report OAM</h3>
                            <p class="text-gray-500">I 5 fogli Excel compilati automaticamente dai dati del gestionale commerciale.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-green-500 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Registro Compliance Unificato</h3>
                            <p class="text-gray-500">Tracciamento completo di ispezioni, rilievi, reclami e segnalazioni SOS (Antiriciclaggio).</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-green-500 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Archivio Sicuro</h3>
                            <p class="text-gray-500">Documentazione, formazione e anagrafiche sempre aggiornate e monitorate per le scadenze.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Login Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-semibold text-gray-900">Accesso Riservato</h2>
                    <p class="text-gray-500 mt-2 text-sm">Accedi utilizzando le credenziali aziendali autorizzate.</p>
                </div>

                <div class="space-y-4">
                    <!-- Google SSO Button (Collegato a Filament Socialite) -->
                    <a href="{{ route('socialite.redirect', 'google') }}" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Accedi con Google Workspace
                    </a>

                    <!-- Microsoft SSO Button (Opzionale) -->
                    <a href="{{ route('socialite.redirect', 'azure') }}" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="h-5 w-5 mr-2" viewBox="0 0 23 23" fill="currentColor">
                            <path fill="#f35325" d="M1 1h10v10H1z"/>
                            <path fill="#81bc06" d="M12 1h10v10H12z"/>
                            <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                            <path fill="#ffba08" d="M12 12h10v10H12z"/>
                        </svg>
                        Accedi con Microsoft Entra ID
                    </a>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                    <p class="text-xs text-gray-400">
                        L'accesso è consentito esclusivamente al personale autorizzato. I log di accesso sono registrati per finalità di sicurezza.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} Unicooam. Sistema gestionale a uso interno. Tutti i diritti riservati.
        </div>
    </footer>

</body>
</html>
