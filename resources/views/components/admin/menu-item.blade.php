{{-- COMPONENTE: ítem del menú lateral --}}
@props(['href', 'icon' => 'ti-circle', 'label', 'active' => false])

<a href="{{ $href }}"
   class="flex items-center gap-3 px-3 py-2 rounded-md transition
          {{ $active ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
    <i class="ti {{ $icon }} text-lg"></i>
    <span>{{ $label }}</span>
</a>
