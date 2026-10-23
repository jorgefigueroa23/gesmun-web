{{-- Logo del sistema: reemplaza public/logo.png para cambiarlo --}}
<img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" {{ $attributes->merge(['class' => 'object-contain']) }}>
