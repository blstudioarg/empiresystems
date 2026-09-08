@props([
	'user' => null,
	// Diámetro en px del avatar. El botón de cámara escala solo a partir de este valor. Se emite
	// como estilo inline, que gana a cualquier hoja: dejarlo en null es lo correcto cuando el
	// tamaño depende del breakpoint (ver .profile-avatar-editor en app-overrides.css), porque un
	// inline haría que los media queries no se apliquen nunca. Sin valor, el CSS usa 96px.
	'size' => null,
	// Clases extra para la <img>. Ojo: la FORMA no va acá, va como border-radius del contenedor
	// (.avatar-editable), que la <img> hereda y el velo de carga también.
	'imgClass' => '',
])

@php
	$user = $user ?? auth()->user();
	// Un id propio por instancia: el <label> necesita un `for` y la misma página puede montar
	// dos editores a la vez (sidebar + perfil).
	$inputId = 'avatar-input-'.\Illuminate\Support\Str::random(8);
@endphp

{{--
	Cambio de foto de perfil: elegir el archivo lo guarda al momento (sin botón "Guardar"),
	manejado por `public/js/avatar-upload.js`. Ver docs/04-front-guidelines.md,
	"Cambiar la foto de perfil".
--}}
<form action="{{ route('profile.avatar.update') }}" method="POST" enctype="multipart/form-data"
	{{ $attributes->merge(['class' => 'avatar-editable-form']) }}>
	@csrf
	<div class="avatar-editable" @if ($size) style="--avatar-editable-size: {{ (int) $size }}px;" @endif>
		<img data-avatar-preview src="{{ $user->avatarUrl() }}" alt="Foto de {{ $user->name }}"
			class="{{ $imgClass }}">
		<label for="{{ $inputId }}" class="avatar-editable-edit" title="Cambiar foto">
			<i class="fas fa-camera"></i>
			<span class="visually-hidden">Cambiar foto de perfil</span>
		</label>
		<input type="file" id="{{ $inputId }}" name="avatar" accept="image/png,image/jpeg,image/webp"
			class="d-none" data-avatar-input>
		{{-- Slot para lo que vaya encima del avatar (el punto de estado del perfil, por ejemplo). --}}
		{{ $slot }}
	</div>
</form>
