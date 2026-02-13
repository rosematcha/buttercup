import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InnerBlocks,
    InspectorControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
    ColorPalette,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';

const VALIGN_OPTIONS = [
    { label: __('Inherit', 'buttercup'), value: 'inherit' },
    { label: __('Top', 'buttercup'), value: 'top' },
    { label: __('Center', 'buttercup'), value: 'center' },
    { label: __('Bottom', 'buttercup'), value: 'bottom' },
    { label: __('Stretch', 'buttercup'), value: 'stretch' },
];

export default function Edit({ attributes, setAttributes, context }) {
    const {
        desktopOrder,
        tabletOrder,
        mobileOrder,
        verticalAlignment,
        paddingTop,
        paddingRight,
        paddingBottom,
        paddingLeft,
        backgroundColor,
        borderRadius,
        hideOnDesktop,
        hideOnTablet,
        hideOnMobile,
    } = attributes;

    const rowColumns = context['buttercup/rowColumns'] || 2;

    const classes = [
        'buttercup-row-column',
        verticalAlignment !== 'inherit' && `is-vertically-aligned-${verticalAlignment}`,
        hideOnDesktop && 'buttercup-hide-desktop',
        hideOnTablet && 'buttercup-hide-tablet',
        hideOnMobile && 'buttercup-hide-mobile',
    ].filter(Boolean).join(' ');

    const style = {};

    if (desktopOrder) {
        style['--rc-desktop-order'] = desktopOrder;
    }
    if (tabletOrder) {
        style['--rc-tablet-order'] = tabletOrder;
    }
    if (mobileOrder) {
        style['--rc-mobile-order'] = mobileOrder;
    }
    if (desktopOrder) {
        style.order = desktopOrder;
    }
    if (paddingTop || paddingRight || paddingBottom || paddingLeft) {
        style.padding = `${paddingTop}px ${paddingRight}px ${paddingBottom}px ${paddingLeft}px`;
    }
    if (backgroundColor) {
        style.backgroundColor = backgroundColor;
    }
    if (borderRadius) {
        style.borderRadius = `${borderRadius}px`;
    }

    const blockProps = useBlockProps({
        className: classes,
        style,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Column Order', 'buttercup')} initialOpen={false}>
                    <p style={{ fontSize: 12, color: '#757575', margin: '0 0 12px' }}>
                        {__('Set the display order for this column at each breakpoint. Lower numbers appear first. 0 = default position.', 'buttercup')}
                    </p>
                    <NumberControl
                        label={__('Desktop Order', 'buttercup')}
                        value={desktopOrder}
                        onChange={(v) => setAttributes({ desktopOrder: parseInt(v, 10) || 0 })}
                        min={0}
                        max={10}
                        __nextHasNoMarginBottom
                    />
                    <div style={{ height: 8 }} />
                    <NumberControl
                        label={__('Tablet Order', 'buttercup')}
                        value={tabletOrder}
                        onChange={(v) => setAttributes({ tabletOrder: parseInt(v, 10) || 0 })}
                        min={0}
                        max={10}
                        __nextHasNoMarginBottom
                    />
                    <div style={{ height: 8 }} />
                    <NumberControl
                        label={__('Mobile Order', 'buttercup')}
                        value={mobileOrder}
                        onChange={(v) => setAttributes({ mobileOrder: parseInt(v, 10) || 0 })}
                        min={0}
                        max={10}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Column Style', 'buttercup')} initialOpen={false}>
                    <SelectControl
                        label={__('Vertical Alignment', 'buttercup')}
                        value={verticalAlignment}
                        options={VALIGN_OPTIONS}
                        onChange={(v) => setAttributes({ verticalAlignment: v })}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Top (px)', 'buttercup')}
                        value={paddingTop}
                        onChange={(v) => setAttributes({ paddingTop: v })}
                        min={0}
                        max={100}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Right (px)', 'buttercup')}
                        value={paddingRight}
                        onChange={(v) => setAttributes({ paddingRight: v })}
                        min={0}
                        max={100}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Bottom (px)', 'buttercup')}
                        value={paddingBottom}
                        onChange={(v) => setAttributes({ paddingBottom: v })}
                        min={0}
                        max={100}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Padding Left (px)', 'buttercup')}
                        value={paddingLeft}
                        onChange={(v) => setAttributes({ paddingLeft: v })}
                        min={0}
                        max={100}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <div style={{ marginTop: 12, marginBottom: 12 }}>
                        <div style={{ fontSize: 12, marginBottom: 6 }}>
                            {__('Background Color', 'buttercup')}
                        </div>
                        <ColorPalette
                            value={backgroundColor}
                            onChange={(v) => setAttributes({ backgroundColor: v || '' })}
                        />
                    </div>
                    <RangeControl
                        label={__('Border Radius (px)', 'buttercup')}
                        value={borderRadius}
                        onChange={(v) => setAttributes({ borderRadius: v })}
                        min={0}
                        max={50}
                        step={1}
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
            <div {...blockProps}>
                <InnerBlocks
                    templateLock={false}
                    renderAppender={InnerBlocks.ButtonBlockAppender}
                />
            </div>
        </>
    );
}
