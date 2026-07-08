(function ($) {
    "use strict"

    var $form = $("#apariencia-form");

    if (!$form.length) {
        return;
    }

    var updateUrl = $form.attr("action");
    var csrfToken = $form.find('input[name="_token"]').val();

    // Petición en curso y temporizador de debounce por campo: el picker de color dispara
    // "change" en cada paso mientras se arrastra (o cada tecla si se escribe el hex a mano), así
    // que sin esto se disparaban varios POST concurrentes por campo y, al no garantizarse el
    // orden de respuesta, una petición vieja podía resolverse después que la más reciente y
    // pisar en BD el color final elegido con uno intermedio (el color "no cambiaba" pese a
    // verse aplicado un instante en pantalla).
    var xhrPorCampo = {};
    var timeoutPorCampo = {};

    function enviarCampo(campo, formData, onSuccess) {
        if (xhrPorCampo[campo]) {
            xhrPorCampo[campo].abort();
        }

        xhrPorCampo[campo] = $.ajax({
            url: updateUrl,
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            headers: { Accept: "application/json" },
        })
            .done(function (response) {
                if (typeof onSuccess === "function") {
                    onSuccess(response);
                }
                window.showToast("success", response.message || "Guardado correctamente.");
            })
            .fail(function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var primerError = Object.values(xhr.responseJSON.errors)[0][0];
                    window.showToast("danger", primerError);
                } else {
                    window.showToast("danger", "Ocurrió un error inesperado. Inténtalo de nuevo.");
                }
            })
            .always(function () {
                xhrPorCampo[campo] = null;
            });
    }

    function enviarCampoConDebounce(campo, formData, onSuccess) {
        if (timeoutPorCampo[campo]) {
            clearTimeout(timeoutPorCampo[campo]);
        }

        timeoutPorCampo[campo] = setTimeout(function () {
            timeoutPorCampo[campo] = null;
            enviarCampo(campo, formData, onSuccess);
        }, 400);
    }

    // Pickr devuelve #RRGGBBAA (con canal alfa) aunque el componente de opacidad esté
    // deshabilitado; el backend valida hex de 6 dígitos, así que se recorta el alfa aquí.
    function normalizarHex(hex) {
        return "#" + hex.replace("#", "").substring(0, 6).toUpperCase();
    }

    function construirFormData(campo, valor) {
        var formData = new FormData();
        formData.append("_token", csrfToken);
        formData.append("_method", "PUT");
        formData.append(campo, valor);

        return formData;
    }

    // Colores: Pickr (vendorizado en public/vendor/pickr) reemplaza a jquery-asColorPicker.
    // Cada swatch abre el popup de Pickr; el input de texto asociado solo muestra el hex
    // (readonly) y es el que viaja en el guardado automático. "change" se dispara en cada
    // arrastre dentro del popup (preview en vivo), "save" solo al confirmar con el botón
    // Guardar del propio picker: por eso el guardado en servidor va atado a "save" y no a
    // "change" (evita el aluvión de POST que producía el picker anterior en cada movimiento).
    $(".color-picker-swatch").each(function () {
        var $swatch = $(this);
        var $input = $swatch.siblings(".color-picker-input");
        var campo = $input.attr("name");
        var valorInicial = $input.val() || "#000000";

        var pickr = Pickr.create({
            el: $swatch.get(0),
            theme: "classic",
            default: valorInicial,
            comparison: false,
            components: {
                preview: true,
                opacity: false,
                hue: true,
                interaction: {
                    hex: true,
                    input: true,
                    save: true,
                },
            },
        });

        pickr.on("change", function (color) {
            var hex = normalizarHex(color.toHEXA().toString());
            aplicarVariableCss(campo, hex);
        });

        pickr.on("save", function (color) {
            if (!color) {
                return;
            }

            var hex = normalizarHex(color.toHEXA().toString());
            $input.val(hex);
            aplicarVariableCss(campo, hex);
            enviarCampoConDebounce(campo, construirFormData(campo, hex));
            pickr.hide();
        });
    });

    // Logo: preview inmediato + subida automática al seleccionar el archivo. `claseMenu` es
    // opcional: solo los logos del menú lateral (nav-header) necesitan reflejarse ahí en vivo;
    // los de la pantalla de login no tienen equivalente visible en la propia configuración.
    function registrarInputLogo(inputId, previewId, campo, claseMenu) {
        $(inputId).on("change", function (event) {
            var file = event.target.files[0];
            var $preview = $(previewId);

            if (!file) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                $preview.attr("src", e.target.result).show();
            };
            reader.readAsDataURL(file);

            var formData = new FormData();
            formData.append("_token", csrfToken);
            formData.append("_method", "PUT");
            formData.append(campo, file);

            enviarCampo(campo, formData, function () {
                if (claseMenu) {
                    actualizarLogoEnMenu(claseMenu, $preview.attr("src"));
                }
            });
        });
    }

    registrarInputLogo("#logo", "#logo-preview", "logo", "brand-title");
    registrarInputLogo("#logo_mini", "#logo-mini-preview", "logo_mini", "logo-abbr");
    registrarInputLogo("#login_logo", "#login-logo-preview", "login_logo");
    registrarInputLogo("#login_imagen", "#login-imagen-preview", "login_imagen");
    registrarInputLogo("#logo_facturacion", "#logo-facturacion-preview", "logo_facturacion");
    registrarInputLogo("#favicon", "#favicon-preview", "favicon");

    // Campos de texto (título de login, redes sociales): se guardan al perder el foco.
    $("#titulo_login, #facebook_url, #instagram_url").on("change", function () {
        var $input = $(this);
        var campo = $input.attr("name");
        var valor = $input.val();

        enviarCampo(campo, construirFormData(campo, valor));
    });

    function actualizarLogoEnMenu(claseMenu, dataUrl) {
        var $navLogo = $(".nav-header .brand-logo");
        var $imgActual = $navLogo.find("img." + claseMenu);

        if ($imgActual.length) {
            $imgActual.attr("src", dataUrl);
            return;
        }

        $navLogo.children("svg." + claseMenu).hide();
        $navLogo.append($('<img class="' + claseMenu + '" alt="Logo" style="height: 33px;">').attr("src", dataUrl));
    }

    // El motor de esquemas de color del template (dzSettings) fija en <body> un atributo
    // data-primary/data-secondary cuya regla en style.css redefine ahí mismo estas variables;
    // como <body> queda más cerca del contenido que <html>, solo tocar documentElement (como
    // hacía la versión anterior) no gana la herencia. Se setean en ambos, vía setProperty con
    // prioridad "important" para asegurar que el inline del tenant gane sobre esa regla.
    function aplicarVariableCss(campo, valor) {
        [document.documentElement.style, document.body.style].forEach(function (root) {
            if (campo === "color_primario") {
                root.setProperty("--primary", valor, "important");
                root.setProperty("--primary-hover", oscurecer(valor, 0.85), "important");

                for (var i = 1; i <= 9; i++) {
                    root.setProperty("--rgba-primary-" + i, hexARgba(valor, i / 10), "important");
                }
            } else if (campo === "color_secundario") {
                root.setProperty("--secondary", valor, "important");
            } else if (campo === "color_topbar") {
                root.setProperty("--topbar-bg", valor, "important");
            }
        });

        if (campo === "color_primario") {
            actualizarLordIcons("primary", valor);
        } else if (campo === "color_secundario") {
            actualizarLordIcons("secondary", valor);
        }
    }

    // El color de un <lord-icon> se fija vía atributo "colors" (resources/views/components/
    // lordicon.blade.php), no vía variable CSS: cambiar --primary/--secondary no lo actualiza
    // solo. Se reescribe el atributo de cada ícono visible en la página, preservando el otro
    // color (primary/secondary) tal cual estaba.
    function actualizarLordIcons(tipo, valor) {
        document.querySelectorAll("lord-icon").forEach(function (icon) {
            var partes = {};

            (icon.getAttribute("colors") || "").split(",").forEach(function (par) {
                var kv = par.split(":");
                if (kv.length === 2) {
                    partes[kv[0].trim()] = kv[1].trim();
                }
            });

            partes[tipo] = valor;

            icon.setAttribute(
                "colors",
                Object.keys(partes).map(function (clave) {
                    return clave + ":" + partes[clave];
                }).join(",")
            );
        });
    }

    function hexARgb(hex) {
        hex = hex.replace("#", "");

        return [
            parseInt(hex.substring(0, 2), 16),
            parseInt(hex.substring(2, 4), 16),
            parseInt(hex.substring(4, 6), 16),
        ];
    }

    function hexARgba(hex, alpha) {
        var rgb = hexARgb(hex);

        return "rgba(" + rgb[0] + ", " + rgb[1] + ", " + rgb[2] + ", " + alpha + ")";
    }

    function oscurecer(hex, factor) {
        var rgb = hexARgb(hex).map(function (canal) {
            return Math.round(canal * factor);
        });

        return "#" + rgb.map(function (canal) {
            return ("0" + canal.toString(16)).slice(-2).toUpperCase();
        }).join("");
    }
})(jQuery);
