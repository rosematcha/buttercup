import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const {
        imageShape,
        imageSize,
        squircleRadius,
        cardBackground,
        cardBorderRadius,
        cardPadding,
        cardShadow,
        cardHoverEffect,
        minCardWidth,
        columnGap,
        rowGap,
        bioLines,
        readMoreLabel,
        readLessLabel,
        textAlign,
        showPronouns,
        showBio,
        showSocial,
        showSocialGrid,
        showSocialMemberPage,
        enableMemberPages,
        socialIconSize,
        socialStyle,
        socialLabelStyle,
    } = attributes;

    const resolvedShowSocialGrid = typeof showSocialGrid === 'boolean'
        ? showSocialGrid
        : (typeof showSocial === 'boolean' ? showSocial : true);

    const blockProps = useBlockProps.save({
        className: [
            'buttercup-team',
            `buttercup-team--${imageShape}`,
            `buttercup-team--align-${textAlign}`,
            `buttercup-team--social-${socialStyle}`,
            `buttercup-team--social-label-${socialLabelStyle}`,
            cardShadow !== 'none' && `buttercup-team--shadow-${cardShadow}`,
            cardHoverEffect !== 'none' && `buttercup-team--hover-${cardHoverEffect}`,
            !showPronouns && 'buttercup-team--hide-pronouns',
            !showBio && 'buttercup-team--hide-bio',
            !resolvedShowSocialGrid && 'buttercup-team--hide-social',
        ].filter(Boolean).join(' '),
        style: {
            '--buttercup-min-card': `${minCardWidth}px`,
            '--buttercup-col-gap': `${columnGap}px`,
            '--buttercup-row-gap': `${rowGap}px`,
            '--buttercup-img-size': `${imageSize}px`,
            '--buttercup-squircle-radius': `${squircleRadius}%`,
            '--buttercup-card-bg': cardBackground || 'transparent',
            '--buttercup-card-radius': `${cardBorderRadius}px`,
            '--buttercup-card-padding': `${cardPadding}px`,
            '--buttercup-bio-lines': bioLines,
            '--buttercup-social-size': `${socialIconSize}px`,
        },
        'data-read-more': readMoreLabel || undefined,
        'data-read-less': readLessLabel || undefined,
        'data-member-pages': enableMemberPages ? '1' : undefined,
    });

    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}
