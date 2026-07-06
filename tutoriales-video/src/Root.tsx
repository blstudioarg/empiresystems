import type {FC} from 'react';
import {Composition} from 'remotion';
import {TutorialFacturacion} from './TutorialFacturacion';
import {FPS, VIDEO_DURATION_FRAMES} from './guion';

export const RemotionRoot: FC = () => {
  return (
    <Composition
      id="TutorialFacturacion"
      component={TutorialFacturacion}
      durationInFrames={VIDEO_DURATION_FRAMES}
      fps={FPS}
      width={1920}
      height={1080}
    />
  );
};
