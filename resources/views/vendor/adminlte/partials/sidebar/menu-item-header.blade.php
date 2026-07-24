<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-header ph-menu-header {{ $item['class'] ?? '' }}">

    <span>{{ is_string($item) ? $item : $item['header'] }}</span>
    <i class="fas fa-chevron-down ph-menu-toggle"></i>

</li>
