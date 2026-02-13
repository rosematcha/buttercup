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
    TextControl,
    TextareaControl,
    ColorPalette,
    Button,
} from '@wordpress/components';
import { useSelect, useDispatch, resolveSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { useEffect, useState } from '@wordpress/element';
import { cleanForSlug } from '@wordpress/url';

const ALLOWED_BLOCKS = ['buttercup/team-member'];

const TEMPLATE = [
    ['buttercup/team-member'],
    ['buttercup/team-member'],
    ['buttercup/team-member'],
];

function AddMemberButton({ clientId }) {
    const { insertBlock } = useDispatch(blockEditorStore);
    return (
        <div className="buttercup-team__add-member">
            <button
                className="buttercup-team__add-member-btn"
                onClick={() => {
                    const block = createBlock('buttercup/team-member');
                    insertBlock(block, undefined, clientId);
                }}
                aria-label={__('Add team member', 'buttercup')}
            >
                <span className="buttercup-team__add-member-icon">+</span>
                <span className="buttercup-team__add-member-label">
                    {__('Add Member', 'buttercup')}
                </span>
            </button>
        </div>
    );
}

export default function Edit({ attributes, setAttributes, clientId }) {
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
        memberBackLabel,
        memberPageIntro,
        memberPageCardBackground,
        memberPageCardRadius,
        memberPageCardPadding,
        memberPageCardShadow,
        memberPageGap,
        memberPageLeftWidth,
        socialIconSize,
        socialStyle,
        socialLabelStyle,
    } = attributes;

    const innerBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(clientId),
        [clientId]
    );
    const allBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(),
        []
    );

    const { replaceInnerBlocks, updateBlockAttributes } = useDispatch(blockEditorStore);
    const [showAdvancedPages, setShowAdvancedPages] = useState(false);
    const [isRefreshingImages, setIsRefreshingImages] = useState(false);
    const [refreshImagesNote, setRefreshImagesNote] = useState('');

    const memberCount = innerBlocks.length;

    useEffect(() => {
        if (!allBlocks.length) return;

        const teamBlocks = [];
        const collectTeamBlocks = (blocks) => {
            blocks.forEach((block) => {
                if (block.name === 'buttercup/team') {
                    teamBlocks.push(block);
                }
                if (block.innerBlocks?.length) {
                    collectTeamBlocks(block.innerBlocks);
                }
            });
        };
        collectTeamBlocks(allBlocks);

        const enabledMembers = [];
        const disabledMembers = [];
        teamBlocks.forEach((teamBlock) => {
            const teamEnabled = teamBlock.attributes?.enableMemberPages !== false;
            const members = (teamBlock.innerBlocks || []).filter(
                (block) => block.name === 'buttercup/team-member'
            );

            members.forEach((member) => {
                const memberDisabled = member.attributes?.disableMemberPage === true;
                if (teamEnabled && !memberDisabled) {
                    enabledMembers.push(member);
                } else {
                    disabledMembers.push(member);
                }
            });
        });

        const counts = {};
        const parts = enabledMembers.map((block) => {
            const rawName = (block.attributes?.name || '').replace(/<[^>]*>/g, '').trim();
            const tokens = rawName.split(/\s+/).filter(Boolean);
            const first = tokens[0] || '';
            const last = tokens.length > 1 ? tokens[tokens.length - 1] : '';
            const key = first.toLowerCase();
            if (key) {
                counts[key] = (counts[key] || 0) + 1;
            }
            return { block, first, last };
        });

        const used = {};
        enabledMembers.forEach((block) => {
            const existingSlug = (block.attributes?.memberSlug || '').trim();
            if (existingSlug) {
                used[existingSlug] = true;
            }
        });

        parts.forEach(({ block, first, last }) => {
            const existingSlug = (block.attributes?.memberSlug || '').trim();
            const nextAttrs = {};

            if (!existingSlug && first) {
                const needsLast = counts[first.toLowerCase()] > 1;
                const base = needsLast && last ? `${first} ${last}` : first;
                let slug = base ? cleanForSlug(base) : '';
                if (slug) {
                    let unique = slug;
                    let i = 2;
                    while (used[unique]) {
                        unique = `${slug}-${i}`;
                        i += 1;
                    }
                    used[unique] = true;
                    slug = unique;
                }
                nextAttrs.memberSlug = slug;
            }

            if (block.attributes?.memberPagesEnabled !== true) {
                nextAttrs.memberPagesEnabled = true;
            }

            if (Object.keys(nextAttrs).length > 0) {
                updateBlockAttributes(block.clientId, nextAttrs);
            }
        });

        disabledMembers.forEach((block) => {
            const nextAttrs = {};
            if (block.attributes?.memberPagesEnabled !== false) {
                nextAttrs.memberPagesEnabled = false;
            }
            if (Object.keys(nextAttrs).length > 0) {
                updateBlockAttributes(block.clientId, nextAttrs);
            }
        });
    }, [allBlocks, updateBlockAttributes]);

    const sortBlocks = (key) => {
        const sorted = [...innerBlocks].sort((a, b) => {
            const valA = (a.attributes[key] || '').replace(/<[^>]*>/g, '').toLowerCase();
            const valB = (b.attributes[key] || '').replace(/<[^>]*>/g, '').toLowerCase();
            return valA.localeCompare(valB);
        });
        replaceInnerBlocks(clientId, sorted, false);
    };

    const getMediaSizeCandidates = (mediaItem) => {
        if (!mediaItem) return [];
        const sizes = mediaItem.media_details?.sizes || mediaItem.sizes || {};
        const candidates = Object.values(sizes)
            .map((size) => ({
                url: size?.source_url || size?.url,
                width: size?.width,
                height: size?.height,
            }))
            .filter((size) => size.url && size.width);
        if (mediaItem.source_url && mediaItem.media_details?.width) {
            candidates.push({
                url: mediaItem.source_url,
                width: mediaItem.media_details.width,
                height: mediaItem.media_details.height,
            });
        }
        return candidates;
    };

    const filterByAspectRatio = (candidates, mediaItem) => {
        const originalWidth = mediaItem?.media_details?.width || 0;
        const originalHeight = mediaItem?.media_details?.height || 0;
        if (!originalWidth || !originalHeight) return candidates;
        const originalRatio = originalWidth / originalHeight;
        const ratioTolerance = 0.08;

        return candidates.filter((item) => {
            if (!item.width || !item.height) return false;
            const ratio = item.width / item.height;
            if (Math.abs(originalRatio - 1) <= 0.05) {
                return true;
            }
            return Math.abs(ratio - originalRatio) / originalRatio <= ratioTolerance;
        });
    };

    const filterOutCropped = (candidates, mediaItem) => {
        const sizes = mediaItem?.media_details?.sizes || mediaItem?.sizes || {};
        const cropMap = new Map();
        Object.values(sizes).forEach((size) => {
            const url = size?.source_url || size?.url;
            if (!url) return;
            cropMap.set(url, !!size?.crop);
        });
        return candidates.filter((item) => !cropMap.get(item.url));
    };

    const buildSrcSet = (mediaItem) => {
        const candidates = filterOutCropped(
            filterByAspectRatio(getMediaSizeCandidates(mediaItem), mediaItem),
            mediaItem
        );
        const seen = new Set();
        return candidates
            .filter((item) => {
                if (seen.has(item.url)) return false;
                seen.add(item.url);
                return true;
            })
            .sort((a, b) => a.width - b.width)
            .map((item) => `${item.url} ${item.width}w`)
            .join(', ');
    };

    const pickBestUrl = (mediaItem, targetWidth) => {
        const candidates = filterOutCropped(
            filterByAspectRatio(getMediaSizeCandidates(mediaItem), mediaItem),
            mediaItem
        )
            .sort((a, b) => a.width - b.width);
        if (!candidates.length) {
            return { url: mediaItem?.url || '', width: 0, height: 0 };
        }
        const match = candidates.find((item) => item.width >= targetWidth) || candidates[candidates.length - 1];
        return match;
    };

    const refreshMemberImages = async () => {
        if (isRefreshingImages) return;
        setIsRefreshingImages(true);
        setRefreshImagesNote('');

        const members = innerBlocks.filter((block) => block.name === 'buttercup/team-member');
        let updated = 0;
        let missing = 0;
        const target = imageSize || 120;

        for (const member of members) {
            const imageId = member.attributes?.profileImageId;
            if (!imageId) continue;
            const mediaItem = await resolveSelect('core').getMedia(imageId);
            if (!mediaItem) {
                missing += 1;
                continue;
            }
            let square = null;
            try {
                square = await apiFetch({
                    path: '/buttercup/v1/square-image',
                    method: 'POST',
                    data: { id: imageId },
                });
            } catch (e) {
                square = null;
            }

            const alt = mediaItem?.alt_text || mediaItem?.alt || '';
            const sizes = `${target}px`;

            if (square?.url) {
                updateBlockAttributes(member.clientId, {
                    profileImageUrl: square.url,
                    profileImageAlt: alt,
                    profileImageSrcSet: '',
                    profileImageSizes: sizes,
                    profileImageWidth: square.width || 600,
                    profileImageHeight: square.height || 600,
                    profileImageSource: 'square-600',
                });
            } else {
                const best = pickBestUrl(mediaItem, target);
                const srcSet = buildSrcSet(mediaItem);
                updateBlockAttributes(member.clientId, {
                    profileImageUrl: best.url || mediaItem.url || mediaItem.source_url || '',
                    profileImageAlt: alt,
                    profileImageSrcSet: srcSet,
                    profileImageSizes: sizes,
                    profileImageWidth: best.width || mediaItem.media_details?.width || 0,
                    profileImageHeight: best.height || mediaItem.media_details?.height || 0,
                    profileImageSource: 'auto',
                });
            }
            updated += 1;
        }

        if (updated || missing) {
            const note = missing
                ? __('Updated images for this block. Some images are still loading from the Media Library.', 'buttercup')
                : __('Updated images for this block.', 'buttercup');
            setRefreshImagesNote(note);
        } else {
            setRefreshImagesNote(__('No images found to refresh in this block.', 'buttercup'));
        }

        setIsRefreshingImages(false);
    };

    const resolvedShowSocialGrid = typeof showSocialGrid === 'boolean'
        ? showSocialGrid
        : (typeof showSocial === 'boolean' ? showSocial : true);
    const resolvedShowSocialMemberPage = typeof showSocialMemberPage === 'boolean'
        ? showSocialMemberPage
        : (typeof showSocial === 'boolean' ? showSocial : true);

    const blockProps = useBlockProps({
        className: [
            'buttercup-team',
            `buttercup-team--${imageShape}`,
            `buttercup-team--align-${textAlign}`,
            `buttercup-team--social-${socialStyle}`,
            `buttercup-team--social-label-${socialLabelStyle}`,
            cardShadow !== 'none' && `buttercup-team--shadow-${cardShadow}`,
            cardHoverEffect !== 'none' && `buttercup-team--hover-${cardHoverEffect}`,
            !showPronouns && 'buttercup-team--hide-pronouns',
            !resolvedShowSocialGrid && 'buttercup-team--hide-social',
        ].join(' '),
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
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Grid Layout', 'buttercup')}>
                    <p style={{ fontSize: 13, color: '#757575', margin: '0 0 12px' }}>
                        {memberCount} {memberCount === 1 ? __('member', 'buttercup') : __('members', 'buttercup')}
                    </p>
                    <RangeControl
                        label={__('Min card width (px)', 'buttercup')}
                        value={minCardWidth}
                        onChange={(v) => setAttributes({ minCardWidth: v })}
                        min={120}
                        max={400}
                        step={10}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Column gap (px)', 'buttercup')}
                        value={columnGap}
                        onChange={(v) => setAttributes({ columnGap: v })}
                        min={0}
                        max={80}
                        step={4}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Row gap (px)', 'buttercup')}
                        value={rowGap}
                        onChange={(v) => setAttributes({ rowGap: v })}
                        min={0}
                        max={80}
                        step={4}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Text Alignment', 'buttercup')}
                        value={textAlign}
                        options={[
                            { label: __('Center', 'buttercup'), value: 'center' },
                            { label: __('Left', 'buttercup'), value: 'left' },
                        ]}
                        onChange={(v) => setAttributes({ textAlign: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Content', 'buttercup')} initialOpen={false}>
                    <ToggleControl
                        label={__('Show Pronouns', 'buttercup')}
                        checked={showPronouns}
                        onChange={(v) => setAttributes({ showPronouns: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show Short Bio', 'buttercup')}
                        checked={showBio}
                        onChange={(v) => setAttributes({ showBio: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show Social Links on Grid', 'buttercup')}
                        checked={resolvedShowSocialGrid}
                        onChange={(v) => setAttributes({ showSocialGrid: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show Social Links on Member Pages', 'buttercup')}
                        checked={resolvedShowSocialMemberPage}
                        onChange={(v) => setAttributes({ showSocialMemberPage: v })}
                        disabled={!enableMemberPages}
                        help={
                            !enableMemberPages
                                ? __('Enable member pages to show social links on profiles.', 'buttercup')
                                : undefined
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Images', 'buttercup')} initialOpen={false}>
                    <SelectControl
                        label={__('Image Shape', 'buttercup')}
                        value={imageShape}
                        options={[
                            { label: __('Circle', 'buttercup'), value: 'circle' },
                            { label: __('Square', 'buttercup'), value: 'square' },
                            { label: __('Squircle', 'buttercup'), value: 'squircle' },
                        ]}
                        onChange={(v) => setAttributes({ imageShape: v })}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Image Size (px)', 'buttercup')}
                        value={imageSize}
                        onChange={(v) => setAttributes({ imageSize: v })}
                        min={40}
                        max={600}
                        step={8}
                        __nextHasNoMarginBottom
                    />
                    {imageShape === 'squircle' && (
                        <RangeControl
                            label={__('Squircle Radius (%)', 'buttercup')}
                            value={squircleRadius}
                            onChange={(v) => setAttributes({ squircleRadius: v })}
                            min={8}
                            max={45}
                            step={1}
                            __nextHasNoMarginBottom
                        />
                    )}
                    <div style={{ marginTop: 8 }}>
                        <Button
                            variant="secondary"
                            onClick={refreshMemberImages}
                            disabled={isRefreshingImages}
                        >
                            {isRefreshingImages
                                ? __('Refreshing Images…', 'buttercup')
                                : __('Refresh Member Images', 'buttercup')}
                        </Button>
                        <p style={{ marginTop: 6, fontSize: 12, color: '#757575' }}>
                            {refreshImagesNote || __('Rebuilds image sizes for all members in this block.', 'buttercup')}
                        </p>
                    </div>
                </PanelBody>
                <PanelBody title={__('Card Style', 'buttercup')} initialOpen={false}>
                    <p style={{ fontSize: 13, color: '#757575', marginTop: 0 }}>
                        {__('Style each member card without editing individual blocks.', 'buttercup')}
                    </p>
                    <div style={{ marginBottom: 12 }}>
                        <div style={{ fontSize: 12, marginBottom: 6 }}>
                            {__('Background', 'buttercup')}
                        </div>
                        <ColorPalette
                            value={cardBackground}
                            onChange={(v) => setAttributes({ cardBackground: v || '' })}
                        />
                    </div>
                    <RangeControl
                        label={__('Border Radius (px)', 'buttercup')}
                        value={cardBorderRadius}
                        onChange={(v) => setAttributes({ cardBorderRadius: v })}
                        min={0}
                        max={40}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Card Padding (px)', 'buttercup')}
                        value={cardPadding}
                        onChange={(v) => setAttributes({ cardPadding: v })}
                        min={0}
                        max={48}
                        step={2}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Shadow', 'buttercup')}
                        value={cardShadow}
                        options={[
                            { label: __('None', 'buttercup'), value: 'none' },
                            { label: __('Soft', 'buttercup'), value: 'soft' },
                            { label: __('Medium', 'buttercup'), value: 'medium' },
                            { label: __('Strong', 'buttercup'), value: 'strong' },
                        ]}
                        onChange={(v) => setAttributes({ cardShadow: v })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Hover Animation', 'buttercup')}
                        value={cardHoverEffect}
                        options={[
                            { label: __('None', 'buttercup'), value: 'none' },
                            { label: __('Lift', 'buttercup'), value: 'lift' },
                            { label: __('Glow', 'buttercup'), value: 'glow' },
                            { label: __('Lift + Glow', 'buttercup'), value: 'lift-glow' },
                            { label: __('Scale', 'buttercup'), value: 'scale' },
                            { label: __('Tilt', 'buttercup'), value: 'tilt' },
                            { label: __('Outline', 'buttercup'), value: 'outline' },
                        ]}
                        onChange={(v) => setAttributes({ cardHoverEffect: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Short Bio Preview', 'buttercup')} initialOpen={false}>
                    <RangeControl
                        label={__('Short Bio Lines', 'buttercup')}
                        value={bioLines}
                        onChange={(v) => setAttributes({ bioLines: v })}
                        min={1}
                        max={8}
                        step={1}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Short Bio Read More Label', 'buttercup')}
                        value={readMoreLabel}
                        onChange={(v) => setAttributes({ readMoreLabel: v })}
                        placeholder={__('Read more', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Short Bio Read Less Label', 'buttercup')}
                        value={readLessLabel}
                        onChange={(v) => setAttributes({ readLessLabel: v })}
                        placeholder={__('Read less', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Social Links', 'buttercup')} initialOpen={false}>
                    <RangeControl
                        label={__('Icon Size (px)', 'buttercup')}
                        value={socialIconSize}
                        onChange={(v) => setAttributes({ socialIconSize: v })}
                        min={16}
                        max={48}
                        step={2}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Icon Shape', 'buttercup')}
                        value={socialStyle}
                        options={[
                            { label: __('Circle', 'buttercup'), value: 'circle' },
                            { label: __('Rounded', 'buttercup'), value: 'rounded' },
                            { label: __('Square', 'buttercup'), value: 'square' },
                        ]}
                        onChange={(v) => setAttributes({ socialStyle: v })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Label Style', 'buttercup')}
                        value={socialLabelStyle}
                        options={[
                            { label: __('Icon Only', 'buttercup'), value: 'icon-only' },
                            { label: __('Icon + Text', 'buttercup'), value: 'icon-text' },
                        ]}
                        onChange={(v) => setAttributes({ socialLabelStyle: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Member Pages', 'buttercup')} initialOpen={false}>
                    <ToggleControl
                        label={__('Enable Individual Pages', 'buttercup')}
                        checked={enableMemberPages}
                        onChange={(v) => setAttributes({ enableMemberPages: v })}
                        help={__('Creates a dedicated page for each team member.', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                    {enableMemberPages && (
                        <p style={{ marginTop: 8, fontSize: 12, color: '#757575' }}>
                            {__('Save the page to refresh member URLs.', 'buttercup')}
                        </p>
                    )}
                    {enableMemberPages && (
                        <>
                            <TextControl
                                label={__('Back Link Label', 'buttercup')}
                                value={memberBackLabel}
                                onChange={(v) => setAttributes({ memberBackLabel: v })}
                                placeholder={__('Back to People', 'buttercup')}
                                __nextHasNoMarginBottom
                            />
                            <TextareaControl
                                label={__('Member Page Intro', 'buttercup')}
                                value={memberPageIntro}
                                onChange={(v) => setAttributes({ memberPageIntro: v })}
                                help={__('Shown above the long bio on every member page.', 'buttercup')}
                            />
                            <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid #eee' }}>
                                <p style={{ fontSize: 13, fontWeight: 600, margin: '0 0 8px' }}>
                                    {__('Member Page Style', 'buttercup')}
                                </p>
                                <div style={{ marginBottom: 12 }}>
                                    <div style={{ fontSize: 12, marginBottom: 6 }}>
                                        {__('Card Background', 'buttercup')}
                                    </div>
                                    <ColorPalette
                                        value={memberPageCardBackground}
                                        onChange={(v) => setAttributes({ memberPageCardBackground: v || '' })}
                                    />
                                </div>
                                <RangeControl
                                    label={__('Card Radius (px)', 'buttercup')}
                                    value={memberPageCardRadius}
                                    onChange={(v) => setAttributes({ memberPageCardRadius: v })}
                                    min={0}
                                    max={32}
                                    step={1}
                                    __nextHasNoMarginBottom
                                />
                                <RangeControl
                                    label={__('Card Padding (px)', 'buttercup')}
                                    value={memberPageCardPadding}
                                    onChange={(v) => setAttributes({ memberPageCardPadding: v })}
                                    min={12}
                                    max={40}
                                    step={2}
                                    __nextHasNoMarginBottom
                                />
                                <SelectControl
                                    label={__('Card Shadow', 'buttercup')}
                                    value={memberPageCardShadow}
                                    options={[
                                        { label: __('None', 'buttercup'), value: 'none' },
                                        { label: __('Soft', 'buttercup'), value: 'soft' },
                                        { label: __('Medium', 'buttercup'), value: 'medium' },
                                        { label: __('Strong', 'buttercup'), value: 'strong' },
                                    ]}
                                    onChange={(v) => setAttributes({ memberPageCardShadow: v })}
                                    __nextHasNoMarginBottom
                                />
                                <RangeControl
                                    label={__('Left Column Width (px)', 'buttercup')}
                                    value={memberPageLeftWidth}
                                    onChange={(v) => setAttributes({ memberPageLeftWidth: v })}
                                    min={220}
                                    max={360}
                                    step={4}
                                    __nextHasNoMarginBottom
                                />
                                <RangeControl
                                    label={__('Column Gap (px)', 'buttercup')}
                                    value={memberPageGap}
                                    onChange={(v) => setAttributes({ memberPageGap: v })}
                                    min={16}
                                    max={64}
                                    step={2}
                                    __nextHasNoMarginBottom
                                />
                            </div>
                            <ToggleControl
                                label={__('Advanced Member Page Tools', 'buttercup')}
                                checked={showAdvancedPages}
                                onChange={(v) => setShowAdvancedPages(v)}
                                __nextHasNoMarginBottom
                            />
                            {showAdvancedPages && (
                                <>
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            const members = [];
                                            const collect = (blocks) => {
                                                blocks.forEach((block) => {
                                                    if (block.name === 'buttercup/team') {
                                                        (block.innerBlocks || []).forEach((inner) => {
                                                            if (inner.name !== 'buttercup/team-member') return;
                                                            members.push(inner);
                                                        });
                                                    }
                                                    if (block.innerBlocks?.length) {
                                                        collect(block.innerBlocks);
                                                    }
                                                });
                                            };
                                            collect(allBlocks);
                                            members.forEach((member) => {
                                                updateBlockAttributes(member.clientId, { memberSlug: '' });
                                            });
                                        }}
                                    >
                                        {__('Reset All Member Slugs', 'buttercup')}
                                    </Button>
                                    <p style={{ marginTop: 8, fontSize: 12, color: '#757575' }}>
                                        {__('This regenerates URLs on the next save.', 'buttercup')}
                                    </p>
                                </>
                            )}
                        </>
                    )}
                </PanelBody>
                <PanelBody title={__('Sort Members', 'buttercup')} initialOpen={false}>
                    <p style={{ fontSize: 13, color: '#757575', marginTop: 0 }}>
                        {__('Reorder all members at once, or drag individual cards.', 'buttercup')}
                    </p>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        <Button variant="secondary" onClick={() => sortBlocks('name')}>
                            {__('Sort A→Z by Name', 'buttercup')}
                        </Button>
                        <Button variant="secondary" onClick={() => sortBlocks('position')}>
                            {__('Sort A→Z by Position', 'buttercup')}
                        </Button>
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <InnerBlocks
                    allowedBlocks={ALLOWED_BLOCKS}
                    template={TEMPLATE}
                    orientation="horizontal"
                    renderAppender={() => <AddMemberButton clientId={clientId} />}
                />
            </div>
        </>
    );
}
