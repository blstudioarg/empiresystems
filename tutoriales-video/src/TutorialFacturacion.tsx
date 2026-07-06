import {AbsoluteFill, OffthreadVideo, Sequence, staticFile} from 'remotion';
import {Caption} from './Caption';
import {TitleCard} from './TitleCard';
import {FPS, INTRO_DURATION_SEGUNDOS, PASOS} from './guion';

export const TutorialFacturacion: React.FC = () => {
  return (
    <AbsoluteFill style={{backgroundColor: '#000'}}>
      <Sequence from={0} durationInFrames={INTRO_DURATION_SEGUNDOS * FPS}>
        <TitleCard
          title="Flujo de facturación"
          subtitle="Clientes → Artículos → Facturas → PDF"
        />
      </Sequence>

      <Sequence from={INTRO_DURATION_SEGUNDOS * FPS}>
        <OffthreadVideo src={staticFile('grabacion.webm')} />
      </Sequence>

      {PASOS.map((paso) => (
        <Sequence
          key={paso.titulo}
          from={(INTRO_DURATION_SEGUNDOS + paso.from) * FPS}
          durationInFrames={paso.duration * FPS}
        >
          <Caption titulo={paso.titulo} texto={paso.texto} />
        </Sequence>
      ))}
    </AbsoluteFill>
  );
};
