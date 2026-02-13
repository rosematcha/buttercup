import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InnerBlocks,
    InspectorControls,
    MediaUpload,
    MediaUploadCheck,
    useSettings,
} from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
    ColorPalette,
    GradientPicker,
    Button,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { useEffect, useState, useRef, useCallback } from '@wordpress/element';

const ALLOWED_BLOCKS = ['buttercup/row-column'];

const LAYOUT_PRESETS = {
    '1-1': [100],
    'equal': [50, 50],
    'left-golden': [66.66, 33.34],
    'right-golden': [33.34, 66.66],
    'left-heavy': [75, 25],
    'right-heavy': [25, 75],
    '3-equal': [33.33, 33.33, 33.34],
    'left-half': [50, 25, 25],
    'right-half': [25, 25, 50],
    'center-half': [25, 50, 25],
    '4-equal': [25, 25, 25, 25],
};

function getPresetsForColumns(count) {
    return Object.entries(LAYOUT_PRESETS)
        .filter(([, widths]) => widths.length === count)
        .map(([key, widths]) => ({ key, widths }));
}

function getColumnWidths(columns, columnLayout, columnWidths) {
    if (columnWidths && columnWidths.length === columns) {
        return columnWidths;
    }
    const preset = LAYOUT_PRESETS[columnLayout];
    if (preset && preset.length === columns) {
        return preset;
    }
    return Array(columns).fill(100 / columns);
}

function ColumnCountPicker({ columns, onChange }) {
    return (
        <div className="buttercup-row-layout-columns-picker">
            {[1, 2, 3, 4].map((n) => (
                <button
                    key={n}
                    type="button"
                    className={`buttercup-row-layout-columns-picker__btn${n === columns ? ' is-active' : ''}`}
                    onClick={() => onChange(n)}
                >
                    {n}
                </button>
            ))}
        </div>
    );
}

function LayoutPresetPicker({ columns, columnLayout, columnWidths, onChange }) {
    const presets = getPresetsForColumns(columns);
    if (presets.length <= 1) return null;

    const currentWidths = getColumnWidths(columns, columnLayout, columnWidths);

    return (
        <div className="buttercup-row-layout-presets">
            {presets.map(({ key, widths }) => {
                const isActive = widths.every((w, i) => Math.abs(w - currentWidths[i]) < 0.1);
                return (
                    <button
                        key={key}
                        type="button"
                        className={`buttercup-row-layout-presets__btn${isActive ? ' is-active' : ''}`}
                        onClick={() => onChange(key)}
                        aria-label={key}
                    >
                        {widths.map((w, i) => (
                            <span
                                key={i}
                                className="buttercup-row-layout-presets__col"
                                style={{ flex: `0 0 ${w}%` }}
                            />
                        ))}
                    </button>
                );
            })}
        </div>
    );
}

