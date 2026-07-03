(function ($) {
    "use strict"

    var $form = $("#apariencia-form");

    if (!$form.length) {
        return;
    }

    var updateUrl = $form.attr("action");
    var csrfToken = $form.find('input[name="_token"]').val();

    function enviarCampo(formData, onSuccess) {
        $.ajax({
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
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var primerError = Object.values(xhr.responseJSON.errors)[0][0];
                    window.showToast("danger", primerError);
                } else {
                    window.showToast("danger", "Ocurrió un error inesperado. Inténtalo de nuevo.");
                }
            });
    }

    function construirFormData(campo, valor) {
        var formData = new FormData();
        formData.append("_token", csrfToken);
        formData.append("_method", "PUT");
        formData.append(campo, valor);

        return formData;
    }

    // Colores: se guardan automáticamente al elegir un valor con el picker.
    $(".as_colorpicker").on("change", function () {
        var $input = $(this);
        var campo = $input.attr("name");
        var valor = $input.val();

        if (!valor) {
            return;
        }

        enviarCampo(construirFormData(campo, valor), function () {
            aplicarVariableCss(campo, valor);
        });
    });

    // Logo: preview inmediato + subida automática al seleccionar el archivo.
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

            enviarCampo(formData, function () {
                actualizarLogoEnMenu(claseMenu, $preview.attr("src"));
            });
        });
    }

    registrarInputLogo("#logo", "#logo-preview", "logo", "brand-title");
    registrarInputLogo("#logo_mini", "#logo-mini-preview", "logo_mini", "logo-abbr");

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

    function aplicarVariableCss(campo, valor) {
        var root = document.documentElement.style;

        if (campo === "color_primario") {
            root.setProperty("--primary", valor);
            root.setProperty("--primary-hover", oscurecer(valor, 0.85));

            for (var i = 1; i <= 9; i++) {
                root.setProperty("--rgba-primary-" + i, hexARgba(valor, i / 10));
            }
        } else if (campo === "color_secundario") {
            root.setProperty("--secondary", valor);
        } else if (campo === "color_topbar") {
            root.setProperty("--topbar-bg", valor);
        }
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
