import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

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

function buildColumnStyles(uniqueId, columns, columnLayout, columnWidths, columnGap) {
    const widths = getColumnWidths(columns, columnLayout, columnWidths);
    const gapOffset = columns > 1 ? ((columns - 1) * columnGap) / columns : 0;
    const selector = `#buttercup-rl-${uniqueId}`;

    let css = '';

    widths.forEach((w, i) => {
        const basis = `calc(${w}% - ${gapOffset}px)`;
        css += `${selector} .buttercup-row-column:nth-child(${i + 1}){flex:0 0 ${basis};max-width:${basis}}`;
    });

    return css;
}

function buildResponsiveStyles(uniqueId, attributes) {
    const { tabletPadding, mobilePadding } = attributes;

    const selector = `#buttercup-rl-${uniqueId}`;
    let tabletCss = '';
    let mobileCss = '';

    if (tabletPadding && tabletPadding.length === 4) {
        tabletCss += `${selector}{padding:${tabletPadding[0]}px ${tabletPadding[1]}px ${tabletPadding[2]}px ${tabletPadding[3]}px}`;
    }

    if (mobilePadding && mobilePadding.length === 4) {
        mobileCss += `${selector}{padding:${mobilePadding[0]}px ${mobilePadding[1]}px ${mobilePadding[2]}px ${mobilePadding[3]}px}`;
    }

    let css = '';
    if (tabletCss) {
        css += `@media(max-width:1024px){${tabletCss}}`;
    }
    if (mobileCss) {
        css += `@media(max-width:767px){${mobileCss}}`;
    }
    return css;
}

export default function save({ attributes }) {
    const {
        uniqueId,
        columns,
        columnLayout,
        columnWidths,
        columnGap,
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
        marginTop,
        marginBottom,
        backgroundColor,
        backgroundGradient,
        backgroundImageUrl,
        backgroundImageSize,
        backgroundImagePosition,
        backgroundImageRepeat,
        hasParallax,
        backgroundVideoUrl,
        overlayColor,
        overlayOpacity,
        overlayGradient,
        hideOnDesktop,
        hideOnTablet,
        hideOnMobile,
        reverseOnMobile,
        reverseOnTablet,
        tabletLayout,
        mobileLayout,
    } = attributes;

    const wrapperClasses = [
        'buttercup-row-layout',
        hasParallax && backgroundImageUrl && 'has-parallax',
        hideOnDesktop && 'buttercup-hide-desktop',
        hideOnTablet && 'buttercup-hide-tablet',
        hideOnMobile && 'buttercup-hide-mobile',
        (tabletLayout === 'collapse' || tabletLayout === 'inherit' && mobileLayout === 'collapse') && 'is-tablet-collapse',
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
    if (backgroundColor && !backgroundGradient) {
        wrapperStyle.backgroundColor = backgroundColor;
    }
    if (backgroundGradient) {
        wrapperStyle.background = backgroundGradient;
    }
    if (backgroundImageUrl) {
        wrapperStyle.backgroundImage = `url(${backgroundImageUrl})`;
        wrapperStyle['--rl-bg-size'] = backgroundImageSize;
        wrapperStyle['--rl-bg-position'] = backgroundImagePosition;
        wrapperStyle['--rl-bg-repeat'] = backgroundImageRepeat;
    }

    const innerStyle = {
        display: 'flex',
        flexWrap: 'wrap',
        gap: `${columnGap}px`,
    };

    const alignMap = {
        top: 'flex-start',
        center: 'center',
        bottom: 'flex-end',
        stretch: 'stretch',
    };
    if (alignMap[verticalAlignment]) {
        innerStyle.alignItems = alignMap[verticalAlignment];
    }

    if (inheritMaxWidth) {
        innerStyle.maxWidth = 'var(--wp--style--global--content-size, 1200px)';
        innerStyle.marginLeft = 'auto';
        innerStyle.marginRight = 'auto';
    } else if (maxWidth) {
        innerStyle.maxWidth = `${maxWidth}${maxWidthUnit}`;
        innerStyle.marginLeft = 'auto';
        innerStyle.marginRight = 'auto';
    }

    const innerClasses = [
        'buttercup-row-layout__inner',
        `is-aligned-${verticalAlignment}`,
    ].filter(Boolean).join(' ');

    let inlineCss = buildColumnStyles(uniqueId, columns, columnLayout, columnWidths, columnGap);
    inlineCss += buildResponsiveStyles(uniqueId, attributes);

    const hasOverlay = overlayColor || overlayGradient;
    const overlayStyle = {};
    if (overlayColor && !overlayGradient) {
        overlayStyle.backgroundColor = overlayColor;
    }
    if (overlayGradient) {
        overlayStyle.background = overlayGradient;
    }
    overlayStyle.opacity = overlayOpacity / 100;

    const blockProps = useBlockProps.save({
        id: `buttercup-rl-${uniqueId}`,
        className: wrapperClasses,
        style: wrapperStyle,
    });

    return (
        <div {...blockProps}>
            {inlineCss && (
                <style dangerouslySetInnerHTML={{ __html: inlineCss }} />
            )}
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
            <div className={innerClasses} style={innerStyle}>
                <InnerBlocks.Content />
            </div>
        </div>
    );
}
