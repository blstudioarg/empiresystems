import {AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig} from 'remotion';

export const MyComposition: React.FC = () => {
    const frame = useCurrentFrame();
    const {fps, durationInFrames} = useVideoConfig();

    const opacity = interpolate(frame, [0, fps], [0, 1], {
        extrapolateRight: 'clamp',
    });

    const scale = interpolate(frame, [0, durationInFrames], [0.9, 1], {
        extrapolateRight: 'clamp',
    });

    return (
        <AbsoluteFill
            style={{
                backgroundColor: '#0b1220',
                alignItems: 'center',
                justifyContent: 'center',
            }}
        >
            <div
                style={{
                    opacity,
                    transform: `scale(${scale})`,
                    fontFamily: 'sans-serif',
                    fontSize: 80,
                    fontWeight: 700,
                    color: 'white',
                }}
            >
                Empire Systems CRM
            </div>
        </AbsoluteFill>
    );
};
