import {AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig} from 'remotion';

export const TitleCard: React.FC<{title: string; subtitle: string}> = ({title, subtitle}) => {
  const frame = useCurrentFrame();
  const {durationInFrames, fps} = useVideoConfig();

  const fadeIn = interpolate(frame, [0, fps * 0.5], [0, 1], {
    extrapolateRight: 'clamp',
  });
  const fadeOut = interpolate(
    frame,
    [durationInFrames - fps * 0.5, durationInFrames],
    [1, 0],
    {extrapolateLeft: 'clamp'}
  );
  const opacity = Math.min(fadeIn, fadeOut);

  return (
    <AbsoluteFill
      style={{
        backgroundColor: '#0B0F1A',
        justifyContent: 'center',
        alignItems: 'center',
        opacity,
      }}
    >
      <div
        style={{
          fontFamily: 'Arial, sans-serif',
          color: '#FFFFFF',
          fontSize: 64,
          fontWeight: 700,
          textAlign: 'center',
          padding: '0 120px',
        }}
      >
        {title}
      </div>
      <div
        style={{
          fontFamily: 'Arial, sans-serif',
          color: '#8FA3C8',
          fontSize: 32,
          marginTop: 24,
          textAlign: 'center',
          padding: '0 160px',
        }}
      >
        {subtitle}
      </div>
    </AbsoluteFill>
  );
};
