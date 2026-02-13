import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';

export default function save({ attributes }) {
    const {
        name,
        pronouns,
        position,
        bio,
        showBio,
        memberSlug,
        memberPagesEnabled,
        disableMemberPage,
        profileImageUrl,
        profileImageSource,
        profileImageAlt,
        profileImageSrcSet,
        profileImageSizes,
        profileImageWidth,
        profileImageHeight,
        socialLinks,
    } = attributes;
    const safeLinks = (socialLinks || []).filter((link) => link?.url?.trim());
    const buildSocialHref = (link) => {
        if (link.platform === 'email') {
            const value = link.url.trim();
            return value.startsWith('mailto:') ? value : `mailto:${value}`;
        }
        return link.url;
    };
    const getPlatformLabel = (platform) => {
        switch (platform) {
            case 'website':
                return __('Website', 'buttercup');
            case 'email':
                return __('Email', 'buttercup');
            case 'bluesky':
                return __('Bluesky', 'buttercup');
            case 'facebook':
                return __('Facebook', 'buttercup');
            case 'instagram':
                return __('Instagram', 'buttercup');
            case 'linkedin':
                return __('LinkedIn', 'buttercup');
            case 'x':
                return __('X', 'buttercup');
            default:
                return platform;
        }
    };

    const showMemberLink = memberPagesEnabled && !disableMemberPage && memberSlug;
    const buildMemberHref = () => {
        if (!showMemberLink) return undefined;
        return `./${memberSlug}`;
    };

    const useSquare = profileImageSource === 'square-600';
    const imageSrcSet = useSquare ? undefined : (profileImageSrcSet || undefined);
    const imageSizes = useSquare ? undefined : (profileImageSizes || undefined);
    const imageWidth = useSquare ? 600 : (profileImageWidth || undefined);
    const imageHeight = useSquare ? 600 : (profileImageHeight || undefined);

    return (
        <article {...useBlockProps.save({ className: 'buttercup-team-member' })}>
            {profileImageUrl && (
                <div className="buttercup-team-member__image-wrap">
                    {showMemberLink ? (
                        <a
                            className="buttercup-team-member__link"
                            href={buildMemberHref()}
                            data-member-slug={memberSlug}
                        >
                            <img
                                src={profileImageUrl}
                                srcSet={imageSrcSet}
                                sizes={imageSizes}
                                width={imageWidth}
                                height={imageHeight}
                                alt={profileImageAlt || sprintf(__('Photo of %s', 'buttercup'), name || __('team member', 'buttercup'))}
                                className="buttercup-team-member__image"
                                loading="lazy"
                                decoding="async"
                                style={{ objectFit: 'contain', objectPosition: 'center' }}
                            />
                        </a>
                    ) : (
                        <img
                            src={profileImageUrl}
                            srcSet={imageSrcSet}
                            sizes={imageSizes}
                            width={imageWidth}
                            height={imageHeight}
                            alt={profileImageAlt || sprintf(__('Photo of %s', 'buttercup'), name || __('team member', 'buttercup'))}
                            className="buttercup-team-member__image"
                            loading="lazy"
                            decoding="async"
                            style={{ objectFit: 'contain', objectPosition: 'center' }}
                        />
                    )}
                </div>
            )}

            <div className="buttercup-team-member__text">
                {showMemberLink ? (
                    <h3 className="buttercup-team-member__name">
                        <a
                            className="buttercup-team-member__link buttercup-team-member__link--name"
                            href={buildMemberHref()}
                            data-member-slug={memberSlug}
                        >
                            <RichText.Content tagName="span" value={name} />
                        </a>
                    </h3>
                ) : (
                    <RichText.Content tagName="h3" className="buttercup-team-member__name" value={name} />
                )}

                {(pronouns || position) && (
                    <p className="buttercup-team-member__subtitle">
                        {pronouns && <span className="buttercup-team-member__pronouns">{pronouns}</span>}
                        {pronouns && position && <span className="buttercup-team-member__sep"> · </span>}
                        {position && <span className="buttercup-team-member__position">{position}</span>}
                    </p>
                )}

                {bio && showBio && (
                    <div className="buttercup-team-member__bio-wrap">
                        <RichText.Content tagName="p" className="buttercup-team-member__bio" value={bio} />
                        <button className="buttercup-team-member__bio-toggle" aria-expanded="false">
                            {__('Read more', 'buttercup')}
                        </button>
                    </div>
                )}

                {safeLinks.length > 0 && (
                    <div className="buttercup-team-member__social">
                        {safeLinks.map((link, i) => {
                            const isEmail = link.platform === 'email';
                            return (
                            <a
                                key={i}
                                href={buildSocialHref(link)}
                                target={isEmail ? undefined : '_blank'}
                                rel={isEmail ? undefined : 'noopener noreferrer'}
                                className={`buttercup-social-link buttercup-social-link--${link.platform}`}
                            >
                                <span className="screen-reader-text">
                                    {sprintf(__('%s on %s', 'buttercup'), name || __('Team member', 'buttercup'), getPlatformLabel(link.platform))}
                                </span>
                                {link.platform === 'website' && <span className="dashicons dashicons-admin-site" aria-hidden="true" />}
                                {link.platform === 'facebook' && <span className="dashicons dashicons-facebook" aria-hidden="true" />}
                                {link.platform === 'instagram' && <span className="dashicons dashicons-instagram" aria-hidden="true" />}
                                {link.platform === 'linkedin' && <span className="dashicons dashicons-linkedin" aria-hidden="true" />}
                                {link.platform === 'bluesky' && <span className="dashicons dashicons-cloud" aria-hidden="true" />}
                                {link.platform === 'x' && <span className="dashicons dashicons-twitter" aria-hidden="true" />}
                                {link.platform === 'email' && <span className="dashicons dashicons-email" aria-hidden="true" />}
                                <span className="buttercup-social-link__label">{getPlatformLabel(link.platform)}</span>
                            </a>
                            );
                        })}
                    </div>
                )}
            </div>
        </article>
    );
}
