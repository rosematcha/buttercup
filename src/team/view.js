document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.buttercup-team[data-member-pages="1"]').forEach((team) => {
        const links = team.querySelectorAll('.buttercup-team-member__link[data-member-slug]');
        if (!links.length) return;

        const slugs = new Set(
            Array.from(links)
                .map((link) => link.dataset.memberSlug)
                .filter(Boolean)
        );

        let basePath = window.location.pathname.replace(/\/$/, '');
        slugs.forEach((slug) => {
            if (basePath.endsWith(`/${slug}`)) {
                basePath = basePath.slice(0, -(slug.length + 1));
            }
        });

        basePath = basePath.replace(/\/$/, '');
        links.forEach((link) => {
            const slug = link.dataset.memberSlug;
            if (!slug) return;
            link.setAttribute('href', `${basePath}/${slug}`);
        });
    });
});
