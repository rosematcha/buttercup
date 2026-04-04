import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import './style.scss';
import './editor.scss';

import Edit from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	variations: [
		{
			name: 'team-cards',
			title: __( 'Team Grid — Cards', 'buttercup' ),
			description: __(
				'Team members in styled cards with shadows and hover effects.',
				'buttercup'
			),
			attributes: {
				imageShape: 'squircle',
				imageSize: 192,
				cardBackground: '#ffffff',
				cardBorderRadius: 16,
				cardPadding: 20,
				cardShadow: 'soft',
				cardHoverEffect: 'lift',
				minCardWidth: 230,
				columnGap: 24,
				rowGap: 24,
				textAlign: 'center',
				showBio: true,
				showPronouns: true,
			},
			scope: [ 'inserter', 'transform' ],
			isDefault: true,
			icon: 'groups',
		},
		{
			name: 'team-minimal',
			title: __( 'Team Grid — Minimal', 'buttercup' ),
			description: __(
				'Clean layout with no card styling, just photos and text.',
				'buttercup'
			),
			attributes: {
				imageShape: 'circle',
				imageSize: 120,
				cardBackground: '',
				cardBorderRadius: 0,
				cardPadding: 0,
				cardShadow: 'none',
				cardHoverEffect: 'none',
				minCardWidth: 180,
				columnGap: 40,
				rowGap: 20,
				textAlign: 'center',
				showBio: false,
				showPronouns: false,
			},
			scope: [ 'inserter', 'transform' ],
			icon: 'grid-view',
		},
		{
			name: 'team-compact',
			title: __( 'Team Grid — Compact', 'buttercup' ),
			description: __(
				'Small cards, left-aligned text, ideal for large teams.',
				'buttercup'
			),
			attributes: {
				imageShape: 'circle',
				imageSize: 64,
				cardBackground: '',
				cardBorderRadius: 8,
				cardPadding: 12,
				cardShadow: 'none',
				cardHoverEffect: 'lift',
				minCardWidth: 200,
				columnGap: 16,
				rowGap: 12,
				textAlign: 'left',
				showBio: false,
				showPronouns: true,
			},
			scope: [ 'inserter', 'transform' ],
			icon: 'editor-ul',
		},
	],
} );
