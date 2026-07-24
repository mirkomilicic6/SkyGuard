<aside class="main-sidebar {{ config('adminlte.classes_sidebar', 'sidebar-dark-primary elevation-4') }}">

    {{-- Sidebar brand logo --}}
    @if(config('adminlte.logo_img_xl'))
        @include('adminlte::partials.common.brand-logo-xl')
    @else
        @include('adminlte::partials.common.brand-logo-xs')
    @endif

    {{-- Sidebar menu --}}
    <div class="sidebar">
        <nav class="pt-2">
            <ul class="nav nav-pills nav-sidebar flex-column {{ config('adminlte.classes_sidebar_nav', '') }}"
                data-widget="treeview" role="menu"
                @if(config('adminlte.sidebar_nav_animation_speed') != 300)
                    data-animation-speed="{{ config('adminlte.sidebar_nav_animation_speed') }}"
                @endif
                @if(!config('adminlte.sidebar_nav_accordion'))
                    data-accordion="false"
                @endif>
                {{-- Configured sidebar links --}}
                @each('adminlte::partials.sidebar.menu-item', $adminlte->menu('sidebar'), 'item')
            </ul>
        </nav>
    </div>

</aside>

@push('js')
<script>
(function () {
    const STORAGE_KEY = 'sidebarCollapsedGroups';
    let collapsedGroups = [];
    try { collapsedGroups = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) {}

    document.querySelectorAll('.nav-sidebar > li.ph-menu-header').forEach(function (header) {
        const label = header.querySelector('span')?.textContent.trim() || header.textContent.trim();
        const groupItems = [];
        let el = header.nextElementSibling;
        while (el && !el.classList.contains('ph-menu-header')) {
            groupItems.push(el);
            el = el.nextElementSibling;
        }

        function applyState(collapsed) {
            groupItems.forEach(function (item) { item.style.display = collapsed ? 'none' : ''; });
            header.classList.toggle('ph-menu-collapsed', collapsed);
        }

        if (collapsedGroups.indexOf(label) !== -1) applyState(true);

        header.addEventListener('click', function () {
            const next = !header.classList.contains('ph-menu-collapsed');
            applyState(next);
            if (next) {
                if (collapsedGroups.indexOf(label) === -1) collapsedGroups.push(label);
            } else {
                collapsedGroups = collapsedGroups.filter(function (g) { return g !== label; });
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsedGroups));
        });
    });
})();
</script>
@endpush
