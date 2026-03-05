import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, Notice, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

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
	const slug = String( value || '' )
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9-_]/g, '' );

	return slug || fallback;
}

export default function Edit( { attributes, setAttributes } ) {
	const { ctaLabel, homeTagSlug, mastTagSlug } = attributes;

	const allBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);

	const [ status, setStatus ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ errorMessage, setErrorMessage ] = useState( '' );

	const blockCount = countHomepageFeedBlocks( allBlocks );
	const countsMessage = `${ __( 'Found', 'buttercup' ) } ${ Number(
		status?.mastCount || 0
	) } ${ __( 'mast item(s) and', 'buttercup' ) } ${ Number(
		status?.homeCount || 0
	) } ${ __( 'home item(s).', 'buttercup' ) }`;

	useEffect( () => {
		let cancelled = false;
		const mast = normalizeSlug( mastTagSlug, 'mast' );
		const home = normalizeSlug( homeTagSlug, 'home' );

		setIsLoading( true );
		setErrorMessage( '' );

		const query = new URLSearchParams( {
			mastTagSlug: mast,
			homeTagSlug: home,
		} );

		apiFetch( {
			path: `/buttercup/v1/homepage-feed-status?${ query.toString() }`,
		} )
			.then( ( data ) => {
				if ( cancelled ) {
					return;
				}
				setStatus( data );
				setIsLoading( false );
			} )
			.catch( ( error ) => {
				if ( cancelled ) {
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

		return () => {
			cancelled = true;
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
					<TextControl
						label={ __( 'Mast Tag Slug', 'buttercup' ) }
						value={ mastTagSlug }
						onChange={ ( value ) =>
							setAttributes( { mastTagSlug: value } )
						}
						help={ __( 'Default: mast', 'buttercup' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Home Tag Slug', 'buttercup' ) }
						value={ homeTagSlug }
						onChange={ ( value ) =>
							setAttributes( { homeTagSlug: value } )
						}
						help={ __( 'Default: home', 'buttercup' ) }
						__nextHasNoMarginBottom
					/>
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
			</div>
		</>
	);
}
