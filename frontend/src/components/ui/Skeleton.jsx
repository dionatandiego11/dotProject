import PropTypes from 'prop-types'

function shapeStyle(shape, width, height) {
    if (shape === 'circle') {
        return {
            width: width || height || 40,
            height: height || width || 40,
            borderRadius: '50%',
        }
    }
    return {
        width: width || '100%',
        height: height || 16,
        borderRadius: 'var(--radius-md)',
    }
}

export default function Skeleton({
    shape = 'text',
    lines = 1,
    width,
    height,
    animate = true,
    style,
}) {
    const count = Math.max(1, lines)
    const items = Array.from({ length: count }, (_, index) => index)

    return (
        <div style={{ display: 'grid', gap: '0.5rem' }}>
            {items.map((index) => (
                <span
                    key={`${shape}-${index}`}
                    style={{
                        display: 'block',
                        background: 'linear-gradient(90deg, var(--color-gray-200) 0%, var(--color-gray-100) 50%, var(--color-gray-200) 100%)',
                        backgroundSize: '220% 100%',
                        ...shapeStyle(shape, width, height),
                        ...(animate ? { animation: 'skeleton-shimmer 1.2s ease-in-out infinite' } : {}),
                        ...style,
                    }}
                />
            ))}
            {animate && (
                <style>{`
                    @keyframes skeleton-shimmer {
                        0% { background-position: 200% 0; }
                        100% { background-position: -200% 0; }
                    }
                `}</style>
            )}
        </div>
    )
}

Skeleton.propTypes = {
    shape: PropTypes.oneOf(['text', 'rect', 'circle']),
    lines: PropTypes.number,
    width: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    height: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    animate: PropTypes.bool,
    style: PropTypes.object,
}
