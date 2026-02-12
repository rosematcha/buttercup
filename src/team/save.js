import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const {
        imageShape,
        imageSize,
        minCardWidth,
        columnGap,
        rowGap,
        textAlign,
        showBio,
        showSocial,
    } = attributes;

    const blockProps = useBlockProps.save({
        className: [
            'buttercup-team',
            `buttercup-team--${imageShape}`,
            `buttercup-team--img-${imageSize}`,
            `buttercup-team--align-${textAlign}`,
            !showBio && 'buttercup-team--hide-bio',
            !showSocial && 'buttercup-team--hide-social',
        ].filter(Boolean).join(' '),
        style: {
            '--buttercup-min-card': `${minCardWidth}px`,
            '--buttercup-col-gap': `${columnGap}px`,
            '--buttercup-row-gap': `${rowGap}px`,
        },
    });

    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}
