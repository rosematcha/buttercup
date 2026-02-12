import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';

export default function save({ attributes }) {
    const { name, pronouns, position, bio, profileImageUrl, socialLinks } = attributes;

    return (
        <article {...useBlockProps.save({ className: 'buttercup-team-member' })}>
            {profileImageUrl && (
                <div className="buttercup-team-member__image-wrap">
                    <img
                        src={profileImageUrl}
                        alt={sprintf(__('Photo of %s', 'buttercup'), name || __('team member', 'buttercup'))}
                        className="buttercup-team-member__image"
                    />
                </div>
            )}

            <div className="buttercup-team-member__text">
                <RichText.Content tagName="h3" className="buttercup-team-member__name" value={name} />

                {(pronouns || position) && (
                    <p className="buttercup-team-member__subtitle">
                        {pronouns && <span className="buttercup-team-member__pronouns">{pronouns}</span>}
                        {pronouns && position && <span className="buttercup-team-member__sep"> · </span>}
                        {position && <span className="buttercup-team-member__position">{position}</span>}
                    </p>
                )}

                {bio && (
                    <div className="buttercup-team-member__bio-wrap">
                        <RichText.Content tagName="p" className="buttercup-team-member__bio" value={bio} />
                        <button className="buttercup-team-member__bio-toggle" aria-expanded="false">
                            {__('Read more', 'buttercup')}
                        </button>
                    </div>
                )}

                {socialLinks.length > 0 && (
                    <div className="buttercup-team-member__social">
                        {socialLinks.map((link, i) => (
                            <a
                                key={i}
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`buttercup-social-link buttercup-social-link--${link.platform}`}
                            >
                                <span className="screen-reader-text">
                                    {sprintf(__('%s on %s', 'buttercup'), name || __('Team member', 'buttercup'), link.platform)}
                                </span>
                                {link.platform === 'website' && <span className="dashicons dashicons-admin-site" aria-hidden="true" />}
                                {link.platform === 'facebook' && <span className="dashicons dashicons-facebook" aria-hidden="true" />}
                                {link.platform === 'instagram' && <span className="dashicons dashicons-instagram" aria-hidden="true" />}
                                {link.platform === 'linkedin' && <span className="dashicons dashicons-linkedin" aria-hidden="true" />}
                                {link.platform === 'bluesky' && <span className="dashicons dashicons-cloud" aria-hidden="true" />}
                                {link.platform === 'x' && <span className="dashicons dashicons-twitter" aria-hidden="true" />}
                            </a>
                        ))}
                    </div>
                )}
            </div>
        </article>
    );
}
