import { __ } from '@wordpress/i18n';

/**
 * Frontend script for the bio "Read more / Read less" toggle.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.buttercup-team-member__bio-toggle').forEach((btn) => {
        const team = btn.closest('.buttercup-team');
        const readMoreLabel = team?.dataset.readMore?.trim() || __('Read more', 'buttercup');
        const readLessLabel = team?.dataset.readLess?.trim() || __('Read less', 'buttercup');
        const wrap = btn.closest('.buttercup-team-member__bio-wrap');
        const bio = wrap?.querySelector('.buttercup-team-member__bio');
        if (!bio) return;
        btn.textContent = readMoreLabel;

        // Hide the toggle entirely if the bio fits within 3 lines.
        if (bio.scrollHeight <= bio.clientHeight) {
            btn.style.display = 'none';
            return;
        }

        btn.addEventListener('click', () => {
            const expanded = wrap.classList.toggle('is-expanded');
            btn.setAttribute('aria-expanded', expanded);
            btn.textContent = expanded ? readLessLabel : readMoreLabel;
        });
    });
});
