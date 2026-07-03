(function () {
    "use strict"

    if (typeof toastr === "undefined") {
        return;
    }

    toastr.options = {
        closeButton: true,
        debug: false,
        newestOnTop: true,
        progressBar: true,
        positionClass: "toast-top-right",
        preventDuplicates: true,
        showDuration: 300,
        hideDuration: 1000,
        timeOut: 5000,
        extendedTimeOut: 1000,
        showEasing: "swing",
        hideEasing: "linear",
        showMethod: "fadeIn",
        hideMethod: "fadeOut",
    };

    // Helper único para disparar notificaciones desde JS (p. ej. respuestas AJAX).
    // Usar esto en vez de construir alerts Bootstrap ad-hoc: mantiene todas las
    // notificaciones de la app en un solo sitio (toastr).
    window.showToast = function (type, message) {
        var metodo = type === "danger" ? "error" : type;

        if (typeof toastr[metodo] === "function") {
            toastr[metodo](message);
        }
    };
})();
