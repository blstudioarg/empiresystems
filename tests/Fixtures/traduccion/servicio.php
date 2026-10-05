<?php

// Fixture del extractor de traducciones (feature 050). No se carga nunca.
return [
    __('Mesa :mesa ocupada', ['mesa' => 1]),
    // Comillas dobles con escapes: Pint no la pasa a simples porque lleva una simple dentro.
    __("Mensaje \"doble\" y 'simple'"),
];
