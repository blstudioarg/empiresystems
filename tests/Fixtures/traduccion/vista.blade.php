{{-- Fixture del extractor de traducciones (feature 050). No es una vista real. --}}
<h1>{{ __('Hola mundo') }}</h1>
<p>@lang("Texto con comillas dobles")</p>
<p>{{ __('Con \'comilla\' simple') }}</p>
<p>{!! __('Ayuda con <strong>negrita</strong>') !!}</p>
<p>{{ __($variable) }}</p>