function BackgroundTypeSelector({ value, onChange }) {
    const types = [
        { key: 'none', label: __('None', 'buttercup') },
        { key: 'color', label: __('Color', 'buttercup') },
        { key: 'gradient', label: __('Gradient', 'buttercup') },
        { key: 'image', label: __('Image', 'buttercup') },
        { key: 'video', label: __('Video', 'buttercup') },
    ];

    return (
        <div className="buttercup-bg-type-selector">
            {types.map(({ key, label }) => (
                <button
                    key={key}
                    type="button"
                    className={`buttercup-bg-type-selector__btn${key === value ? ' is-active' : ''}`}
                    onClick={() => onChange(key)}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

function ColumnResizers({ widths, columns, columnGap, containerRef, onResize }) {
    const [dragging, setDragging] = useState(null);
    const [tooltip, setTooltip] = useState(null);
    const startXRef = useRef(0);
    const startWidthsRef = useRef([]);

    const handlePointerDown = useCallback((e, index) => {
        e.preventDefault();
        e.stopPropagation();
        const container = containerRef.current;
        if (!container) return;

        startXRef.current = e.clientX;
        startWidthsRef.current = [...widths];
        setDragging(index);
        setTooltip({
            index,
            left: Math.round(widths[index] * 10) / 10,
            right: Math.round(widths[index + 1] * 10) / 10,
        });

        const handlePointerMove = (moveEvent) => {
            const rect = container.getBoundingClientRect();
            const totalGapPx = (columns - 1) * columnGap;
            const availableWidth = rect.width - totalGapPx;
            const deltaX = moveEvent.clientX - startXRef.current;
            const deltaPct = (deltaX / availableWidth) * 100;

            const newWidths = [...startWidthsRef.current];
            let leftW = newWidths[index] + deltaPct;
            let rightW = newWidths[index + 1] - deltaPct;

            if (leftW < 10) {
                rightW -= (10 - leftW);
                leftW = 10;
            }
            if (rightW < 10) {
                leftW -= (10 - rightW);
                rightW = 10;
            }

            leftW = Math.round(leftW * 2) / 2;
            rightW = Math.round(rightW * 2) / 2;

            newWidths[index] = leftW;
            newWidths[index + 1] = rightW;

            onResize(newWidths);
            setTooltip({
                index,
                left: leftW,
                right: rightW,
            });
        };

        const handlePointerUp = () => {
            setDragging(null);
            setTooltip(null);
            document.removeEventListener('pointermove', handlePointerMove);
            document.removeEventListener('pointerup', handlePointerUp);
        };

        document.addEventListener('pointermove', handlePointerMove);
        document.addEventListener('pointerup', handlePointerUp);
    }, [widths, columns, columnGap, containerRef, onResize]);

    if (columns <= 1) return null;

    const handles = [];
    let cumulativePct = 0;
    for (let i = 0; i < columns - 1; i++) {
        cumulativePct += widths[i];
        const gapsBefore = i + 1;
        handles.push({
            index: i,
            position: cumulativePct,
            gapsBefore,
        });
    }

    return (
        <div className="buttercup-column-resizers" aria-hidden="true">
            {handles.map(({ index, position, gapsBefore }) => (
                <div
                    key={index}
                    className={`buttercup-column-resizer${dragging === index ? ' is-dragging' : ''}`}
                    style={{
                        left: `calc(${position}% + ${(gapsBefore * columnGap) - (columnGap / 2)}px)`,
                    }}
                    onPointerDown={(e) => handlePointerDown(e, index)}
                    tabIndex={-1}
                >
                    <div className="buttercup-column-resizer__handle">
                        <span className="buttercup-column-resizer__grip" />
                    </div>
                    {tooltip && tooltip.index === index && (
                        <div className="buttercup-column-resizer__tooltip">
                            {tooltip.left}% | {tooltip.right}%
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function getActiveBgType(attributes) {
    if (attributes.backgroundType && attributes.backgroundType !== 'none') {
        return attributes.backgroundType;
    }
    if (attributes.backgroundVideoUrl) return 'video';
    if (attributes.backgroundImageUrl) return 'image';
    if (attributes.backgroundGradient) return 'gradient';
    if (attributes.backgroundColor) return 'color';
    return 'none';
}

export default function Edit({ attributes, setAttributes, clientId }) {
    const {
        uniqueId,
        columns,
        columnLayout,
        columnWidths,
        columnGap,
        tabletLayout,
        mobileLayout,
        collapseOrder,
        reverseOnMobile,
        reverseOnTablet,
        verticalAlignment,
        maxWidth,
        maxWidthUnit,
        inheritMaxWidth,
        minHeight,
        minHeightUnit,
        paddingTop,
        paddingRight,
        paddingBottom,
        paddingLeft,
        tabletPadding,
        mobilePadding,
        marginTop,
        marginBottom,
        backgroundColor,
        backgroundGradient,
        backgroundImageId,
        backgroundImageUrl,
        backgroundImageSize,
        backgroundImagePosition,
        backgroundImageRepeat,
        hasParallax,
        backgroundVideoUrl,
        backgroundVideoId,
        backgroundType,
        overlayColor,
        overlayOpacity,
        overlayGradient,
        hideOnDesktop,
        hideOnTablet,
        hideOnMobile,
    } = attributes;

    const innerBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(clientId),
        [clientId]
    );

    const allBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(),
        []
    );

    const { replaceInnerBlocks } = useDispatch(blockEditorStore);

    const [layout] = useSettings('layout');
    const themeContentWidth = layout?.contentSize ? parseInt(layout.contentSize, 10) : 0;

    useEffect(() => {
        if (!uniqueId) {
            setAttributes({ uniqueId: 'rl-' + Math.random().toString(36).substr(2, 6) });
        } else {
            const duplicates = [];
            const collectRowLayouts = (blocks) => {
                blocks.forEach((block) => {
                    if (block.name === 'buttercup/row-layout' && block.clientId !== clientId && block.attributes.uniqueId === uniqueId) {
                        duplicates.push(block);
                    }
                    if (block.innerBlocks?.length) {
                        collectRowLayouts(block.innerBlocks);
                    }
                });
            };
            collectRowLayouts(allBlocks);

            if (duplicates.length > 0) {
                setAttributes({ uniqueId: 'rl-' + Math.random().toString(36).substr(2, 6) });
            }
        }
    }, []);

    useEffect(() => {
        const currentCount = innerBlocks.length;
        if (currentCount === columns) return;

        if (columns > currentCount) {
            const newBlocks = [...innerBlocks];
            for (let i = currentCount; i < columns; i++) {
                newBlocks.push(createBlock('buttercup/row-column'));
            }
            replaceInnerBlocks(clientId, newBlocks, false);
        } else if (columns < currentCount) {
            const kept = innerBlocks.slice(0, columns);
            const removed = innerBlocks.slice(columns);

            const lastKept = kept[kept.length - 1];
            const mergedInner = [
                ...(lastKept.innerBlocks || []),
                ...removed.flatMap((col) => col.innerBlocks || []),
            ];

            const updatedLast = createBlock(
                'buttercup/row-column',
                { ...lastKept.attributes },
                mergedInner
            );

            const newBlocks = [...kept.slice(0, -1), updatedLast];
            replaceInnerBlocks(clientId, newBlocks, false);
        }
    }, [columns]);

    const activeBgType = getActiveBgType(attributes);

    const innerRef = useRef(null);

    const handleColumnResize = useCallback((newWidths) => {
        setAttributes({ columnWidths: newWidths, columnLayout: 'custom' });
    }, [setAttributes]);

    const handleBgTypeChange = (type) => {
        const clearAttrs = {
            backgroundType: type,
            backgroundColor: '',
            backgroundGradient: '',
            backgroundImageUrl: '',
            backgroundImageId: 0,
            backgroundVideoUrl: '',
            backgroundVideoId: 0,
            hasParallax: false,
            overlayColor: '',
            overlayGradient: '',
        };
        setAttributes(clearAttrs);
    };

    const widths = getColumnWidths(columns, columnLayout, columnWidths);
    const gapOffset = columns > 1 ? ((columns - 1) * columnGap) / columns : 0;

    const wrapperClasses = [
        'buttercup-row-layout',
        hasParallax && backgroundImageUrl && 'has-parallax',
        hideOnDesktop && 'buttercup-hide-desktop',
        hideOnTablet && 'buttercup-hide-tablet',
        hideOnMobile && 'buttercup-hide-mobile',
        tabletLayout === 'collapse' && 'is-tablet-collapse',
        reverseOnTablet && 'is-tablet-reverse',
        mobileLayout === 'collapse' && 'is-mobile-collapse',
        reverseOnMobile && 'is-mobile-reverse',
    ].filter(Boolean).join(' ');

    const wrapperStyle = {};
    if (paddingTop || paddingRight || paddingBottom || paddingLeft) {
        wrapperStyle.padding = `${paddingTop}px ${paddingRight}px ${paddingBottom}px ${paddingLeft}px`;
    }
    if (marginTop) {
        wrapperStyle.marginTop = `${marginTop}px`;
    }
    if (marginBottom) {
        wrapperStyle.marginBottom = `${marginBottom}px`;
    }
    if (minHeight) {
        wrapperStyle.minHeight = `${minHeight}${minHeightUnit}`;
    }
    if (backgroundColor && activeBgType === 'color') {
        wrapperStyle.backgroundColor = backgroundColor;
    }
    if (backgroundGradient && activeBgType === 'gradient') {
        wrapperStyle.background = backgroundGradient;
    }
    if (backgroundImageUrl && activeBgType === 'image') {
        wrapperStyle.backgroundImage = `url(${backgroundImageUrl})`;
        wrapperStyle['--rl-bg-size'] = backgroundImageSize;
        wrapperStyle['--rl-bg-position'] = backgroundImagePosition;
        wrapperStyle['--rl-bg-repeat'] = backgroundImageRepeat;
    }

    const alignMap = {
        top: 'flex-start',
        center: 'center',
        bottom: 'flex-end',
        stretch: 'stretch',
    };

    const innerStyle = {
        display: 'flex',
        flexWrap: 'wrap',
        gap: `${columnGap}px`,
    };
    if (alignMap[verticalAlignment]) {
        innerStyle.alignItems = alignMap[verticalAlignment];
    }
    if (inheritMaxWidth && themeContentWidth) {
        innerStyle.maxWidth = `${themeContentWidth}px`;
        innerStyle.marginLeft = 'auto';
        innerStyle.marginRight = 'auto';
    } else if (!inheritMaxWidth && maxWidth) {
        innerStyle.maxWidth = `${maxWidth}${maxWidthUnit}`;
        innerStyle.marginLeft = 'auto';
        innerStyle.marginRight = 'auto';
    }

    const editorColumnStyles = widths.map((w, i) => {
        const basis = `calc(${w}% - ${gapOffset}px)`;
        return `#block-${clientId} .buttercup-row-column:nth-child(${i + 1}){flex:0 0 ${basis};max-width:${basis}}`;
    }).join('');

    const blockProps = useBlockProps({
        className: wrapperClasses,
        style: wrapperStyle,
    });

    const hasOverlay = overlayColor || overlayGradient;
    const overlayStyle = {};
    if (overlayColor && !overlayGradient) {
        overlayStyle.backgroundColor = overlayColor;
    }
    if (overlayGradient) {
        overlayStyle.background = overlayGradient;
    }
    overlayStyle.opacity = overlayOpacity / 100;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Column Layout', 'buttercup')}>
                    <p style={{ fontSize: 12, marginBottom: 8, color: '#757575' }}>
                        {__('Columns', 'buttercup')}
                    </p>
                    <ColumnCountPicker
                        columns={columns}
                        onChange={(v) => setAttributes({ columns: v })}
                    />

                    <LayoutPresetPicker
                        columns={columns}
                        columnLayout={columnLayout}
                        columnWidths={columnWidths}
                        onChange={(preset) => setAttributes({ columnLayout: preset, columnWidths: [] })}
                    />

                    {columns > 1 && widths.map((w, i) => (
                        <RangeControl
                            key={i}
                            label={`${__('Column', 'buttercup')} ${i + 1} (%)`}
                            value={Math.round(w * 100) / 100}
                            onChange={(v) => {
                                const newWidths = [...widths];
                                newWidths[i] = v;
                                setAttributes({ columnWidths: newWidths, columnLayout: 'custom' });
                            }}
                            min={10}
                            max={90}
                            step={0.5}
                            __nextHasNoMarginBottom
                        />
                    ))}

                    <RangeControl
                        label={__('Column Gap (px)', 'buttercup')}
                        value={columnGap}
                        onChange={(v) => setAttributes({ columnGap: v })}
                        min={0}
                        max={80}
                        step={2}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>

                <PanelBody title={__('Responsive', 'buttercup')} initialOpen={false}>
                    <SelectControl
                        label={__('Tablet Layout', 'buttercup')}
                        value={tabletLayout}
                        options={[
                            { label: __('Inherit Desktop', 'buttercup'), value: 'inherit' },
                            { label: __('Collapse to Rows', 'buttercup'), value: 'collapse' },
                        ]}
                        onChange={(v) => setAttributes({ tabletLayout: v })}
                        __nextHasNoMarginBottom
                    />
                    {tabletLayout !== 'collapse' && (
                        <ToggleControl
                            label={__('Reverse Column Order on Tablet', 'buttercup')}
                            checked={reverseOnTablet}
                            onChange={(v) => setAttributes({ reverseOnTablet: v })}
                            __nextHasNoMarginBottom
                        />
                    )}

                    <SelectControl
                        label={__('Mobile Layout', 'buttercup')}
                        value={mobileLayout}
                        options={[
                            { label: __('Inherit', 'buttercup'), value: 'inherit' },
                            { label: __('Collapse to Rows', 'buttercup'), value: 'collapse' },
                        ]}
                        onChange={(v) => setAttributes({ mobileLayout: v })}
                        __nextHasNoMarginBottom
                    />
                    {mobileLayout === 'collapse' && (
                        <ToggleControl
                            label={__('Reverse Column Order on Mobile', 'buttercup')}
                            checked={reverseOnMobile}
                            onChange={(v) => setAttributes({ reverseOnMobile: v })}
                            __nextHasNoMarginBottom
                        />
                    )}
                </PanelBody>

                <PanelBody title={__('Column Order', 'buttercup')} initialOpen={false}>
                    <p style={{ fontSize: 12, color: '#757575', margin: '0 0 12px' }}>
                        {__('Set per-column display order at each breakpoint. Edit each column to set its order values.', 'buttercup')}
                    </p>
                    {innerBlocks.map((block, i) => (
                        <div key={block.clientId} className="buttercup-column-order-group">
                            <div className="buttercup-column-order-group__title">
                                {`${__('Column', 'buttercup')} ${i + 1}`}
                            </div>
                            <div className="buttercup-column-order-group__inputs">
                                <div className="buttercup-column-order-group__input-wrap">
                                    <span style={{ fontSize: 11, color: '#757575' }}>{__('Desktop', 'buttercup')}</span>
                                    <span style={{ fontSize: 13, textAlign: 'center' }}>
                                        {block.attributes.desktopOrder || '—'}
                                    </span>
                                </div>
                                <div className="buttercup-column-order-group__input-wrap">
                                    <span style={{ fontSize: 11, color: '#757575' }}>{__('Tablet', 'buttercup')}</span>
                                    <span style={{ fontSize: 13, textAlign: 'center' }}>
                                        {block.attributes.tabletOrder || '—'}
                                    </span>
                                </div>
                                <div className="buttercup-column-order-group__input-wrap">
                                    <span style={{ fontSize: 11, color: '#757575' }}>{__('Mobile', 'buttercup')}</span>
                                    <span style={{ fontSize: 13, textAlign: 'center' }}>
                                        {block.attributes.mobileOrder || '—'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                    <p style={{ fontSize: 11, color: '#999', marginTop: 8 }}>
                        {__('Click a column to edit its order values directly.', 'buttercup')}
                    </p>
                </PanelBody>

                <PanelBody title={__('Background', 'buttercup')} initialOpen={false}>
                    <BackgroundTypeSelector
                        value={activeBgType}
                        onChange={handleBgTypeChange}
                    />

                    {activeBgType === 'color' && (
                        <ColorPalette
                            value={backgroundColor}
                            onChange={(v) => setAttributes({ backgroundColor: v || '' })}
                        />
                    )}

                    {activeBgType === 'gradient' && (
                        <GradientPicker
                            value={backgroundGradient}
                            onChange={(v) => setAttributes({ backgroundGradient: v || '' })}
                        />
                    )}

                    {activeBgType === 'image' && (
                        <>
                            <MediaUploadCheck>
                                <MediaUpload
                                    onSelect={(media) => setAttributes({
                                        backgroundImageUrl: media.url,
                                        backgroundImageId: media.id,
                                    })}
                                    allowedTypes={['image']}
                                    value={backgroundImageId}
                                    render={({ open }) => (
                                        <div style={{ marginBottom: 12 }}>
                                            {backgroundImageUrl ? (
                                                <>
                                                    <img
                                                        src={backgroundImageUrl}
                                                        alt=""
                                                        style={{ width: '100%', height: 120, objectFit: 'cover', borderRadius: 4, marginBottom: 8 }}
                                                    />
                                                    <div style={{ display: 'flex', gap: 8 }}>
                                                        <Button variant="secondary" onClick={open}>
                                                            {__('Replace', 'buttercup')}
                                                        </Button>
                                                        <Button
                                                            variant="tertiary"
                                                            isDestructive
                                                            onClick={() => setAttributes({ backgroundImageUrl: '', backgroundImageId: 0 })}
                                                        >
                                                            {__('Remove', 'buttercup')}
                                                        </Button>
                                                    </div>
                                                </>
                                            ) : (
                                                <Button variant="secondary" onClick={open}>
                                                    {__('Select Image', 'buttercup')}
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                />
                            </MediaUploadCheck>
                            {backgroundImageUrl && (
                                <>
                                    <SelectControl
                                        label={__('Size', 'buttercup')}
                                        value={backgroundImageSize}
                                        options={[
                                            { label: __('Cover', 'buttercup'), value: 'cover' },
                                            { label: __('Contain', 'buttercup'), value: 'contain' },
                                            { label: __('Auto', 'buttercup'), value: 'auto' },
                                        ]}
                                        onChange={(v) => setAttributes({ backgroundImageSize: v })}
                                        __nextHasNoMarginBottom
                                    />
                                    <SelectControl
                                        label={__('Position', 'buttercup')}
                                        value={backgroundImagePosition}
                                        options={[
                                            { label: __('Center Center', 'buttercup'), value: 'center center' },
                                            { label: __('Top Center', 'buttercup'), value: 'top center' },
                                            { label: __('Bottom Center', 'buttercup'), value: 'bottom center' },
                                            { label: __('Center Left', 'buttercup'), value: 'center left' },
                                            { label: __('Center Right', 'buttercup'), value: 'center right' },
                                        ]}
                                        onChange={(v) => setAttributes({ backgroundImagePosition: v })}
                                        __nextHasNoMarginBottom
                                    />
                                    <SelectControl
                                        label={__('Repeat', 'buttercup')}
                                        value={backgroundImageRepeat}
                                        options={[
                                            { label: __('No Repeat', 'buttercup'), value: 'no-repeat' },
                                            { label: __('Repeat', 'buttercup'), value: 'repeat' },
                                            { label: __('Repeat X', 'buttercup'), value: 'repeat-x' },
                                            { label: __('Repeat Y', 'buttercup'), value: 'repeat-y' },
                                        ]}
                                        onChange={(v) => setAttributes({ backgroundImageRepeat: v })}
                                        __nextHasNoMarginBottom
                                    />
                                    <ToggleControl
                                        label={__('Fixed Background (Parallax)', 'buttercup')}
                                        checked={hasParallax}
                                        onChange={(v) => setAttributes({ hasParallax: v })}
                                        __nextHasNoMarginBottom
                                    />
                                </>
                            )}
                        </>
                    )}

                    {activeBgType === 'video' && (
                        <MediaUploadCheck>
                            <MediaUpload
                                onSelect={(media) => setAttributes({
                                    backgroundVideoUrl: media.url,
                                    backgroundVideoId: media.id,
                                })}
                                allowedTypes={['video']}
                                value={backgroundVideoId}
                                render={({ open }) => (
                                    <div style={{ marginBottom: 12 }}>
                                        {backgroundVideoUrl ? (
                                            <div style={{ display: 'flex', gap: 8 }}>
                                                <Button variant="secondary" onClick={open}>
                                                    {__('Replace Video', 'buttercup')}
                                                </Button>
                                                <Button
                                                    variant="tertiary"
                                                    isDestructive
                                                    onClick={() => setAttributes({ backgroundVideoUrl: '', backgroundVideoId: 0 })}
                                                >
                                                    {__('Remove', 'buttercup')}
                                                </Button>
                                            </div>
                                        ) : (
                                            <Button variant="secondary" onClick={open}>
                                                {__('Select Video', 'buttercup')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            />
                        </MediaUploadCheck>
                    )}

                    {(activeBgType === 'image' || activeBgType === 'video' || activeBgType === 'color' || activeBgType === 'gradient') && (
                        <>
                            <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                                <p style={{ fontSize: 12, fontWeight: 600, marginBottom: 8 }}>
                                    {__('Overlay', 'buttercup')}
                                </p>
                                <div style={{ marginBottom: 8 }}>
                                    <div style={{ fontSize: 11, marginBottom: 4 }}>
                                        {__('Color', 'buttercup')}
                                    </div>
                                    <ColorPalette
                                        value={overlayColor}
                                        onChange={(v) => setAttributes({ overlayColor: v || '' })}
                                    />
                                </div>
                                <GradientPicker
                                    value={overlayGradient}
                                    onChange={(v) => setAttributes({ overlayGradient: v || '' })}
                                />
                                <RangeControl
                                    label={__('Overlay Opacity (%)', 'buttercup')}
                                    value={overlayOpacity}
                                    onChange={(v) => setAttributes({ overlayOpacity: v })}
                                    min={0}
                                    max={100}
                                    step={5}
                                    __nextHasNoMarginBottom
                                />
                            </div>
                        </>
                    )}
                </PanelBody>

                <PanelBody title={__('Spacing', 'buttercup')} initialOpen={false}>
                    <RangeControl
                        label={__('Padding Top (px)', 'buttercup')}
                        value={paddingTop}
                        onChange={(v) => setAttributes({ paddingTop: v })}
                        min={0}
                        max={200}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Right (px)', 'buttercup')}
                        value={paddingRight}
                        onChange={(v) => setAttributes({ paddingRight: v })}
                        min={0}
                        max={200}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Bottom (px)', 'buttercup')}
                        value={paddingBottom}
                        onChange={(v) => setAttributes({ paddingBottom: v })}
                        min={0}
                        max={200}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Left (px)', 'buttercup')}
                        value={paddingLeft}
                        onChange={(v) => setAttributes({ paddingLeft: v })}
                        min={0}
                        max={200}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                        <p style={{ fontSize: 12, fontWeight: 600, marginBottom: 8 }}>
                            {__('Tablet Padding', 'buttercup')}
                        </p>
                        <p style={{ fontSize: 11, color: '#757575', marginBottom: 8 }}>
                            {__('Leave all at 0 to inherit desktop padding.', 'buttercup')}
                        </p>
                        {['Top', 'Right', 'Bottom', 'Left'].map((side, i) => (
                            <RangeControl
                                key={side}
                                label={`${side} (px)`}
                                value={(tabletPadding && tabletPadding[i]) || 0}
                                onChange={(v) => {
                                    const next = tabletPadding && tabletPadding.length === 4
                                        ? [...tabletPadding]
                                        : [0, 0, 0, 0];
                                    next[i] = v;
                                    setAttributes({ tabletPadding: next });
                                }}
                                min={0}
                                max={200}
                                step={1}
                                __nextHasNoMarginBottom
                            />
                        ))}
                    </div>
                    <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                        <p style={{ fontSize: 12, fontWeight: 600, marginBottom: 8 }}>
                            {__('Mobile Padding', 'buttercup')}
                        </p>
                        <p style={{ fontSize: 11, color: '#757575', marginBottom: 8 }}>
                            {__('Leave all at 0 to inherit tablet/desktop padding.', 'buttercup')}
                        </p>
                        {['Top', 'Right', 'Bottom', 'Left'].map((side, i) => (
                            <RangeControl
                                key={side}
                                label={`${side} (px)`}
                                value={(mobilePadding && mobilePadding[i]) || 0}
                                onChange={(v) => {
                                    const next = mobilePadding && mobilePadding.length === 4
                                        ? [...mobilePadding]
                                        : [0, 0, 0, 0];
                                    next[i] = v;
                                    setAttributes({ mobilePadding: next });
                                }}
                                min={0}
                                max={200}
                                step={1}
                                __nextHasNoMarginBottom
                            />
                        ))}
                    </div>
                    <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                        <RangeControl
                            label={__('Margin Top (px)', 'buttercup')}
                            value={marginTop}
                            onChange={(v) => setAttributes({ marginTop: v })}
                            min={0}
                            max={200}
                            step={1}
                            __nextHasNoMarginBottom
                        />
                        <RangeControl
                            label={__('Margin Bottom (px)', 'buttercup')}
                            value={marginBottom}
                            onChange={(v) => setAttributes({ marginBottom: v })}
                            min={0}
                            max={200}
                            step={1}
                            __nextHasNoMarginBottom
                        />
                    </div>
                    <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                        <RangeControl
                            label={__('Min Height', 'buttercup')}
                            value={minHeight}
                            onChange={(v) => setAttributes({ minHeight: v })}
                            min={0}
                            max={1000}
                            step={10}
                            __nextHasNoMarginBottom
                        />
                        {minHeight > 0 && (
                            <SelectControl
                                label={__('Min Height Unit', 'buttercup')}
                                value={minHeightUnit}
                                options={[
                                    { label: 'px', value: 'px' },
                                    { label: 'vh', value: 'vh' },
                                ]}
                                onChange={(v) => setAttributes({ minHeightUnit: v })}
                                __nextHasNoMarginBottom
                            />
                        )}
                    </div>
                </PanelBody>

                <PanelBody title={__('Max Width', 'buttercup')} initialOpen={false}>
                    <ToggleControl
                        label={__('Inherit from Theme', 'buttercup')}
                        checked={inheritMaxWidth}
                        onChange={(v) => setAttributes({ inheritMaxWidth: v })}
                        help={
                            inheritMaxWidth && themeContentWidth
                                ? `${__('Theme content width:', 'buttercup')} ${themeContentWidth}px`
                                : inheritMaxWidth
                                    ? __('No theme content width detected. Falling back to 1200px.', 'buttercup')
                                    : undefined
                        }
                        __nextHasNoMarginBottom
                    />
                    {!inheritMaxWidth && (
                        <>
                            <NumberControl
                                label={__('Custom Max Width', 'buttercup')}
                                value={maxWidth}
                                onChange={(v) => setAttributes({ maxWidth: parseInt(v, 10) || 0 })}
                                min={0}
                                __nextHasNoMarginBottom
                            />
                            <div style={{ height: 8 }} />
                            <SelectControl
                                label={__('Unit', 'buttercup')}
                                value={maxWidthUnit}
                                options={[
                                    { label: 'px', value: 'px' },
                                    { label: '%', value: '%' },
                                    { label: 'vw', value: 'vw' },
                                ]}
                                onChange={(v) => setAttributes({ maxWidthUnit: v })}
                                __nextHasNoMarginBottom
                            />
                        </>
                    )}
                </PanelBody>

                <PanelBody title={__('Vertical Alignment', 'buttercup')} initialOpen={false}>
                    <SelectControl
                        label={__('Align Columns', 'buttercup')}
                        value={verticalAlignment}
                        options={[
                            { label: __('Top', 'buttercup'), value: 'top' },
                            { label: __('Center', 'buttercup'), value: 'center' },
                            { label: __('Bottom', 'buttercup'), value: 'bottom' },
                            { label: __('Stretch', 'buttercup'), value: 'stretch' },
                        ]}
                        onChange={(v) => setAttributes({ verticalAlignment: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>

                <PanelBody title={__('Visibility', 'buttercup')} initialOpen={false}>
                    <ToggleControl
                        label={__('Hide on Desktop', 'buttercup')}
                        checked={hideOnDesktop}
                        onChange={(v) => setAttributes({ hideOnDesktop: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Hide on Tablet', 'buttercup')}
                        checked={hideOnTablet}
                        onChange={(v) => setAttributes({ hideOnTablet: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Hide on Mobile', 'buttercup')}
                        checked={hideOnMobile}
                        onChange={(v) => setAttributes({ hideOnMobile: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            {editorColumnStyles && (
                <style dangerouslySetInnerHTML={{ __html: editorColumnStyles }} />
            )}
            <div {...blockProps}>
                {backgroundVideoUrl && (
                    <video
                        className="buttercup-row-layout__video"
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={backgroundVideoUrl}
                    />
                )}
                {hasOverlay && (
                    <span
                        className="buttercup-row-layout__overlay"
                        style={overlayStyle}
                        aria-hidden="true"
                    />
                )}
                <div
                    className={`buttercup-row-layout__inner is-aligned-${verticalAlignment}`}
                    style={innerStyle}
                    ref={innerRef}
                >
                    <InnerBlocks
                        allowedBlocks={ALLOWED_BLOCKS}
                        orientation="horizontal"
                        renderAppender={false}
                    />
                    <ColumnResizers
                        widths={widths}
                        columns={columns}
                        columnGap={columnGap}
                        containerRef={innerRef}
                        onResize={handleColumnResize}
                    />
                </div>
            </div>
        </>
    );
}
