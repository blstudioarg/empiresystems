<?php

use App\Support\MenuTenant;

/*
|--------------------------------------------------------------------------
| Traducción automática de la interfaz (feature 050)
|--------------------------------------------------------------------------
|
| Los textos propios de la app se marcan con `__('Texto en español')` (Blade/PHP) o
| `__t('Texto en español')` (JS): la clave es el propio texto en español. Para un idioma distinto
| del de origen, `App\Traduccion\CargadorTraducciones` sirve las traducciones desde base de datos
| (`traducciones` + correcciones del tenant). El comando `traducciones:sincronizar` extrae las
| claves de cada ámbito y traduce las pendientes con el proveedor (DeepL).
|
| Ver docs/01-arquitectura.md (Decisión 12) y specs/050-traduccion-pos/research.md.
|
*/

return [

    // Idiomas que se pueden elegir para el POS. La clave es el código que se guarda en
    // `configuraciones` (`pos.idioma`) y el locale que fija el middleware `IdiomaPos`.
    'idiomas' => [
        'es' => 'Español',
        'zh' => '中文 (chino simplificado)',
    ],

    'idioma_origen' => 'es',

    'proveedor' => 'deepl',

    'deepl' => [
        // Clave de la plataforma (no del tenant). Solo se lee aquí, compatible con config:cache.
        'api_key' => env('DEEPL_API_KEY'),
        'api_url' => env('DEEPL_API_URL') ?: 'https://api-free.deepl.com',
        // Código de destino de DeepL por idioma interno.
        'idiomas_destino' => [
            'zh' => 'ZH-HANS',
        ],
    ],

    // Timeouts (segundos): corto en el respaldo de primer uso (corre tras enviar la respuesta),
    // largo en el comando del deploy.
    'timeout_respaldo' => 5,
    'timeout_comando' => 30,

    // Textos por llamada a la API.
    'lote' => 50,

    // A partir de estos intentos fallidos un texto deja de reintentarse solo.
    'max_intentos' => 5,

    // Prefijo del glosario remoto; el nombre completo es `<prefijo><idioma>-<hash8 de las
    // entradas>`, p. ej. `empire-pos-es-zh-1a2b3c4d` (research D7).
    'glosario_prefijo' => 'empire-pos-es-',

    /*
    | Glosario: término en español => traducción fijada. Se envía a DeepL como glosario (se añade
    | sola la variante con minúscula inicial) y un texto que es exactamente un término (sin
    | distinguir mayúsculas) se traduce localmente, sin llamar a la API. Las frases con variables
    | («Hace :min min») solo se aplican así, como texto exacto. Lista a validar con el cliente
    | (spec, Assumptions). Cambiar una entrada no retraduce lo ya traducido: correr
    | `traducciones:sincronizar --retraducir`.
    */
    'glosario' => [
        'zh' => [
            'Precuenta' => '预结单',
            'Precuentas' => '预结单',
            'Ticket' => '小票',
            'Tickets' => '小票',
            'Factura simplificada' => '简易发票',
            'Facturas simplificadas' => '简易发票',
            'Cuenta' => '账单',
            'Cuentas' => '账单',
            'Cuenta aparcada' => '挂单',
            'Cuentas aparcadas' => '挂单',
            'Aparcadas' => '挂单',
            'Aparcar' => '挂单',
            'Mesa' => '餐桌',
            'Mesas' => '餐桌',
            'Sala' => '餐厅',
            'Zona' => '区域',
            'Zonas' => '区域',
            'Suplemento de zona' => '区域附加费',
            'Comensales' => '用餐人数',
            'Caja' => '收银台',
            'Fondo de cambio' => '备用金',
            'Arqueo' => '盘点现金',
            'Cierre de caja' => '收银结账',
            'Cierres de caja' => '收银结账记录',
            'Cobrar' => '收款',
            'Cobro' => '收款',
            'Cobro por partes' => '分开收款',
            'Opción' => '选项',
            'Opciones' => '选项',
            'Opciones de artículo' => '商品选项',
            'Grupo de opciones' => '选项组',
            'Grupos de opciones' => '选项组',
            'Reimpresión' => '重新打印',
            'Guardar' => '保存',
            'Transferir' => '转台',
            'Unir' => '并单',
            'Anular' => '作废',
            'Crear ticket' => '开单',
            'Caja abierta' => '收银台已开',
            'Caja cerrada' => '收银台已关',
            'Abrir caja' => '开启收银台',
            'Cerrar caja' => '收银结账',
            'Cierre' => '结账',
            'Último cierre' => '上次结账',
            'Historial de cierres' => '结账记录',
            'Cierres este mes' => '本月结账次数',
            'Fondo inicial' => '初始备用金',
            'Reposición de fondo' => '补充备用金',
            'Cuadra' => '账实相符',
            'Cuadró' => '账实相符',
            'Sobrante' => '长款',
            'Faltante' => '短款',
            'Esperado' => '应有现金',
            'Contado' => '实点现金',
            'Conteo' => '点钞',
            'Movimientos' => '现金收支',
            'Suplemento' => '附加费',
            'Precuenta dada' => '已出预结单',
            'Base imponible' => '税基',
            'Dto.' => '折扣',
            // Frases fijadas con variables (solo locales, ver arriba).
            'Hace :min min' => ':min 分钟前',
            'Precuenta hace :min min' => '预结单 · :min 分钟前',
            ':impuesto incluido' => '含 :impuesto',
            ':n artículo' => ':n 件商品',
            ':n artículos' => ':n 件商品',
            ':n ticket' => ':n 张小票',
            ':n tickets' => ':n 张小票',
            ':n movimiento' => ':n 笔收支',
            ':n movimientos' => ':n 笔收支',
            'Sobran :importe' => '长款 :importe',
            'Faltan :importe' => '短款 :importe',
            'Sobraron :importe' => '长款 :importe',
            'Faltaron :importe' => '短款 :importe',
            'Abrir caja con :importe' => '以 :importe 开启收银台',
            'base :importe' => '税基 :importe',
            // Frases donde DeepL no aplica el glosario («mesa» → «tabla», «cuenta» → «cuenta
            // bancaria»), fijadas tras revisar la traducción real del 2026-10-05.
            '¿Eliminar la mesa «:nombre»?' => '删除餐桌“:nombre”？',
            '¿Eliminar la zona «:nombre»?' => '删除区域“:nombre”？',
            '¿Transferir la cuenta a «:mesa»?' => '将账单转到“:mesa”？',
            'No se puede eliminar «:nombre»: tiene :n mesa(s). Muévelas o elimínalas primero.' => '无法删除“:nombre”：该区域有 :n 张餐桌。请先移动或删除这些餐桌。',
            'No se puede eliminar «:nombre»: tiene una cuenta abierta. Cóbrala o anúlala primero.' => '无法删除“:nombre”：该餐桌有未结账单。请先收款或作废账单。',
            'Esta caja ya la cerró :quien a las :hora.' => '该收银台已由 :quien 于 :hora 结账。',
            'Pide a un responsable que la abra para poder cobrar. El ticket no se pierde.' => '请负责人开启收银台后再收款。小票不会丢失。',
            'No quedan tantas unidades pendientes de «:concepto».' => '“:concepto”没有这么多待收款的数量。',
            '«:articulo» necesita elegir «:grupo» antes de comandarse.' => '“:articulo”下单前需要选择“:grupo”。',
            'No se puede eliminar «:nombre»: se usa en :n artículo(s).' => '无法删除“:nombre”：已用于 :n 个商品。',
        ],
    ],

    // Siglas y marcas que no se traducen nunca (se protegen con `<k>` + `ignore_tags`).
    'no_traducir' => ['VERI*FACTU', 'IVA', 'IGIC', 'IPSI', 'NIF', 'TPV', '€'],

    /*
    | Ámbitos de extracción (research D5): archivos o carpetas (relativos a la raíz) donde el
    | extractor busca `__('…')`, `@lang('…')` y `__t('…')`, más claves que no salen por expresión
    | regular (`claves_extra`: textos sueltos o clases con un método estático que las devuelve).
    | Extender la traducción a otra parte de la app = declarar un ámbito nuevo.
    */
    'ambitos' => [
        'pos' => [
            'rutas' => [
                'resources/views/pos',
                'resources/views/caja',
                'resources/views/ayuda/pos.blade.php',
                'resources/views/ayuda/pos-crear.blade.php',
                'resources/views/ayuda/pos-sala.blade.php',
                'resources/views/ayuda/pos-caja.blade.php',
                'resources/views/ayuda/pos-caja-cierres.blade.php',
                'resources/views/ayuda/pos-opciones.blade.php',
                'resources/views/facturas/ticket-80mm.blade.php',
                'resources/views/facturas/pdf.blade.php',
                'resources/views/partials/sidebar.blade.php',
                'resources/views/partials/ayuda-modal.blade.php',
                // Modal de confirmación global: en el POS traducido también sale en chino.
                'resources/views/partials/confirm-delete-modal.blade.php',
                'public/js/confirm-delete.js',
                'public/js/pos-form.js',
                'public/js/pos-ticket.js',
                'public/js/pos-catalogo.js',
                'public/js/pos-cobro.js',
                'public/js/pos-cuenta.js',
                'public/js/pos-precuenta.js',
                'public/js/pos-opciones.js',
                'public/js/pos-teclado.js',
                'public/js/pos-caja-bandeja.js',
                'public/js/pos-caja-apertura.js',
                'public/js/pos-caja-tpv.js',
                'public/js/plugins-init/pos-datatable.init.js',
                'public/js/plugins-init/pos-sala.init.js',
                'public/js/plugins-init/pos-plano-dibujo.js',
                'public/js/plugins-init/pos-sala-plano.init.js',
                'public/js/plugins-init/pos-sala-plano-servicio.init.js',
                'public/js/plugins-init/pos-sala-plano-gestion.init.js',
                'public/js/plugins-init/pos-caja.init.js',
                'public/js/plugins-init/pos-caja-cierres.init.js',
                'public/js/plugins-init/pos-opciones-datatable.init.js',
                'app/Http/Controllers/PosController.php',
                'app/Http/Controllers/Pos',
                'app/Http/Middleware/ModuloHosteleriaActivo.php',
                'app/Http/Requests/StoreTicketRequest.php',
                // Zonas y mesas: sus rutas viven en Configuración pero solo las usa el editor del
                // plano de la Sala, así que llevan el idioma del POS.
                'app/Http/Controllers/Configuracion/PosZonaController.php',
                'app/Http/Controllers/Configuracion/PosMesaController.php',
                'app/Http/Requests/AbrirCajaRequest.php',
                'app/Http/Requests/CerrarCajaRequest.php',
                'app/Http/Requests/MovimientoCajaRequest.php',
                'app/Services/CobradorCuenta.php',
                'app/Services/TransferidorCuenta.php',
                'app/Services/PrecuentaCuenta.php',
                'app/Services/AperturaCaja.php',
                'app/Services/CierreCaja.php',
                'app/Services/MovimientosCaja.php',
                'app/Services/RegistroTicket.php',
                'app/Exceptions/CajaCerradaException.php',
                'app/Exceptions/CajaYaAbiertaException.php',
                'app/Exceptions/CajaYaCerradaException.php',
                'app/Exceptions/TicketFueraDeTopeException.php',
                'app/Exceptions/PagoTicketDescuadradoException.php',
                'app/Exceptions/ObservacionRequeridaException.php',
                'app/Services/ResumenCaja.php',
                'app/Traduccion/Bilingue.php',
            ],
            'claves_extra' => [
                // Etiquetas del grupo POS del menú lateral (FR-009): salen del catálogo, no de un
                // `__()` literal.
                [MenuTenant::class, 'clavesTraduciblesPos'],
            ],
        ],
    ],

];
