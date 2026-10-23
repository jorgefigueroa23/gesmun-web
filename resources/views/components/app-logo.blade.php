@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex h-8 w-8 items-center justify-center">
            <x-app-logo-icon class="h-8 w-auto" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex h-8 w-8 items-center justify-center">
            <x-app-logo-icon class="h-8 w-auto" />
        </x-slot>
    </flux:brand>
@endif
