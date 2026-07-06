export const FPS = 30;

/**
 * Tras grabar con Playwright, ajustar `from`/`duration` (en segundos) a los
 * timestamps reales de cada acción en el video grabado.
 */
export type PasoGuion = {
  titulo: string;
  texto: string;
  from: number; // segundos desde el inicio del video
  duration: number; // segundos que el cartel permanece en pantalla
};

export const INTRO_DURATION_SEGUNDOS = 3.5;

// Timestamps calibrados sobre la grabación real (grabacion.webm, 68.08s),
// grabada con Playwright contra midemo.localhost:8000.
export const PASOS: PasoGuion[] = [
  {
    titulo: 'Iniciar sesión',
    texto: 'Accedemos al CRM con nuestro usuario del tenant.',
    from: 0,
    duration: 9,
  },
  {
    titulo: 'Clientes',
    texto: 'Damos de alta un cliente nuevo con sus datos fiscales.',
    from: 9,
    duration: 15,
  },
  {
    titulo: 'Artículos',
    texto: 'Creamos un artículo del catálogo con precio e impuesto.',
    from: 24,
    duration: 13,
  },
  {
    titulo: 'Facturas',
    texto: 'Creamos una factura, elegimos el cliente y añadimos la línea.',
    from: 37,
    duration: 11,
  },
  {
    titulo: 'Emitir',
    texto: 'Emitimos la factura: a partir de aquí queda inmutable.',
    from: 48,
    duration: 8,
  },
  {
    titulo: 'PDF',
    texto: 'Abrimos el PDF final de la factura emitida.',
    from: 56,
    duration: 12,
  },
];

// Duración real de la grabación en bruto (sin el intro), medida con
// ffprobe sobre tutoriales-video/public/grabacion.webm.
export const GRABACION_DURATION_SEGUNDOS = 68.08;

export const VIDEO_DURATION_FRAMES = Math.round(
  (INTRO_DURATION_SEGUNDOS + GRABACION_DURATION_SEGUNDOS) * FPS
);
