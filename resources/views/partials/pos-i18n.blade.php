{{--
    Traducción de los JS (feature 050). `traduccion.js` define `__t()` en todas las páginas (devuelve
    el español si no hay diccionario). El diccionario solo se inyecta cuando el request ya está en
    un idioma traducido, es decir, en las rutas del POS (middleware `idioma.pos`) con el POS en un
    idioma distinto del español: el resto de pantallas no cargan ni un byte de más
    (docs/04-front-guidelines.md § "Peso y cacheo de assets").
--}}
@if (tenant() && \App\Traduccion\CargadorTraducciones::esIdiomaTraducido(app()->getLocale()))
    <script>window.posI18n = @json(app(\App\Traduccion\MemoriaTraducciones::class)->diccionario('pos', app()->getLocale()));</script>
@endif
<script src="@assetv('js/traduccion.js')"></script>
