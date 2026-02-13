import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save({ attributes }) {
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
        style.order = desktopOrder;
    }
    if (tabletOrder) {
        style['--rc-tablet-order'] = tabletOrder;
    }
    if (mobileOrder) {
        style['--rc-mobile-order'] = mobileOrder;
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

    const blockProps = useBlockProps.save({
        className: classes,
        style,
    });

    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}
