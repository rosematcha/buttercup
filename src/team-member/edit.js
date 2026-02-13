import { __, sprintf } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    MediaUpload,
    MediaUploadCheck,
    InspectorControls,
    BlockControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    TextareaControl,
    Button,
    SelectControl,
    ToggleControl,
    BaseControl,
    ToolbarGroup,
    ToolbarButton,
    Notice,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { cloneBlock } from '@wordpress/blocks';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { cleanForSlug } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes, context, clientId }) {
    const {
        name,
        pronouns,
        position,
        bio,
        showBio,
        longBio,
        email,
        phone,
        location,
        disableMemberPage,
        memberSlug,
        profileImageId,
        profileImageUrl,
        profileImageSource,
        profileImageAlt,
        profileImageSrcSet,
        profileImageSizes,
        profileImageWidth,
        profileImageHeight,
        socialLinks,
    } = attributes;

    const imageShape = context['buttercup/imageShape'] || 'circle';
    const imageSize = context['buttercup/imageSize'] || 120;
    const showBioFromParent = context['buttercup/showBio'] !== false;
    const showPronounsFromParent = context['buttercup/showPronouns'] !== false;
    const memberPagesEnabledFromParent = context['buttercup/enableMemberPages'] !== false;
    const shouldShowBio = showBioFromParent && showBio;
    const memberPagesEnabled = memberPagesEnabledFromParent && !disableMemberPage;
    const [showSlugSettings, setShowSlugSettings] = useState(false);

    const permalink = useSelect((select) => {
        try {
            const editorStore = select('core/editor');
            if (editorStore?.getPermalink) {
                return editorStore.getPermalink();
            }
        } catch (e) {
            // Ignore missing editor store (e.g. Site Editor context).
        }

        return '';
    }, []);
    const allBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(),
        []
    );
    const media = useSelect(
        (select) => (profileImageId ? select('core').getMedia(profileImageId) : null),
        [profileImageId]
    );

    const slugCounts = useMemo(() => {
        const counts = {};
        const collect = (blocks) => {
            blocks.forEach((block) => {
                if (block.name === 'buttercup/team') {
                    const teamEnabled = block.attributes?.enableMemberPages !== false;
                    if (teamEnabled) {
                        (block.innerBlocks || []).forEach((inner) => {
                            if (inner.name !== 'buttercup/team-member') return;
                            if (inner.attributes?.disableMemberPage) return;
                            const slug = (inner.attributes?.memberSlug || '').trim();
                            if (slug) {
                                counts[slug] = (counts[slug] || 0) + 1;
                            }
                        });
                    }
                }
                if (block.innerBlocks?.length) {
                    collect(block.innerBlocks);
                }
            });
        };
        collect(allBlocks);
        return counts;
    }, [allBlocks]);

    const slugDuplicate = memberSlug && slugCounts[memberSlug] > 1;
    const baseUrl = permalink ? permalink.split('?')[0].replace(/\/$/, '') : '';
    const memberUrl = memberPagesEnabled && memberSlug && baseUrl ? `${baseUrl}/${memberSlug}` : '';
    const conflictSuggestion = slugDuplicate ? `${memberSlug}-${clientId.slice(0, 4)}` : '';

    /* ── Duplicate / Remove helpers ── */
    const currentBlock = useSelect(
        (select) => select(blockEditorStore).getBlock(clientId),
        [clientId]
    );
    const parentClientId = useSelect(
        (select) => select(blockEditorStore).getBlockRootClientId(clientId),
        [clientId]
    );
    const { insertBlock, removeBlock } = useDispatch(blockEditorStore);

    const duplicateMember = () => {
        if (currentBlock) {
            const clone = cloneBlock(currentBlock);
            insertBlock(clone, undefined, parentClientId);
        }
    };

    const removeMember = () => {
        removeBlock(clientId);
    };

    /* ── Social link helpers ── */
    const updateSocialLink = (index, key, value) => {
        const updated = [...socialLinks];
        updated[index] = { ...updated[index], [key]: value };
        setAttributes({ socialLinks: updated });
    };

    const addSocialLink = () => {
        setAttributes({
            socialLinks: [...socialLinks, { platform: 'website', url: '' }],
        });
    };

    const removeSocialLink = (index) => {
        setAttributes({
            socialLinks: socialLinks.filter((_, i) => i !== index),
        });
    };

    /* ── Image helpers ── */
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

    const requestSquareImage = async (imageId) => {
        try {
            return await apiFetch({
                path: '/buttercup/v1/square-image',
                method: 'POST',
                data: { id: imageId },
            });
        } catch (e) {
            return null;
        }
    };

    const onSelectImage = async (mediaItem) => {
        const target = imageSize || 600;
        const alt = mediaItem?.alt_text || mediaItem?.alt || '';
        const square = await requestSquareImage(mediaItem.id);

        if (square?.url) {
            setAttributes({
                profileImageId: mediaItem.id,
                profileImageUrl: square.url,
                profileImageAlt: alt,
                profileImageSrcSet: '',
                profileImageSizes: `${target}px`,
                profileImageWidth: square.width || 600,
                profileImageHeight: square.height || 600,
                profileImageSource: 'square-600',
            });
            return;
        }

        const best = pickBestUrl(mediaItem, target);
        const srcSet = buildSrcSet(mediaItem);
        const sizes = `${target}px`;
        setAttributes({
            profileImageId: mediaItem.id,
            profileImageUrl: best.url || mediaItem.url,
            profileImageAlt: alt,
            profileImageSrcSet: srcSet,
            profileImageSizes: sizes,
            profileImageWidth: best.width || mediaItem.media_details?.width || 0,
            profileImageHeight: best.height || mediaItem.media_details?.height || 0,
            profileImageSource: 'auto',
        });
    };

    useEffect(() => {
        if (!profileImageId || !media) return;
        if (profileImageSource === 'square-600') return;
        const target = imageSize || 600;
        const best = pickBestUrl(media, target);
        const srcSet = buildSrcSet(media);
        const alt = media?.alt_text || media?.alt || '';
        const sizes = `${target}px`;
        const nextAttrs = {};

        if (best.url && best.url !== profileImageUrl) {
            nextAttrs.profileImageUrl = best.url;
        }
        if (alt !== (profileImageAlt || '')) {
            nextAttrs.profileImageAlt = alt;
        }
        if (srcSet && srcSet !== (profileImageSrcSet || '')) {
            nextAttrs.profileImageSrcSet = srcSet;
        }
        if (sizes !== (profileImageSizes || '')) {
            nextAttrs.profileImageSizes = sizes;
        }
        if ((best.width || 0) !== (profileImageWidth || 0)) {
            nextAttrs.profileImageWidth = best.width || 0;
        }
        if ((best.height || 0) !== (profileImageHeight || 0)) {
            nextAttrs.profileImageHeight = best.height || 0;
        }

        if (Object.keys(nextAttrs).length) {
            setAttributes(nextAttrs);
        }
    }, [
        profileImageId,
        media,
        imageSize,
        profileImageUrl,
        profileImageSource,
        profileImageAlt,
        profileImageSrcSet,
        profileImageSizes,
        profileImageWidth,
        profileImageHeight,
        setAttributes,
    ]);

    const onRemoveImage = () => {
        setAttributes({ profileImageId: 0, profileImageUrl: '' });
    };

    const shapeClass = `buttercup-team-member__image-wrap--${imageShape}`;
    const getSocialPlaceholder = (platform) => {
        switch (platform) {
            case 'email':
                return __('name@example.com', 'buttercup');
            case 'website':
                return 'https://';
            default:
                return 'https://';
        }
    };

    return (
        <article {...useBlockProps({ className: 'buttercup-team-member' })}>
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon="admin-page"
                        label={__('Duplicate Member', 'buttercup')}
                        onClick={duplicateMember}
                    />
                    <ToolbarButton
                        icon="trash"
                        label={__('Remove Member', 'buttercup')}
                        onClick={removeMember}
                    />
                </ToolbarGroup>
            </BlockControls>

            <InspectorControls>
                <PanelBody title={__('Profile Image', 'buttercup')} initialOpen={true}>
                    <MediaUploadCheck>
                        {profileImageUrl && (
                            <div style={{ marginBottom: 12 }}>
                                <img
                                    src={profileImageUrl}
                                    alt={name || __('Preview', 'buttercup')}
                                    style={{ width: '100%', borderRadius: 4 }}
                                />
                            </div>
                        )}
                        <div style={{ display: 'flex', gap: 8 }}>
                            <MediaUpload
                                onSelect={onSelectImage}
                                allowedTypes={['image']}
                                value={profileImageId}
                                render={({ open }) => (
                                    <Button variant="secondary" onClick={open}>
                                        {profileImageUrl
                                            ? __('Replace Image', 'buttercup')
                                            : __('Upload Image', 'buttercup')}
                                    </Button>
                                )}
                            />
                            {profileImageUrl && (
                                <Button isDestructive variant="tertiary" onClick={onRemoveImage}>
                                    {__('Remove', 'buttercup')}
                                </Button>
                            )}
                        </div>
                    </MediaUploadCheck>
                </PanelBody>

                <PanelBody title={__('Member Details', 'buttercup')} initialOpen={true}>
                    <TextControl
                        label={__('Pronouns', 'buttercup')}
                        value={pronouns}
                        onChange={(v) => setAttributes({ pronouns: v })}
                        placeholder={__('e.g. she/her', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                    {!showPronounsFromParent && (
                        <p style={{ marginTop: 4, color: '#757575', fontSize: 12 }}>
                            {__('Pronouns are hidden by the Team block setting.', 'buttercup')}
                        </p>
                    )}
                    <ToggleControl
                        label={__('Show Short Bio', 'buttercup')}
                        checked={showBio}
                        onChange={(v) => setAttributes({ showBio: v })}
                        disabled={!showBioFromParent}
                        help={
                            !showBioFromParent
                                ? __('Disabled by Team block setting.', 'buttercup')
                                : undefined
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Contact Info', 'buttercup')} initialOpen={false}>
                    <TextControl
                        label={__('Email', 'buttercup')}
                        value={email}
                        onChange={(v) => setAttributes({ email: v })}
                        placeholder={__('name@example.com', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Phone', 'buttercup')}
                        value={phone}
                        onChange={(v) => setAttributes({ phone: v })}
                        placeholder={__('(555) 555-5555', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Location', 'buttercup')}
                        value={location}
                        onChange={(v) => setAttributes({ location: v })}
                        placeholder={__('City, State', 'buttercup')}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>

                <PanelBody title={__('Social Links', 'buttercup')} initialOpen={false}>
                    {socialLinks.map((link, index) => (
                        <BaseControl key={index}>
                            <div style={{ marginBottom: 12, paddingBottom: 12, borderBottom: '1px solid #ddd' }}>
                                <SelectControl
                                    label={__('Platform', 'buttercup')}
                                    value={link.platform}
                                    options={[
                                        { label: 'Website', value: 'website' },
                                        { label: 'Email', value: 'email' },
                                        { label: 'BlueSky', value: 'bluesky' },
                                        { label: 'Facebook', value: 'facebook' },
                                        { label: 'Instagram', value: 'instagram' },
                                        { label: 'LinkedIn', value: 'linkedin' },
                                        { label: 'X (Twitter)', value: 'x' },
                                    ]}
                                    onChange={(v) => updateSocialLink(index, 'platform', v)}
                                    __nextHasNoMarginBottom
                                />
                                <TextControl
                                    label={__('URL', 'buttercup')}
                                    value={link.url}
                                    onChange={(v) => updateSocialLink(index, 'url', v)}
                                    placeholder={getSocialPlaceholder(link.platform)}
                                    __nextHasNoMarginBottom
                                />
                                <Button
                                    isDestructive
                                    variant="tertiary"
                                    onClick={() => removeSocialLink(index)}
                                    aria-label={sprintf(__('Remove %s link', 'buttercup'), link.platform)}
                                    style={{ marginTop: 4 }}
                                >
                                    {__('Remove', 'buttercup')}
                                </Button>
                            </div>
                        </BaseControl>
                    ))}
                    <Button variant="secondary" onClick={addSocialLink}>
                        {__('Add Social Link', 'buttercup')}
                    </Button>
                </PanelBody>
                <PanelBody title={__('Member Page', 'buttercup')} initialOpen={false}>
                    <ToggleControl
                        label={__('Disable Individual Page', 'buttercup')}
                        checked={disableMemberPage}
                        onChange={(v) => setAttributes({ disableMemberPage: v })}
                        disabled={!memberPagesEnabledFromParent}
                        help={
                            !memberPagesEnabledFromParent
                                ? __('Disabled by Team block setting.', 'buttercup')
                                : __('Keeps this person in the grid but removes their individual page.', 'buttercup')
                        }
                        __nextHasNoMarginBottom
                    />
                    {memberPagesEnabled && memberSlug && (
                        <p style={{ marginTop: 8, fontSize: 12, color: '#757575' }}>
                            {__('URL slug:', 'buttercup')} /{memberSlug}
                        </p>
                    )}
                    {memberPagesEnabled && !memberSlug && (
                        <p style={{ marginTop: 8, fontSize: 12, color: '#757575' }}>
                            {__('URL slug will be generated from the name.', 'buttercup')}
                        </p>
                    )}
                    {memberPagesEnabled && slugDuplicate && (
                        <Notice status="warning" isDismissible={false}>
                            {__('This slug is already used by another member on this page.', 'buttercup')}
                        </Notice>
                    )}
                    {memberPagesEnabled && slugDuplicate && showSlugSettings && (
                        <Button
                            variant="secondary"
                            onClick={() => setAttributes({ memberSlug: cleanForSlug(conflictSuggestion) })}
                        >
                            {__('Use Suggested Unique Slug', 'buttercup')}
                        </Button>
                    )}
                    {memberPagesEnabled && memberUrl && (
                        <div style={{ marginTop: 8 }}>
                            <Button
                                variant="secondary"
                                href={memberUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {__('Preview Member Page', 'buttercup')}
                            </Button>
                        </div>
                    )}
                    {memberPagesEnabled && !memberUrl && (
                        <p style={{ marginTop: 8, fontSize: 12, color: '#757575' }}>
                            {__('Save this page to generate a preview URL.', 'buttercup')}
                        </p>
                    )}
                    {memberPagesEnabled && (
                        <ToggleControl
                            label={__('Customize URL Slug', 'buttercup')}
                            checked={showSlugSettings}
                            onChange={(v) => setShowSlugSettings(v)}
                            __nextHasNoMarginBottom
                        />
                    )}
                    {memberPagesEnabled && showSlugSettings && (
                        <>
                            <TextControl
                                label={__('Custom URL Slug', 'buttercup')}
                                value={memberSlug}
                                onChange={(v) => setAttributes({ memberSlug: cleanForSlug(v) })}
                                help={__('Lowercase letters, numbers, and hyphens only.', 'buttercup')}
                                __nextHasNoMarginBottom
                            />
                            <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                                <Button
                                    variant="secondary"
                                    onClick={() => setAttributes({ memberSlug: '' })}
                                >
                                    {__('Reset Slug', 'buttercup')}
                                </Button>
                            </div>
                        </>
                    )}
                    {memberPagesEnabled && (
                        <TextareaControl
                            label={__('Long Bio', 'buttercup')}
                            value={longBio}
                            onChange={(v) => setAttributes({ longBio: v })}
                            help={__('Used on the individual member page. If empty, the short bio is used.', 'buttercup')}
                        />
                    )}
                </PanelBody>
            </InspectorControls>

            { /* Card body */}
            <MediaUploadCheck>
                <MediaUpload
                    onSelect={onSelectImage}
                    allowedTypes={['image']}
                    value={profileImageId}
                    render={({ open }) => (
                        <div
                            className={`buttercup-team-member__image-wrap ${shapeClass}`}
                            onClick={open}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                                    e.preventDefault();
                                    open();
                                }
                            }}
                            role="button"
                            tabIndex={0}
                            aria-label={profileImageUrl
                                ? __('Replace profile image', 'buttercup')
                                : __('Upload profile image', 'buttercup')}
                        >
                            {profileImageUrl ? (
                                <img
                                    src={profileImageUrl}
                                    srcSet={profileImageSrcSet || undefined}
                                    sizes={profileImageSizes || undefined}
                                    width={profileImageWidth || undefined}
                                    height={profileImageHeight || undefined}
                                    alt={profileImageAlt || sprintf(__('Photo of %s', 'buttercup'), name || __('team member', 'buttercup'))}
                                    className="buttercup-team-member__image"
                                    style={{ objectFit: 'contain', objectPosition: 'center' }}
                                />
                            ) : (
                                <span className="buttercup-team-member__image-placeholder-label">
                                    {__('+ Photo', 'buttercup')}
                                </span>
                            )}
                        </div>
                    )}
                />
            </MediaUploadCheck>

            <div className="buttercup-team-member__text">
                <RichText
                    tagName="h3"
                    className="buttercup-team-member__name"
                    placeholder={__('Name', 'buttercup')}
                    value={name}
                    onChange={(v) => setAttributes({ name: v })}
                    aria-label={__('Name', 'buttercup')}
                />
                {(showPronounsFromParent && pronouns) && (
                    <p className="buttercup-team-member__subtitle">
                        {showPronounsFromParent && pronouns && (
                            <span className="buttercup-team-member__pronouns">{pronouns}</span>
                        )}
                    </p>
                )}
                <RichText
                    tagName="p"
                    className="buttercup-team-member__position"
                    placeholder={__('Position', 'buttercup')}
                    value={position}
                    onChange={(v) => setAttributes({ position: v })}
                    aria-label={__('Position', 'buttercup')}
                />
                {shouldShowBio && (
                    <RichText
                        tagName="p"
                        className="buttercup-team-member__bio"
                        placeholder={__('Short bio…', 'buttercup')}
                        value={bio}
                        onChange={(v) => setAttributes({ bio: v })}
                        aria-label={__('Short bio', 'buttercup')}
                    />
                )}
            </div>
        </article>
    );
}
