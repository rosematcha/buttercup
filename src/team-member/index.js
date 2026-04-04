import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import './editor.scss';

import Edit from './edit';
import save from './save';
import deprecatedV1Save from './deprecated-v1-save';
import metadata from './block.json';

/* Build the v1 attribute set: swap enableMemberPage back to disableMemberPage. */
const { enableMemberPage: _dropped, ...v1Attributes } = metadata.attributes;

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	deprecated: [
		{
			attributes: {
				...v1Attributes,
				disableMemberPage: {
					type: 'boolean',
					default: false,
				},
			},
			save: deprecatedV1Save,
			migrate( attributes ) {
				const { disableMemberPage, ...rest } = attributes;
				return {
					...rest,
					enableMemberPage: ! disableMemberPage,
				};
			},
		},
	],
} );
