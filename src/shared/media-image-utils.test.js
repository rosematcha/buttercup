/* eslint-env jest */
import { buildSrcSetFromMedia, pickBestCandidate } from './media-image-utils';

describe( 'media-image-utils', () => {
	it( 'filters cropped and mismatched aspect-ratio candidates from srcset', () => {
		const media = {
			source_url: 'https://example.com/full.jpg',
			media_details: {
				width: 2000,
				height: 1000,
				sizes: {
					medium: {
						source_url: 'https://example.com/medium.jpg',
						width: 300,
						height: 150,
						crop: false,
					},
					badratio: {
						source_url: 'https://example.com/badratio.jpg',
						width: 300,
						height: 300,
						crop: false,
					},
					thumb: {
						source_url: 'https://example.com/thumb.jpg',
						width: 150,
						height: 150,
						crop: true,
					},
				},
			},
		};

		const srcSet = buildSrcSetFromMedia( media );

		expect( srcSet ).toContain( 'medium.jpg 300w' );
		expect( srcSet ).toContain( 'full.jpg 2000w' );
		expect( srcSet ).not.toContain( 'badratio.jpg' );
		expect( srcSet ).not.toContain( 'thumb.jpg' );
	} );

	it( 'picks best candidate at or above target width', () => {
		const media = {
			url: 'https://example.com/fallback.jpg',
			source_url: 'https://example.com/full.jpg',
			media_details: {
				width: 2000,
				height: 1200,
				sizes: {
					small: {
						source_url: 'https://example.com/small.jpg',
						width: 320,
						height: 192,
						crop: false,
					},
					large: {
						source_url: 'https://example.com/large.jpg',
						width: 1200,
						height: 720,
						crop: false,
					},
				},
			},
		};

		const result = pickBestCandidate( media, 800 );
		expect( result.url ).toBe( 'https://example.com/large.jpg' );
		expect( result.width ).toBe( 1200 );
	} );
} );
