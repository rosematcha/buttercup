import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Notice,
	Spinner,
	FormTokenField,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { cleanForSlug } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import { buildHomepageFeedStatusQuery } from '../shared/rest-query-utils';

function countHomepageFeedBlocks( blocks ) {
	let count = 0;

	blocks.forEach( ( block ) => {
		if ( block.name === 'buttercup/homepage-feed' ) {
			count += 1;
		}
		if ( block.innerBlocks && block.innerBlocks.length ) {
			count += countHomepageFeedBlocks( block.innerBlocks );
		}
	} );

	return count;
}

function normalizeSlug( value, fallback ) {
	const slug = cleanForSlug( String( value || '' ).trim() );
	return slug || fallback;
}

function normalizeTokenSlug( tokens ) {
	if ( ! Array.isArray( tokens ) || tokens.length === 0 ) {
		return '';
	}
	const last = tokens[ tokens.length - 1 ];
	return normalizeSlug( last, '' );
}

function getTagCountHint( input, fallback, tagCounts ) {
	const resolvedSlug = normalizeSlug( input, fallback );
	if ( tagCounts.has( resolvedSlug ) ) {
		return `${ resolvedSlug }: ${ Number(
			tagCounts.get( resolvedSlug ) || 0
		) } ${ __( 'item(s)', 'buttercup' ) }`;
	}

	if ( input ) {
		return `${ resolvedSlug }: ${ __(
			'custom slug (term not found yet)',
			'buttercup'
		) }`;
	}

	return `${ __( 'Using default', 'buttercup' ) }: ${ fallback }`;
}

