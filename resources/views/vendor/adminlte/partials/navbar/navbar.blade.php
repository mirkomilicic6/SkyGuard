@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

<nav class="main-header navbar
    {{ config('adminlte.classes_topnav_nav', 'navbar-expand') }}
    {{ config('adminlte.classes_topnav', 'navbar-white navbar-light') }}">

    {{-- Navbar left links --}}
    <ul class="navbar-nav">
        {{-- Left sidebar toggler link --}}
        @include('adminlte::partials.navbar.menu-item-left-sidebar-toggler')

        {{-- Global search (admin only) --}}
        @auth
        @if(auth()->user()->hasRole('admin'))
        <li class="nav-item d-none d-sm-flex align-items-center" id="global-search" style="position:relative">
            <div class="input-group input-group-sm" style="width:260px">
                <input type="text" id="global-search-input" class="form-control form-control-navbar"
                       placeholder="Pretraži dronove, pilote, postaje..." autocomplete="off">
                <div class="input-group-append">
                    <span class="input-group-text" style="background:transparent;border-left:none;color:rgba(255,255,255,.6)">
                        <i class="fas fa-search" style="font-size:.8rem"></i>
                    </span>
                </div>
            </div>
            <div id="global-search-results"
                 style="display:none;position:absolute;top:100%;left:0;margin-top:4px;min-width:320px;max-width:380px;
                        background:#0d2460;border:1px solid rgba(240,192,64,0.25);border-radius:8px;
                        box-shadow:0 8px 28px rgba(0,0,0,.4);z-index:1050;max-height:420px;overflow-y:auto"></div>
        </li>

        @push('js')
        <script>
        (function () {
            const input   = document.getElementById('global-search-input');
            const results = document.getElementById('global-search-results');
            let debounceTimer = null;

            const GROUPS = {
                drones: { icon: 'fa-helicopter', label: 'Dronovi', color: '#3c8dbc' },
                pilots: { icon: 'fa-user', label: 'Piloti', color: '#28a745' },
                stations: { icon: 'fa-shield-alt', label: 'Postaje', color: '#17a2b8' },
                administrations: { icon: 'fa-building', label: 'Uprave', color: '#f0c040' },
            };

            function renderResults(data) {
                const groupsWithData = Object.keys(GROUPS).filter(k => (data[k] || []).length);
                if (!groupsWithData.length) {
                    results.innerHTML = '<p class="text-center py-3 my-0" style="font-size:.83rem;color:rgba(255,255,255,.4)">Nema rezultata</p>';
                    results.style.display = 'block';
                    return;
                }
                results.innerHTML = groupsWithData.map(key => {
                    const group = GROUPS[key];
                    const items = data[key].map(item => `
                        <a href="${item.url}" class="d-flex align-items-center px-3 py-2" style="font-size:.83rem;color:rgba(255,255,255,.85);text-decoration:none;gap:.6rem">
                            <i class="fas ${group.icon}" style="color:${group.color};width:16px;text-align:center;flex-shrink:0"></i>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${item.label}</span>
                        </a>
                    `).join('');
                    return `
                        <div style="padding:.4rem 1rem 0.2rem;font-size:.68rem;font-weight:700;letter-spacing:.05em;color:rgba(255,255,255,.35)">${group.label.toUpperCase()}</div>
                        ${items}
                    `;
                }).join('<div style="border-top:1px solid rgba(255,255,255,.06);margin:.3rem 0"></div>');
                results.style.display = 'block';
            }

            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const q = input.value.trim();
                if (q.length < 2) {
                    results.style.display = 'none';
                    return;
                }
                debounceTimer = setTimeout(() => {
                    $.get('{{ route('search.index') }}', { q }, renderResults);
                }, 250);
            });

            document.addEventListener('click', function (e) {
                if (!e.target.closest('#global-search')) {
                    results.style.display = 'none';
                }
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') results.style.display = 'none';
            });
        })();
        </script>
        @endpush
        @endif
        @endauth

        {{-- Configured left links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-left'), 'item')

        {{-- Custom left links --}}
        @yield('content_top_nav_left')
    </ul>

    {{-- Navbar right links --}}
    <ul class="navbar-nav ml-auto">
        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Language switcher --}}
        @php $lang = session('locale', config('app.locale')); @endphp
        <li class="nav-item d-flex align-items-center mr-2" style="gap:.35rem">
            <i class="fas fa-globe-europe text-muted" style="font-size:.9rem"></i>
            <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                <a href="{{ route('language.switch', 'hr') }}"
                   class="btn {{ $lang === 'hr' ? 'btn-warning' : 'btn-outline-secondary' }}"
                   title="Hrvatski">HR</a>
                <a href="{{ route('language.switch', 'en') }}"
                   class="btn {{ $lang === 'en' ? 'btn-warning' : 'btn-outline-secondary' }}"
                   title="English">EN</a>
            </div>
        </li>

        {{-- Notification bell (admin + pilot) --}}
        @auth
        @if(auth()->user()->hasRole(['admin', 'pilot']))
        <li class="nav-item dropdown" id="notif-bell">
            <a href="#" class="nav-link" data-toggle="dropdown" aria-expanded="false" onclick="return false;">
                <i class="far fa-bell" style="font-size:1.1rem"></i>
                <span id="notif-badge" class="badge badge-danger badge-pill navbar-badge" style="display:none;font-size:.6rem;padding:2px 5px;top:5px"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-0" style="min-width:300px;max-width:340px;border:none;box-shadow:0 4px 24px rgba(0,0,0,.18)">
                <div style="background:#0d2460;color:#fff;padding:.6rem 1rem;font-size:.8rem;font-weight:700;letter-spacing:.05em;border-radius:4px 4px 0 0">
                    OBAVIJESTI
                </div>
                <div id="notif-items" style="max-height:340px;overflow-y:auto;background:#fff">
                    <p class="text-center text-muted py-3 my-0" style="font-size:.83rem">Nema novih obavijesti</p>
                </div>
                <div style="border-top:1px solid #eee">
                    <a href="{{ route('notifications.markAllRead') }}" class="d-block text-center py-2 border-bottom" style="font-size:.78rem;color:#0d2460;font-weight:600">
                        Označi sve kao pročitano
                    </a>
                    <a href="{{ route('notifications.history') }}" class="d-block text-center py-2" style="font-size:.78rem;color:#555">
                        <i class="fas fa-list mr-1"></i> Prikaži sve obavijesti
                    </a>
                </div>
            </div>
        </li>

        @push('js')
        <script>
        (function () {
            function refreshNotifications() {
                $.get('{{ route("notifications.data") }}', function (data) {
                    var count = parseInt(data.label) || 0;
                    if (count > 0) {
                        $('#notif-badge').text(count).show();
                    } else {
                        $('#notif-badge').hide();
                    }
                    if (data.dropdown) {
                        $('#notif-items').html(data.dropdown);
                    }
                });
            }
            refreshNotifications();
            setInterval(refreshNotifications, 60000);
        })();
        </script>
        @endpush
        @endif
        @endauth

        {{-- Configured right links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-right'), 'item')

        {{-- User menu link --}}
        @if(Auth::user())
            @if(config('adminlte.usermenu_enabled'))
                @include('adminlte::partials.navbar.menu-item-dropdown-user-menu')
            @else
                @include('adminlte::partials.navbar.menu-item-logout-link')
            @endif
        @endif

        {{-- Right sidebar toggler link --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.navbar.menu-item-right-sidebar-toggler')
        @endif
    </ul>

</nav>
