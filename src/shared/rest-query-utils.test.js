/* eslint-env jest */
import {
	buildHomepageFeedStatusQuery,
	buildTagShowcaseStatusQuery,
	sanitizeIntInRange,
} from './rest-query-utils';

describe( 'rest-query-utils', () => {
	it( 'sanitizes bounded integers', () => {
		expect( sanitizeIntInRange( '99', 12, 1, 60 ) ).toBe( 60 );
		expect( sanitizeIntInRange( '-4', 0, 0, 50 ) ).toBe( 0 );
		expect( sanitizeIntInRange( 'abc', 10, 0, 20 ) ).toBe( 10 );
	} );

	it( 'builds homepage-feed query with sanitized slugs', () => {
		const query = buildHomepageFeedStatusQuery( {
			mastTagSlug: ' Mast Tag ',
			homeTagSlug: '',
		} );

		expect( query.get( 'mastTagSlug' ) ).toBe( 'mast-tag' );
		expect( query.get( 'homeTagSlug' ) ).toBe( 'home' );
	} );

	it( 'builds tag-showcase query with sanitized values', () => {
		const query = buildTagShowcaseStatusQuery( {
			tagSlugs: [ 'News', '  ', 'events-2026' ],
			tagMatch: 'invalid',
			postTypes: [ 'post', '', 'page' ],
			excludeCurrentPost: true,
			offset: -100,
			maxItems: 999,
		} );

		expect( query.get( 'tagSlugs' ) ).toBe( 'news,events-2026' );
		expect( query.get( 'tagMatch' ) ).toBe( 'any' );
		expect( query.get( 'postTypes' ) ).toBe( 'post,page' );
		expect( query.get( 'excludeCurrentPost' ) ).toBe( '1' );
		expect( query.get( 'offset' ) ).toBe( '0' );
		expect( query.get( 'maxItems' ) ).toBe( '60' );
	} );
} );