export default function Edit( { attributes, setAttributes } ) {
	const { ctaLabel, homeTagSlug, mastTagSlug } = attributes;

	const allBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);

	const tags = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'post_tag', {
				per_page: 100,
				hide_empty: false,
			} ) || [],
		[]
	);

	const [ status, setStatus ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ errorMessage, setErrorMessage ] = useState( '' );
	const [ previewViewport, setPreviewViewport ] = useState( 'desktop' );
	const statusRequestRef = useRef( 0 );

	const tagSuggestions = useMemo(
		() =>
			tags
				.map( ( term ) => term.slug )
				.filter( Boolean )
				.sort( ( a, b ) => a.localeCompare( b ) ),
		[ tags ]
	);

	const tagCounts = useMemo( () => {
		const map = new Map();
		tags.forEach( ( term ) => {
			if ( term?.slug ) {
				map.set( term.slug, Number( term.count ) || 0 );
			}
		} );
		return map;
	}, [ tags ] );

	const blockCount = countHomepageFeedBlocks( allBlocks );
	const countsMessage = `${ __( 'Found', 'buttercup' ) } ${ Number(
		status?.mastCount || 0
	) } ${ __( 'mast item(s) and', 'buttercup' ) } ${ Number(
		status?.homeCount || 0
	) } ${ __( 'home item(s).', 'buttercup' ) }`;

	useEffect( () => {
		const mast = normalizeSlug( mastTagSlug, 'mast' );
		const home = normalizeSlug( homeTagSlug, 'home' );

		const requestId = statusRequestRef.current + 1;
		statusRequestRef.current = requestId;

		setIsLoading( true );
		setErrorMessage( '' );

		const controller =
			typeof AbortController !== 'undefined'
				? new AbortController()
				: null;

		const timer = setTimeout( () => {
			const query = buildHomepageFeedStatusQuery( {
				mastTagSlug: mast,
				homeTagSlug: home,
			} );

			apiFetch( {
				path: `/buttercup/v1/homepage-feed-status?${ query.toString() }`,
				signal: controller?.signal,
			} )
				.then( ( data ) => {
					if ( requestId !== statusRequestRef.current ) {
						return;
					}
					setStatus( data );
					setIsLoading( false );
				} )
				.catch( ( error ) => {
					if (
						requestId !== statusRequestRef.current ||
						error?.name === 'AbortError'
					) {
						return;
					}
					setStatus( null );
					setIsLoading( false );
					setErrorMessage(
						error?.message ||
							__(
								'Unable to load homepage feed status.',
								'buttercup'
							)
					);
				} );
		}, 280 );

		return () => {
			clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}
		};
	}, [ mastTagSlug, homeTagSlug ] );

	const blockProps = useBlockProps( {
		className: 'buttercup-homepage-feed-editor',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feed Settings', 'buttercup' ) }>
					<TextControl
						label={ __( 'CTA Label', 'buttercup' ) }
						value={ ctaLabel }
						onChange={ ( value ) =>
							setAttributes( { ctaLabel: value } )
						}
						help={ __(
							'Used in split/text sections.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					<FormTokenField
						label={ __( 'Mast Tag Slug', 'buttercup' ) }
						value={ mastTagSlug ? [ mastTagSlug ] : [] }
						onChange={ ( tokens ) =>
							setAttributes( {
								mastTagSlug: normalizeTokenSlug( tokens ),
							} )
						}
						suggestions={ tagSuggestions }
						help={ __(
							'Type or select a tag slug. Custom values are allowed.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					<p className="buttercup-homepage-feed-editor__tag-hint">
						{ getTagCountHint( mastTagSlug, 'mast', tagCounts ) }
					</p>
					<FormTokenField
						label={ __( 'Home Tag Slug', 'buttercup' ) }
						value={ homeTagSlug ? [ homeTagSlug ] : [] }
						onChange={ ( tokens ) =>
							setAttributes( {
								homeTagSlug: normalizeTokenSlug( tokens ),
							} )
						}
						suggestions={ tagSuggestions }
						help={ __(
							'Type or select a tag slug. Custom values are allowed.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					<p className="buttercup-homepage-feed-editor__tag-hint">
						{ getTagCountHint( homeTagSlug, 'home', tagCounts ) }
					</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3>{ __( 'Homepage Feed', 'buttercup' ) }</h3>
				<p className="buttercup-homepage-feed-editor__summary">
					{ __(
						'Renders one mast item and up to five home items from tagged posts/pages.',
						'buttercup'
					) }
				</p>

				{ blockCount > 1 && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Use only one Homepage Feed block per page to avoid duplicate mast/home content.',
							'buttercup'
						) }
					</Notice>
				) }

				{ errorMessage && (
					<Notice status="error" isDismissible={ false }>
						{ errorMessage }
					</Notice>
				) }

				{ isLoading && (
					<div className="buttercup-homepage-feed-editor__loading">
						<Spinner />
					</div>
				) }

				{ ! isLoading && status && (
					<div className="buttercup-homepage-feed-editor__status">
						{ ( status.mastOverflow ||
							status.homeOverflow ||
							( status.dualTagged &&
								status.dualTagged.length > 0 ) ) && (
							<div className="buttercup-homepage-feed-editor__warnings">
								{ status.mastOverflow && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'More than one mast-tagged item found. Only the newest mast item will render.',
											'buttercup'
										) }
									</Notice>
								) }
								{ status.homeOverflow && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'More than five home-tagged items found. Only the newest five home items will render.',
											'buttercup'
										) }
									</Notice>
								) }
								{ status.dualTagged &&
									status.dualTagged.length > 0 && (
										<Notice
											status="warning"
											isDismissible={ false }
										>
											{ __(
												'Some items have both mast and home tags. They are treated as mast-only.',
												'buttercup'
											) }
										</Notice>
									) }
							</div>
						) }

						<p>{ countsMessage }</p>

						<div className="buttercup-homepage-feed-editor__list-wrap">
							<h4>{ __( 'Selected Mast', 'buttercup' ) }</h4>
							{ status.mastSelected &&
							status.mastSelected.length > 0 ? (
								<ul>
									{ status.mastSelected.map( ( item ) => (
										<li key={ item.id }>
											{ item.title ||
												__(
													'(Untitled)',
													'buttercup'
												) }
										</li>
									) ) }
								</ul>
							) : (
								<p>
									{ __(
										'No mast item selected.',
										'buttercup'
									) }
								</p>
							) }
						</div>

						<div className="buttercup-homepage-feed-editor__list-wrap">
							<h4>
								{ __( 'Selected Home Items', 'buttercup' ) }
							</h4>
							{ status.homeSelected &&
							status.homeSelected.length > 0 ? (
								<ul>
									{ status.homeSelected.map( ( item ) => (
										<li key={ item.id }>
											{ item.title ||
												__(
													'(Untitled)',
													'buttercup'
												) }
										</li>
									) ) }
								</ul>
							) : (
								<p>
									{ __(
										'No home items selected.',
										'buttercup'
									) }
								</p>
							) }
						</div>
					</div>
				) }

				<div className="buttercup-homepage-feed-editor__preview">
					<div className="buttercup-homepage-feed-editor__preview-controls">
						<Button
							variant={
								previewViewport === 'desktop'
									? 'primary'
									: 'secondary'
							}
							onClick={ () => setPreviewViewport( 'desktop' ) }
						>
							{ __( 'Desktop', 'buttercup' ) }
						</Button>
						<Button
							variant={
								previewViewport === 'tablet'
									? 'primary'
									: 'secondary'
							}
							onClick={ () => setPreviewViewport( 'tablet' ) }
						>
							{ __( 'Tablet', 'buttercup' ) }
						</Button>
						<Button
							variant={
								previewViewport === 'mobile'
									? 'primary'
									: 'secondary'
							}
							onClick={ () => setPreviewViewport( 'mobile' ) }
						>
							{ __( 'Mobile', 'buttercup' ) }
						</Button>
					</div>
					<div
						className={ `buttercup-homepage-feed-editor__preview-frame is-${ previewViewport }` }
					>
						<div className="buttercup-homepage-feed-editor__preview-body">
							<ServerSideRender
								block="buttercup/homepage-feed"
								attributes={ {
									...attributes,
									mastTagSlug: normalizeSlug(
										mastTagSlug,
										'mast'
									),
									homeTagSlug: normalizeSlug(
										homeTagSlug,
										'home'
									),
								} }
								httpMethod="GET"
								skipBlockSupportAttributes
							/>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
