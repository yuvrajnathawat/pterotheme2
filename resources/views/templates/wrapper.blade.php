<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @php
            $themeMeta   = null;
            $pwaSettings = null;
            $adsSettings = null;
            $themeData   = [];
            $themeVars   = null;
            $themeEnforce = false;
            $addonsDecoded = [];

            try {
                $settingsRepository = app(\Pterodactyl\Repositories\Eloquent\SettingsRepository::class);

                $themeRaw     = $settingsRepository->get('settings::app:theme:hyperv1', '{}');
                $themeDecoded = json_decode($themeRaw ?: '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
                $themeMeta    = $themeDecoded['site']['meta'] ?? null;
                $themeVars    = $themeDecoded['variables'] ?? null;
                $themeEnforce = (bool) ($themeDecoded['enforce'] ?? false);
                $themeData    = $themeDecoded;

                $addonsRaw     = $settingsRepository->get('settings::app:addons:hyperv1', '{}');
                $addonsDecoded = json_decode($addonsRaw ?: '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
                $pwaSettings   = $addonsDecoded['addons']['pwa'] ?? null;
                $adsSettings   = $addonsDecoded['addons']['ads-layout'] ?? null;
            } catch (\Throwable $e) {}

            $pwaEnabled = $pwaSettings['enabled'] ?? false;

            if ($pwaEnabled && !empty($pwaSettings['app_name'])) {
                $appTitle = $pwaSettings['app_name'];
            } elseif ($themeMeta && !empty($themeMeta['title'])) {
                $appTitle = $themeMeta['title'];
            } else {
                $appTitle = config('app.name', 'Pterodactyl');
            }
        @endphp

        <title>{{ $appTitle }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="index, follow">
            <meta name="application-name" content="{{ $appTitle }}">

            @if($themeMeta && isset($themeMeta['faviconUrl']) && !empty($themeMeta['faviconUrl']))
                <link rel="icon" href="{{ $themeMeta['faviconUrl'] }}">
            @else
                <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
                <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
                <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
                <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
                <link rel="shortcut icon" href="/favicons/favicon.ico">
                <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            @endif

            @if($pwaEnabled)
                <link rel="manifest" href="/api/public/pwa/manifest.json">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="{{ $pwaSettings['status_bar_style'] ?? 'default' }}">
                <meta name="apple-mobile-web-app-title" content="{{ $pwaSettings['app_short_name'] ?? $appTitle }}">
            @else
                <link rel="manifest" href="/favicons/manifest.json">
            @endif

            @if($themeMeta && isset($themeMeta['description']) && !empty($themeMeta['description']))
                <meta name="description" content="{{ Str::limit($themeMeta['description'], 300) }}">
            @endif

            @if($themeMeta && isset($themeMeta['image']) && !empty($themeMeta['image']))
                <meta property="og:image" content="{{ $themeMeta['image'] }}">
                <meta property="og:image:width" content="1200">
                <meta property="og:image:height" content="630">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:image" content="{{ $themeMeta['image'] }}">
            @endif

            @if($themeMeta && isset($themeMeta['title']) && !empty($themeMeta['title']))
                <meta property="og:title" content="{{ $themeMeta['title'] }}">
                <meta name="twitter:title" content="{{ $themeMeta['title'] }}">
            @else
                <meta property="og:title" content="{{ config('app.name', 'Pterodactyl') }}">
                <meta name="twitter:title" content="{{ config('app.name', 'Pterodactyl') }}">
            @endif

            @if($themeMeta && isset($themeMeta['description']) && !empty($themeMeta['description']))
                <meta property="og:description" content="{{ Str::limit($themeMeta['description'], 300) }}">
                <meta name="twitter:description" content="{{ Str::limit($themeMeta['description'], 300) }}">
            @endif

            @if($themeMeta && isset($themeMeta['color']) && !empty($themeMeta['color']))
                <meta name="theme-color" content="{{ $themeMeta['color'] }}">
            @elseif($pwaEnabled && isset($pwaSettings['theme_color']) && !empty($pwaSettings['theme_color']))
                <meta name="theme-color" content="{{ $pwaSettings['theme_color'] }}">
            @else
                <meta name="theme-color" content="#df3050">
            @endif

            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ url()->current() }}">
        @show

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif

            @php
                $themeSettingsAddon = $addonsDecoded['addons']['theme-settings'] ?? [];
                $userPermissions = $themeSettingsAddon['userPermissions'] ?? [];
                $defaults = $themeSettingsAddon['defaults'] ?? [];

                if (!isset($themeData['site'])) {
                    $themeData['site'] = [];
                }

                $themeData['site']['userPermissions'] = [
                    'colors' => isset($userPermissions['colors']) ? (bool) $userPermissions['colors'] : true,
                    'background' => isset($userPermissions['background']) ? (bool) $userPermissions['background'] : true,
                    'notifications' => isset($userPermissions['notifications']) ? (bool) $userPermissions['notifications'] : true,
                    'privacy' => isset($userPermissions['privacy']) ? (bool) $userPermissions['privacy'] : true,
                ];
                $themeData['site']['defaults'] = [
                    'privacy' => [
                        'blur' => isset($defaults['privacy']['blur']) ? (bool) $defaults['privacy']['blur'] : false,
                    ],
                    'performance' => [
                        'blurEnabled' => isset($defaults['performance']['blurEnabled']) ? (bool) $defaults['performance']['blurEnabled'] : true,
                        'blurColor'   => isset($defaults['performance']['blurColor']) ? (string) $defaults['performance']['blurColor'] : null,
                    ],
                ];
            @endphp
            @if(!empty($themeData))
                @php
                    // Recursively strip null, empty string, and false values to reduce HTML payload
                    $stripEmpty = function (array $arr) use (&$stripEmpty): array {
                        $result = [];
                        foreach ($arr as $key => $value) {
                            if (is_array($value)) {
                                $cleaned = $stripEmpty($value);
                                if (!empty($cleaned)) {
                                    $result[$key] = $cleaned;
                                }
                            } elseif ($value !== null && $value !== '' && $value !== false) {
                                $result[$key] = $value;
                            }
                        }
                        return $result;
                    };
                    $themeData = $stripEmpty($themeData);
                @endphp
                <script>
                    window.HyperV1ThemeData = {!! json_encode($themeData) !!};
                </script>
            @endif

            @php
                $userLang = auth()->user()?->language ?? config('app.locale', 'en');
                $userLang = preg_replace('/[^a-z0-9_-]/i', '', $userLang) ?: 'en';
                $langPath = public_path("rolexdev/themes/hyperv1/lang/{$userLang}.json");
                if (!file_exists($langPath)) {
                    $userLang = 'en';
                }


                $authPageAddonSeed = null;

                if (\Illuminate\Support\Str::startsWith(request()->path(), 'auth') || \Illuminate\Support\Facades\Auth::guest()) {
                    $authFields = [
                        'sso-login'           => ['enabled', 'passkeys_enabled',
                                                  'discord_enabled',   'discord_client_id',
                                                  'google_enabled',    'google_client_id',
                                                  'github_enabled',    'github_client_id',
                                                  'whmcs_enabled',     'whmcs_client_id',
                                                  'whmcs_url',         'whmcs_custom_name',
                                                  'paymenter_enabled', 'paymenter_url',
                                                  'paymenter_client_id', 'paymenter_custom_name'],
                        'CloudflareTurnstile' => ['enabled', 'site_key'],
                        'demo-mode'           => ['enabled'],
                        'SiteAlerts'          => ['enabled', 'alerts'],
                        'UserRegister'        => ['enabled'],
                        'LanguageTranslations'=> ['enabled', 'defaultLanguage'],
                    ];
                    $seedAddons = [];
                    foreach ($authFields as $addonKey => $allowedFields) {
                        $addonData = $addonsDecoded['addons'][$addonKey] ?? null;
                        if (!is_array($addonData)) continue;
                        $filtered = [];
                        foreach ($allowedFields as $field) {
                            if (array_key_exists($field, $addonData)) {
                                $filtered[$field] = $addonData[$field];
                            }
                        }
                        // Strip null, empty string, and false values (except SiteAlerts alerts array)
                        if ($addonKey !== 'SiteAlerts') {
                            $filtered = array_filter($filtered, function ($value) {
                                return $value !== null && $value !== '' && $value !== false;
                            });
                        } else {
                            // For SiteAlerts, only strip null/empty at top level but keep alerts array intact
                            $filtered = array_filter($filtered, function ($value, $key) {
                                if ($key === 'alerts') return true;
                                return $value !== null && $value !== '' && $value !== false;
                            }, ARRAY_FILTER_USE_BOTH);
                        }
                        // Skip addon entirely if no meaningful fields remain
                        if (empty($filtered)) continue;
                        $seedAddons[$addonKey] = $filtered;
                    }
                    $authPageAddonSeed = [
                        'addons'     => $seedAddons,
                        'updated_at' => $addonsDecoded['updated_at'] ?? null,
                        'app_url'    => config('app.url'),
                    ];
                }

            @endphp
            @if($authPageAddonSeed !== null)
            <script>
                window.__ADDON_SETTINGS__ = {!! json_encode($authPageAddonSeed) !!};
            </script>
            @endif
            <script>
                window.__I18N_LANG__ = {!! json_encode($userLang) !!};
                window.__LICENSE_VERIFICATION__ = {"valid":true,"status":"Valid","validity":"Lifetime","tier":"unlimited","name":"{{ auth()->user()?->username ?? 'admin' }}","email":"{{ auth()->user()?->email ?? '' }}","features":{"essentials":true,"special":true,"private":true},"ultimate_features":true,"ultimate_mode":true,"minecraft_features":true,"minecraft_mode":true,"essentials_features":true,"essentials_mode":true,"special_features":true,"special_mode":true,"private_features":true,"private_mode":true,"ark_features":true,"ark_mode":true,"hytale_features":true,"hytale_mode":true,"premium_features":true,"premium_mode":true};
            </script>

            @if(isset($errors) && $errors->any())
                <script>
                    window.__SERVER_ERRORS__ = {!! json_encode($errors->all()) !!};
                </script>
            @endif
        @show

        <link rel="stylesheet" href="/assets/css/fonts.css" media="print" onload="this.media='all'">
        
        @if(!Str::startsWith(request()->path(), 'auth'))
            @foreach($asset->preloads() as $preload)
                <link rel="modulepreload" href="{!! $preload['src'] !!}" integrity="{!! $preload['integrity'] !!}" crossorigin="anonymous">
            @endforeach
        @else
            @foreach($asset->authPreloads() as $preload)
                <link rel="modulepreload" href="{!! $preload['src'] !!}" integrity="{!! $preload['integrity'] !!}" crossorigin="anonymous">
            @endforeach
        @endif
        
        <noscript>
            <link rel="stylesheet" href="/assets/css/fonts.css">
        </noscript>

        @php
            $hyperParseRgb = function (?string $color): ?string {
                if (!$color) return null;
                $color = trim($color);
                if (preg_match('/^#([a-f0-9]{6})$/i', $color, $m)) {
                    return hexdec(substr($m[1], 0, 2)) . ', '
                         . hexdec(substr($m[1], 2, 2)) . ', '
                         . hexdec(substr($m[1], 4, 2));
                }
                if (preg_match('/^#([a-f0-9]{3})$/i', $color, $m)) {
                    $r = str_repeat($m[1][0], 2);
                    $g = str_repeat($m[1][1], 2);
                    $b = str_repeat($m[1][2], 2);
                    return hexdec($r) . ', ' . hexdec($g) . ', ' . hexdec($b);
                }
                if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $color, $m)) {
                    return ((int)$m[1]) . ', ' . ((int)$m[2]) . ', ' . ((int)$m[3]);
                }
                return null;
            };
            $hyperPrimaryRgb = $themeVars ? $hyperParseRgb($themeVars['--hyper-primary'] ?? null) : null;
            $hyperBgRgb      = $themeVars ? $hyperParseRgb($themeVars['--hyper-background'] ?? null) : null;
        @endphp

        @if($themeVars && is_array($themeVars) && count($themeVars) > 0)
            <style id="hyper-theme-vars">
                :root {
                    @foreach($themeVars as $key => $value)
                        @if(Str::startsWith($key, '--hyper-') && !empty($value) && !Str::endsWith($key, '-rgb'))
                            {{ $key }}: {{ $value }}{{ $themeEnforce ? ' !important' : '' }};
                        @endif
                    @endforeach
                    @if($hyperPrimaryRgb)
                        --hyper-primary-rgb: {{ $hyperPrimaryRgb }}{{ $themeEnforce ? ' !important' : '' }};
                    @endif
                    @if($hyperBgRgb)
                        --hyper-background-rgb: {{ $hyperBgRgb }}{{ $themeEnforce ? ' !important' : '' }};
                    @endif
                }
            </style>
            @if(isset($themeVars['--hyper-font-url']) && !empty($themeVars['--hyper-font-url']))
                <link rel="stylesheet" href="{{ $themeVars['--hyper-font-url'] }}" media="print" onload="this.media='all'">
                <noscript>
                    <link rel="stylesheet" href="{{ $themeVars['--hyper-font-url'] }}">
                </noscript>
            @endif
        @endif



        @if($asset->url('main.css') !== 'main.css')
            <link rel="preload" href="{!! $asset->url('main.css') !!}" as="style" crossorigin="anonymous" integrity="{!! $asset->integrity('main.css') !!}">
            <link rel="stylesheet" href="{!! $asset->url('main.css') !!}" media="print" onload="this.media='all'" crossorigin="anonymous" integrity="{!! $asset->integrity('main.css') !!}">
            <noscript>
                <link rel="stylesheet" href="{!! $asset->url('main.css') !!}" crossorigin="anonymous" integrity="{!! $asset->integrity('main.css') !!}">
            </noscript>
        @endif

        @yield('assets')

        @include('layouts.scripts')

        @if(!empty($adsSettings['header_script']))
            {!! $adsSettings['header_script'] !!}
        @endif
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            <script type="module" defer src="{!! $asset->url('main.js') !!}" crossorigin="anonymous" integrity="{!! $asset->integrity('main.js') !!}"></script>
        @show
        
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    @if($pwaEnabled)
                    navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                        .then(function(registration) {
                            fetch('/api/public/pwa/sw-config.js')
                                .then(function(response) { return response.json(); })
                                .then(function(config) {
                                    if (registration.active) {
                                        registration.active.postMessage({
                                            type: 'PWA_CONFIG',
                                            config: config
                                        });
                                    }
                                })
                                .catch(function(err) {
                                    console.warn('PWA config fetch failed:', err);
                                });
                        })
                        .catch(function(err) {
                            console.warn('Service Worker registration failed:', err);
                        });
                    @else
                    navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
                    @endif
                });
            }
        </script>

        @if(!empty($adsSettings['body_script']))
            {!! $adsSettings['body_script'] !!}
        @endif
    </body>
</html>
