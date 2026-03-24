/* eslint-env jest */
import { buildMemberSlugAssignments } from './member-slug-utils';

describe( 'buildMemberSlugAssignments', () => {
	it( 'builds unique slugs while preserving existing ones', () => {
		const assignments = buildMemberSlugAssignments( [
			{ clientId: 'a', name: 'Alice Doe', memberSlug: '' },
			{ clientId: 'b', name: 'Alice Smith', memberSlug: '' },
			{ clientId: 'c', name: 'Bob Ray', memberSlug: 'bob' },
			{ clientId: 'd', name: 'Bob Lee', memberSlug: '' },
		] );

		expect( assignments ).toEqual( {
			a: 'alice-doe',
			b: 'alice-smith',
			d: 'bob-lee',
		} );
	} );

	it( 'returns empty assignment for members without a slug source', () => {
		const assignments = buildMemberSlugAssignments( [
			{ clientId: 'x', name: '', memberSlug: '' },
		] );

		expect( assignments ).toEqual( {} );
	} );
} );
