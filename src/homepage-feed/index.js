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
			name: 'homepage-mast',
			title: __( 'Homepage Mast', 'buttercup' ),
			description: __(
				'Featured hero item from the newest mast-tagged post or page. Set it and forget it.',
				'buttercup'
			),
			attributes: { renderMode: 'mast' },
			scope: [ 'inserter', 'transform' ],
			isDefault: true,
			icon: 'cover-image',
		},
		{
			name: 'homepage-items',
			title: __( 'Homepage Items', 'buttercup' ),
			description: __(
				'Automatically shows every home-tagged post or page. Items appear and disappear as you tag content.',
				'buttercup'
			),
			attributes: { renderMode: 'home-all' },
			scope: [ 'inserter', 'transform' ],
			icon: 'format-aside',
		},
	],
} );
