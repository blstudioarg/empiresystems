import {AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig} from 'remotion';

export const Caption: React.FC<{titulo: string; texto: string}> = ({titulo, texto}) => {
  const frame = useCurrentFrame();
  const {durationInFrames, fps} = useVideoConfig();

  const slideIn = interpolate(frame, [0, fps * 0.4], [40, 0], {
    extrapolateRight: 'clamp',
  });
  const fadeOut = interpolate(
    frame,
    [durationInFrames - fps * 0.4, durationInFrames],
    [1, 0],
    {extrapolateLeft: 'clamp'}
  );

  return (
    <AbsoluteFill style={{justifyContent: 'flex-end', alignItems: 'flex-start'}}>
      <div
        style={{
          margin: '0 0 72px 72px',
          transform: `translateY(${slideIn}px)`,
          opacity: fadeOut,
          background: 'rgba(11, 15, 26, 0.85)',
          borderLeft: '6px solid #1D69D6',
          borderRadius: 8,
          padding: '20px 32px',
          maxWidth: 760,
          fontFamily: 'Arial, sans-serif',
          boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
        }}
      >
        <div style={{color: '#1D69D6', fontSize: 22, fontWeight: 700, letterSpacing: 1}}>
          {titulo.toUpperCase()}
        </div>
        <div style={{color: '#FFFFFF', fontSize: 28, marginTop: 6}}>{texto}</div>
      </div>
    </AbsoluteFill>
  );
};
