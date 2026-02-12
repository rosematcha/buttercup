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
    Button,
    SelectControl,
    BaseControl,
    ToolbarGroup,
    ToolbarButton,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { cloneBlock } from '@wordpress/blocks';

export default function Edit({ attributes, setAttributes, context, clientId }) {
    const {
        name,
        pronouns,
        position,
        bio,
        profileImageId,
        profileImageUrl,
        socialLinks,
    } = attributes;

    const imageShape = context['buttercup/imageShape'] || 'circle';
    const showBio = context['buttercup/showBio'] !== false;
    const showSocial = context['buttercup/showSocial'] !== false;

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
    const onSelectImage = (media) => {
        setAttributes({ profileImageId: media.id, profileImageUrl: media.url });
    };

    const onRemoveImage = () => {
        setAttributes({ profileImageId: 0, profileImageUrl: '' });
    };

    const shapeClass = `buttercup-team-member__image-wrap--${imageShape}`;

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
                                    placeholder="https://"
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
                            onKeyDown={(e) => e.key === 'Enter' && open()}
                            role="button"
                            tabIndex={0}
                            aria-label={profileImageUrl
                                ? __('Replace profile image', 'buttercup')
                                : __('Upload profile image', 'buttercup')}
                        >
                            {profileImageUrl ? (
                                <img
                                    src={profileImageUrl}
                                    alt={sprintf(__('Photo of %s', 'buttercup'), name || __('team member', 'buttercup'))}
                                    className="buttercup-team-member__image"
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
                {(pronouns || position) && (
                    <p className="buttercup-team-member__subtitle">
                        {pronouns && <span className="buttercup-team-member__pronouns">{pronouns}</span>}
                        {pronouns && position && <span className="buttercup-team-member__sep"> · </span>}
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
                {showBio && (
                    <RichText
                        tagName="p"
                        className="buttercup-team-member__bio"
                        placeholder={__('Short bio…', 'buttercup')}
                        value={bio}
                        onChange={(v) => setAttributes({ bio: v })}
                        aria-label={__('Biography', 'buttercup')}
                    />
                )}
            </div>
        </article>
    );
}
