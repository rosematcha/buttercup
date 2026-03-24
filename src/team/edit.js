import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	TextareaControl,
	ColorPalette,
	Button,
} from '@wordpress/components';
import { useSelect, useDispatch, resolveSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { createBlock } from '@wordpress/blocks';
import { useEffect, useState, useRef, useMemo } from '@wordpress/element';
import {
	buildSrcSetFromMedia,
	pickBestCandidate,
} from '../shared/media-image-utils';
import {
	collectAllTeamMembers,
	collectTeamBlocks,
	getTeamMembersFromBlock,
	stripHtmlText,
} from '../shared/block-tree-utils';
import { buildMemberSlugAssignments } from '../shared/member-slug-utils';

const ALLOWED_BLOCKS = [ 'buttercup/team-member' ];

const TEMPLATE = [
	[ 'buttercup/team-member' ],
	[ 'buttercup/team-member' ],
	[ 'buttercup/team-member' ],
];

const TEAM_LAYOUT_DEFAULTS = {
	minCardWidth: 230,
	columnGap: 40,
	rowGap: 20,
	textAlign: 'center',
};

function AddMemberButton( { clientId } ) {
	const { insertBlock } = useDispatch( blockEditorStore );
	return (
		<div className="buttercup-team__add-member">
			<button
				className="buttercup-team__add-member-btn"
				onClick={ () => {
					const block = createBlock( 'buttercup/team-member' );
					insertBlock( block, undefined, clientId );
				} }
				aria-label={ __( 'Add team member', 'buttercup' ) }
			>
				<span className="buttercup-team__add-member-icon">+</span>
				<span className="buttercup-team__add-member-label">
					{ __( 'Add Member', 'buttercup' ) }
				</span>
			</button>
		</div>
	);
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		imageShape,
		imageSize,
		squircleRadius,
		cardBackground,
		cardBorderRadius,
		cardPadding,
		cardShadow,
		cardHoverEffect,
		minCardWidth,
		columnGap,
		rowGap,
		bioLines,
		readMoreLabel,
		readLessLabel,
		textAlign,
		showPronouns,
		showBio,
		showSocial,
		showSocialGrid,
		showSocialMemberPage,
		enableMemberPages,
		memberBackLabel,
		memberPageIntro,
		memberPageCardBackground,
		memberPageCardRadius,
		memberPageCardPadding,
		memberPageCardShadow,
		memberPageGap,
		memberPageLeftWidth,
		socialIconSize,
		socialStyle,
		socialLabelStyle,
	} = attributes;

	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);
	const allBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);
	const teamBlocks = useMemo(
		() => collectTeamBlocks( allBlocks ),
		[ allBlocks ]
	);
	const allTeamMembers = useMemo(
		() => collectAllTeamMembers( allBlocks ),
		[ allBlocks ]
	);

	const { replaceInnerBlocks, updateBlockAttributes } =
		useDispatch( blockEditorStore );
	const [ showAdvancedPages, setShowAdvancedPages ] = useState( false );
	const [ isRefreshingImages, setIsRefreshingImages ] = useState( false );
	const [ refreshImagesNote, setRefreshImagesNote ] = useState( '' );
	const syncSignatureRef = useRef( '' );

	const memberCount = innerBlocks.length;

	useEffect( () => {
		if ( ! teamBlocks.length ) {
			return;
		}

		const signatureParts = [];
		teamBlocks.forEach( ( teamBlock ) => {
			const teamEnabled =
				teamBlock.attributes?.enableMemberPages !== false;
			signatureParts.push(
				`${ teamBlock.clientId }:${ teamEnabled ? '1' : '0' }`
			);

			getTeamMembersFromBlock( teamBlock ).forEach( ( memberBlock ) => {
				if ( memberBlock.name !== 'buttercup/team-member' ) {
					return;
				}
				const rawName = stripHtmlText(
					memberBlock.attributes?.name || ''
				);
				const memberDisabled =
					memberBlock.attributes?.disableMemberPage === true;
				signatureParts.push(
					`${ memberBlock.clientId }:${ rawName.toLowerCase() }:${
						memberDisabled ? '1' : '0'
					}`
				);
			} );
		} );

		const signature = signatureParts.join( '|' );
		if ( signature === syncSignatureRef.current ) {
			return;
		}
		syncSignatureRef.current = signature;

		const enabledMembers = [];
		const disabledMembers = [];
		teamBlocks.forEach( ( teamBlock ) => {
			const teamEnabled =
				teamBlock.attributes?.enableMemberPages !== false;
			const members = getTeamMembersFromBlock( teamBlock );

			members.forEach( ( member ) => {
				const memberDisabled =
					member.attributes?.disableMemberPage === true;
				if ( teamEnabled && ! memberDisabled ) {
					enabledMembers.push( member );
				} else {
					disabledMembers.push( member );
				}
			} );
		} );

		const slugAssignments = buildMemberSlugAssignments(
			enabledMembers.map( ( block ) => ( {
				clientId: block.clientId,
				name: block.attributes?.name || '',
				memberSlug: block.attributes?.memberSlug || '',
			} ) )
		);

		enabledMembers.forEach( ( block ) => {
			const existingSlug = ( block.attributes?.memberSlug || '' ).trim();
			const nextAttrs = {};

			if ( ! existingSlug && slugAssignments[ block.clientId ] ) {
				nextAttrs.memberSlug = slugAssignments[ block.clientId ];
			}

			if ( block.attributes?.memberPagesEnabled !== true ) {
				nextAttrs.memberPagesEnabled = true;
			}

			if ( Object.keys( nextAttrs ).length > 0 ) {
				updateBlockAttributes( block.clientId, nextAttrs );
			}
		} );

		disabledMembers.forEach( ( block ) => {
			const nextAttrs = {};
			if ( block.attributes?.memberPagesEnabled !== false ) {
				nextAttrs.memberPagesEnabled = false;
			}
			if ( Object.keys( nextAttrs ).length > 0 ) {
				updateBlockAttributes( block.clientId, nextAttrs );
			}
		} );
	}, [ teamBlocks, updateBlockAttributes ] );

	const sortBlocks = ( key ) => {
		const sorted = [ ...innerBlocks ].sort( ( a, b ) => {
			const valA = stripHtmlText(
				a.attributes[ key ] || ''
			).toLowerCase();
			const valB = stripHtmlText(
				b.attributes[ key ] || ''
			).toLowerCase();
			return valA.localeCompare( valB );
		} );
		replaceInnerBlocks( clientId, sorted, false );
	};

	const teamMembers = innerBlocks.filter(
		( block ) => block.name === 'buttercup/team-member'
	);

	const regenerateTeamSlugs = () => {
		teamMembers.forEach( ( member ) => {
			updateBlockAttributes( member.clientId, { memberSlug: '' } );
		} );
	};

	const setTeamMemberPages = ( enabled ) => {
		if ( enabled && ! enableMemberPages ) {
			setAttributes( { enableMemberPages: true } );
		}

		teamMembers.forEach( ( member ) => {
			updateBlockAttributes( member.clientId, {
				disableMemberPage: ! enabled,
				memberPagesEnabled: enabled,
			} );
		} );
	};

	const resetTeamLayout = () => {
		setAttributes( TEAM_LAYOUT_DEFAULTS );
	};

	const refreshMemberImages = async () => {
		if ( isRefreshingImages ) {
			return;
		}
		setIsRefreshingImages( true );
		setRefreshImagesNote( '' );

		const members = teamMembers;
		let updated = 0;
		let missing = 0;
		const target = imageSize || 120;
		const membersWithImage = members.filter(
			( member ) => !! member.attributes?.profileImageId
		);
		const uniqueImageIds = Array.from(
			new Set(
				membersWithImage.map( ( member ) =>
					Number( member.attributes?.profileImageId || 0 )
				)
			)
		).filter( Boolean );

		const mediaEntries = await Promise.all(
			uniqueImageIds.map( async ( imageId ) => [
				imageId,
				await resolveSelect( 'core' ).getMedia( imageId ),
			] )
		);
		const mediaById = new Map( mediaEntries );

		const squareEntries = await Promise.all(
			uniqueImageIds.map( async ( imageId ) => {
				try {
					const square = await apiFetch( {
						path: '/buttercup/v1/square-image',
						method: 'POST',
						data: { id: imageId },
					} );
					return [ imageId, square ];
				} catch ( e ) {
					return [ imageId, null ];
				}
			} )
		);
		const squareById = new Map( squareEntries );

		membersWithImage.forEach( ( member ) => {
			const imageId = Number( member.attributes?.profileImageId || 0 );
			const mediaItem = mediaById.get( imageId );
			if ( ! mediaItem ) {
				missing += 1;
				return;
			}

			const square = squareById.get( imageId );
			const alt = mediaItem?.alt_text || mediaItem?.alt || '';
			const sizes = `${ target }px`;

			if ( square?.url ) {
				updateBlockAttributes( member.clientId, {
					profileImageUrl: square.url,
					profileImageAlt: alt,
					profileImageSrcSet: '',
					profileImageSizes: sizes,
					profileImageWidth: square.width || 600,
					profileImageHeight: square.height || 600,
					profileImageSource: 'square-600',
				} );
				updated += 1;
				return;
			}

			const best = pickBestCandidate( mediaItem, target );
			const srcSet = buildSrcSetFromMedia( mediaItem );
			updateBlockAttributes( member.clientId, {
				profileImageUrl:
					best.url || mediaItem.url || mediaItem.source_url || '',
				profileImageAlt: alt,
				profileImageSrcSet: srcSet,
				profileImageSizes: sizes,
				profileImageWidth:
					best.width || mediaItem.media_details?.width || 0,
				profileImageHeight:
					best.height || mediaItem.media_details?.height || 0,
				profileImageSource: 'auto',
			} );
			updated += 1;
		} );

		if ( updated || missing ) {
			const note = missing
				? __(
						'Updated images for this block. Some images are still loading from the Media Library.',
						'buttercup'
				  )
				: __( 'Updated images for this block.', 'buttercup' );
			setRefreshImagesNote( note );
		} else {
			setRefreshImagesNote(
				__( 'No images found to refresh in this block.', 'buttercup' )
			);
		}

		setIsRefreshingImages( false );
	};

	const fallbackShowSocial =
		typeof showSocial === 'boolean' ? showSocial : true;
	const resolvedShowSocialGrid =
		typeof showSocialGrid === 'boolean'
			? showSocialGrid
			: fallbackShowSocial;
	const resolvedShowSocialMemberPage =
		typeof showSocialMemberPage === 'boolean'
			? showSocialMemberPage
			: fallbackShowSocial;

	const blockProps = useBlockProps( {
		className: [
			'buttercup-team',
			`buttercup-team--${ imageShape }`,
			`buttercup-team--align-${ textAlign }`,
			`buttercup-team--social-${ socialStyle }`,
			`buttercup-team--social-label-${ socialLabelStyle }`,
			cardShadow !== 'none' && `buttercup-team--shadow-${ cardShadow }`,
			cardHoverEffect !== 'none' &&
				`buttercup-team--hover-${ cardHoverEffect }`,
			! showPronouns && 'buttercup-team--hide-pronouns',
			! resolvedShowSocialGrid && 'buttercup-team--hide-social',
		].join( ' ' ),
		style: {
			'--buttercup-min-card': `${ minCardWidth }px`,
			'--buttercup-col-gap': `${ columnGap }px`,
			'--buttercup-row-gap': `${ rowGap }px`,
			'--buttercup-img-size': `${ imageSize }px`,
			'--buttercup-squircle-radius': `${ squircleRadius }%`,
			'--buttercup-card-bg': cardBackground || 'transparent',
			'--buttercup-card-radius': `${ cardBorderRadius }px`,
			'--buttercup-card-padding': `${ cardPadding }px`,
			'--buttercup-bio-lines': bioLines,
			'--buttercup-social-size': `${ socialIconSize }px`,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grid Layout', 'buttercup' ) }>
					<p
						style={ {
							fontSize: 13,
							color: '#757575',
							margin: '0 0 12px',
						} }
					>
						{ memberCount }{ ' ' }
						{ memberCount === 1
							? __( 'member', 'buttercup' )
							: __( 'members', 'buttercup' ) }
					</p>
					<RangeControl
						label={ __( 'Min card width (px)', 'buttercup' ) }
						value={ minCardWidth }
						onChange={ ( v ) =>
							setAttributes( { minCardWidth: v } )
						}
						min={ 120 }
						max={ 400 }
						step={ 10 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Column gap (px)', 'buttercup' ) }
						value={ columnGap }
						onChange={ ( v ) => setAttributes( { columnGap: v } ) }
						min={ 0 }
						max={ 80 }
						step={ 4 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Row gap (px)', 'buttercup' ) }
						value={ rowGap }
						onChange={ ( v ) => setAttributes( { rowGap: v } ) }
						min={ 0 }
						max={ 80 }
						step={ 4 }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Text Alignment', 'buttercup' ) }
						value={ textAlign }
						options={ [
							{
								label: __( 'Center', 'buttercup' ),
								value: 'center',
							},
							{ label: __( 'Left', 'buttercup' ), value: 'left' },
						] }
						onChange={ ( v ) => setAttributes( { textAlign: v } ) }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ resetTeamLayout }
						style={ { marginTop: 12 } }
					>
						{ __( 'Reset Layout Defaults', 'buttercup' ) }
					</Button>
				</PanelBody>
				<PanelBody
					title={ __( 'Content', 'buttercup' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Show Pronouns', 'buttercup' ) }
						checked={ showPronouns }
						onChange={ ( v ) =>
							setAttributes( { showPronouns: v } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Short Bio', 'buttercup' ) }
						checked={ showBio }
						onChange={ ( v ) => setAttributes( { showBio: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Social Links on Grid', 'buttercup' ) }
						checked={ resolvedShowSocialGrid }
						onChange={ ( v ) =>
							setAttributes( { showSocialGrid: v } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Show Social Links on Member Pages',
							'buttercup'
						) }
						checked={ resolvedShowSocialMemberPage }
						onChange={ ( v ) =>
							setAttributes( { showSocialMemberPage: v } )
						}
						disabled={ ! enableMemberPages }
						help={
							! enableMemberPages
								? __(
										'Enable member pages to show social links on profiles.',
										'buttercup'
								  )
								: undefined
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Images', 'buttercup' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Image Shape', 'buttercup' ) }
						value={ imageShape }
						options={ [
							{
								label: __( 'Circle', 'buttercup' ),
								value: 'circle',
							},
							{
								label: __( 'Square', 'buttercup' ),
								value: 'square',
							},
							{
								label: __( 'Squircle', 'buttercup' ),
								value: 'squircle',
							},
						] }
						onChange={ ( v ) => setAttributes( { imageShape: v } ) }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Image Size (px)', 'buttercup' ) }
						value={ imageSize }
						onChange={ ( v ) => setAttributes( { imageSize: v } ) }
						min={ 40 }
						max={ 600 }
						step={ 8 }
						__nextHasNoMarginBottom
					/>
					{ imageShape === 'squircle' && (
						<RangeControl
							label={ __( 'Squircle Radius (%)', 'buttercup' ) }
							value={ squircleRadius }
							onChange={ ( v ) =>
								setAttributes( { squircleRadius: v } )
							}
							min={ 8 }
							max={ 45 }
							step={ 1 }
							__nextHasNoMarginBottom
						/>
					) }
					<div style={ { marginTop: 8 } }>
						<Button
							variant="secondary"
							onClick={ refreshMemberImages }
							disabled={ isRefreshingImages }
						>
							{ isRefreshingImages
								? __( 'Refreshing Images…', 'buttercup' )
								: __( 'Refresh Member Images', 'buttercup' ) }
						</Button>
						<p
							style={ {
								marginTop: 6,
								fontSize: 12,
								color: '#757575',
							} }
						>
							{ refreshImagesNote ||
								__(
									'Rebuilds image sizes for all members in this block.',
									'buttercup'
								) }
						</p>
					</div>
				</PanelBody>
				<PanelBody
					title={ __( 'Card Style', 'buttercup' ) }
					initialOpen={ false }
				>
					<p
						style={ {
							fontSize: 13,
							color: '#757575',
							marginTop: 0,
						} }
					>
						{ __(
							'Style each member card without editing individual blocks.',
							'buttercup'
						) }
					</p>
					<div style={ { marginBottom: 12 } }>
						<div style={ { fontSize: 12, marginBottom: 6 } }>
							{ __( 'Background', 'buttercup' ) }
						</div>
						<ColorPalette
							value={ cardBackground }
							onChange={ ( v ) =>
								setAttributes( { cardBackground: v || '' } )
							}
						/>
					</div>
					<RangeControl
						label={ __( 'Border Radius (px)', 'buttercup' ) }
						value={ cardBorderRadius }
						onChange={ ( v ) =>
							setAttributes( { cardBorderRadius: v } )
						}
						min={ 0 }
						max={ 40 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Card Padding (px)', 'buttercup' ) }
						value={ cardPadding }
						onChange={ ( v ) =>
							setAttributes( { cardPadding: v } )
						}
						min={ 0 }
						max={ 48 }
						step={ 2 }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Shadow', 'buttercup' ) }
						value={ cardShadow }
						options={ [
							{ label: __( 'None', 'buttercup' ), value: 'none' },
							{ label: __( 'Soft', 'buttercup' ), value: 'soft' },
							{
								label: __( 'Medium', 'buttercup' ),
								value: 'medium',
							},
							{
								label: __( 'Strong', 'buttercup' ),
								value: 'strong',
							},
						] }
						onChange={ ( v ) => setAttributes( { cardShadow: v } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Hover Animation', 'buttercup' ) }
						value={ cardHoverEffect }
						options={ [
							{ label: __( 'None', 'buttercup' ), value: 'none' },
							{ label: __( 'Lift', 'buttercup' ), value: 'lift' },
							{ label: __( 'Glow', 'buttercup' ), value: 'glow' },
							{
								label: __( 'Lift + Glow', 'buttercup' ),
								value: 'lift-glow',
							},
							{
								label: __( 'Scale', 'buttercup' ),
								value: 'scale',
							},
							{ label: __( 'Tilt', 'buttercup' ), value: 'tilt' },
							{
								label: __( 'Outline', 'buttercup' ),
								value: 'outline',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { cardHoverEffect: v } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Short Bio Preview', 'buttercup' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Short Bio Lines', 'buttercup' ) }
						value={ bioLines }
						onChange={ ( v ) => setAttributes( { bioLines: v } ) }
						min={ 1 }
						max={ 8 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Short Bio Read More Label', 'buttercup' ) }
						value={ readMoreLabel }
						onChange={ ( v ) =>
							setAttributes( { readMoreLabel: v } )
						}
						placeholder={ __( 'Read more', 'buttercup' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Short Bio Read Less Label', 'buttercup' ) }
						value={ readLessLabel }
						onChange={ ( v ) =>
							setAttributes( { readLessLabel: v } )
						}
						placeholder={ __( 'Read less', 'buttercup' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Social Links', 'buttercup' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Icon Size (px)', 'buttercup' ) }
						value={ socialIconSize }
						onChange={ ( v ) =>
							setAttributes( { socialIconSize: v } )
						}
						min={ 16 }
						max={ 48 }
						step={ 2 }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Icon Shape', 'buttercup' ) }
						value={ socialStyle }
						options={ [
							{
								label: __( 'Circle', 'buttercup' ),
								value: 'circle',
							},
							{
								label: __( 'Rounded', 'buttercup' ),
								value: 'rounded',
							},
							{
								label: __( 'Square', 'buttercup' ),
								value: 'square',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { socialStyle: v } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Label Style', 'buttercup' ) }
						value={ socialLabelStyle }
						options={ [
							{
								label: __( 'Icon Only', 'buttercup' ),
								value: 'icon-only',
							},
							{
								label: __( 'Icon + Text', 'buttercup' ),
								value: 'icon-text',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { socialLabelStyle: v } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Member Pages', 'buttercup' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Enable Individual Pages', 'buttercup' ) }
						checked={ enableMemberPages }
						onChange={ ( v ) =>
							setAttributes( { enableMemberPages: v } )
						}
						help={ __(
							'Creates a dedicated page for each team member.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					<div
						style={ {
							marginTop: 12,
							paddingTop: 12,
							borderTop: '1px solid #eee',
						} }
					>
						<p
							style={ {
								fontSize: 13,
								fontWeight: 600,
								margin: '0 0 8px',
							} }
						>
							{ __(
								'Bulk Actions (This Team Only)',
								'buttercup'
							) }
						</p>
						<div
							style={ {
								display: 'flex',
								flexDirection: 'column',
								gap: 8,
							} }
						>
							<Button
								variant="secondary"
								onClick={ regenerateTeamSlugs }
								disabled={ teamMembers.length === 0 }
							>
								{ __(
									'Regenerate Slugs (This Team)',
									'buttercup'
								) }
							</Button>
							<Button
								variant="secondary"
								onClick={ () => setTeamMemberPages( true ) }
								disabled={ teamMembers.length === 0 }
							>
								{ __(
									'Enable Member Pages (This Team)',
									'buttercup'
								) }
							</Button>
							<Button
								variant="secondary"
								onClick={ () => setTeamMemberPages( false ) }
								disabled={ teamMembers.length === 0 }
							>
								{ __(
									'Disable Member Pages (This Team)',
									'buttercup'
								) }
							</Button>
						</div>
					</div>
					{ enableMemberPages && (
						<p
							style={ {
								marginTop: 8,
								fontSize: 12,
								color: '#757575',
							} }
						>
							{ __(
								'Save the page to refresh member URLs.',
								'buttercup'
							) }
						</p>
					) }
					{ enableMemberPages && (
						<>
							<TextControl
								label={ __( 'Back Link Label', 'buttercup' ) }
								value={ memberBackLabel }
								onChange={ ( v ) =>
									setAttributes( { memberBackLabel: v } )
								}
								placeholder={ __(
									'Back to People',
									'buttercup'
								) }
								__nextHasNoMarginBottom
							/>
							<TextareaControl
								label={ __( 'Member Page Intro', 'buttercup' ) }
								value={ memberPageIntro }
								onChange={ ( v ) =>
									setAttributes( { memberPageIntro: v } )
								}
								help={ __(
									'Shown above the long bio on every member page.',
									'buttercup'
								) }
							/>
							<div
								style={ {
									marginTop: 16,
									paddingTop: 12,
									borderTop: '1px solid #eee',
								} }
							>
								<p
									style={ {
										fontSize: 13,
										fontWeight: 600,
										margin: '0 0 8px',
									} }
								>
									{ __( 'Member Page Style', 'buttercup' ) }
								</p>
								<div style={ { marginBottom: 12 } }>
									<div
										style={ {
											fontSize: 12,
											marginBottom: 6,
										} }
									>
										{ __( 'Card Background', 'buttercup' ) }
									</div>
									<ColorPalette
										value={ memberPageCardBackground }
										onChange={ ( v ) =>
											setAttributes( {
												memberPageCardBackground:
													v || '',
											} )
										}
									/>
								</div>
								<RangeControl
									label={ __(
										'Card Radius (px)',
										'buttercup'
									) }
									value={ memberPageCardRadius }
									onChange={ ( v ) =>
										setAttributes( {
											memberPageCardRadius: v,
										} )
									}
									min={ 0 }
									max={ 32 }
									step={ 1 }
									__nextHasNoMarginBottom
								/>
								<RangeControl
									label={ __(
										'Card Padding (px)',
										'buttercup'
									) }
									value={ memberPageCardPadding }
									onChange={ ( v ) =>
										setAttributes( {
											memberPageCardPadding: v,
										} )
									}
									min={ 12 }
									max={ 40 }
									step={ 2 }
									__nextHasNoMarginBottom
								/>
								<SelectControl
									label={ __( 'Card Shadow', 'buttercup' ) }
									value={ memberPageCardShadow }
									options={ [
										{
											label: __( 'None', 'buttercup' ),
											value: 'none',
										},
										{
											label: __( 'Soft', 'buttercup' ),
											value: 'soft',
										},
										{
											label: __( 'Medium', 'buttercup' ),
											value: 'medium',
										},
										{
											label: __( 'Strong', 'buttercup' ),
											value: 'strong',
										},
									] }
									onChange={ ( v ) =>
										setAttributes( {
											memberPageCardShadow: v,
										} )
									}
									__nextHasNoMarginBottom
								/>
								<RangeControl
									label={ __(
										'Left Column Width (px)',
										'buttercup'
									) }
									value={ memberPageLeftWidth }
									onChange={ ( v ) =>
										setAttributes( {
											memberPageLeftWidth: v,
										} )
									}
									min={ 220 }
									max={ 360 }
									step={ 4 }
									__nextHasNoMarginBottom
								/>
								<RangeControl
									label={ __(
										'Column Gap (px)',
										'buttercup'
									) }
									value={ memberPageGap }
									onChange={ ( v ) =>
										setAttributes( { memberPageGap: v } )
									}
									min={ 16 }
									max={ 64 }
									step={ 2 }
									__nextHasNoMarginBottom
								/>
							</div>
							<ToggleControl
								label={ __(
									'Advanced Member Page Tools',
									'buttercup'
								) }
								checked={ showAdvancedPages }
								onChange={ ( v ) => setShowAdvancedPages( v ) }
								__nextHasNoMarginBottom
							/>
							{ showAdvancedPages && (
								<>
									<Button
										variant="secondary"
										onClick={ () => {
											allTeamMembers.forEach(
												( member ) => {
													updateBlockAttributes(
														member.clientId,
														{
															memberSlug: '',
														}
													);
												}
											);
										} }
									>
										{ __(
											'Reset All Member Slugs',
											'buttercup'
										) }
									</Button>
									<p
										style={ {
											marginTop: 8,
											fontSize: 12,
											color: '#757575',
										} }
									>
										{ __(
											'This regenerates URLs on the next save.',
											'buttercup'
										) }
									</p>
								</>
							) }
						</>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Sort Members', 'buttercup' ) }
					initialOpen={ false }
				>
					<p
						style={ {
							fontSize: 13,
							color: '#757575',
							marginTop: 0,
						} }
					>
						{ __(
							'Reorder all members at once, or drag individual cards.',
							'buttercup'
						) }
					</p>
					<div
						style={ {
							display: 'flex',
							flexDirection: 'column',
							gap: 8,
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => sortBlocks( 'name' ) }
						>
							{ __( 'Sort A→Z by Name', 'buttercup' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => sortBlocks( 'position' ) }
						>
							{ __( 'Sort A→Z by Position', 'buttercup' ) }
						</Button>
					</div>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					template={ TEMPLATE }
					orientation="horizontal"
					renderAppender={ () => (
						<AddMemberButton clientId={ clientId } />
					) }
				/>
			</div>
		</>
	);
}
