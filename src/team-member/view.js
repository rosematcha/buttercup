/**
 * Frontend script for the bio "Read more / Read less" toggle.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.buttercup-team-member__bio-toggle').forEach((btn) => {
        const wrap = btn.closest('.buttercup-team-member__bio-wrap');
        const bio = wrap?.querySelector('.buttercup-team-member__bio');
        if (!bio) return;

        // Hide the toggle entirely if the bio fits within 3 lines.
        if (bio.scrollHeight <= bio.clientHeight) {
            btn.style.display = 'none';
            return;
        }

        btn.addEventListener('click', () => {
            const expanded = wrap.classList.toggle('is-expanded');
            btn.setAttribute('aria-expanded', expanded);
            btn.textContent = expanded ? 'Read less' : 'Read more';
        });
    });
});
