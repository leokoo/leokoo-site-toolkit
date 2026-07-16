/**
 * Pros & Cons block — editor script.
 *
 * Server-side rendered: save() returns null and the PHP render seam
 * (Zehoro\Modules\ProsCons::render_html) produces the front-end markup. One
 * consolidated block replaces the retired lkst/pros-cons container + standalone
 * lkst/pros and lkst/cons — the `show` toggle covers the single-list cases.
 */
import { __, sprintf } from '@wordpress/i18n';
import { registerBlockType, createBlock } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	InnerBlocks,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

const HEADING_LEVELS = [ 2, 3, 4 ];

function normaliseLevel( level ) {
	return HEADING_LEVELS.includes( level ) ? level : 3;
}

/**
 * One editable column (pros or cons).
 * @param root0
 * @param root0.variant
 * @param root0.title
 * @param root0.items
 * @param root0.HeadingTag
 * @param root0.onTitle
 * @param root0.onItems
 */
function Column( { variant, title, items, HeadingTag, onTitle, onItems } ) {
	const label =
		variant === 'pros'
			? __( 'Add a pro…', 'zehoro-toolkit' )
			: __( 'Add a con…', 'zehoro-toolkit' );
	return (
		<div
			className={ `zehoro-pros-cons__col zehoro-pros-cons__${ variant }` }
		>
			<RichText
				tagName={ HeadingTag }
				className="zehoro-pros-cons__title"
				value={ title }
				allowedFormats={ [] }
				onChange={ onTitle }
				placeholder={
					variant === 'pros'
						? __( 'Pros', 'zehoro-toolkit' )
						: __( 'Cons', 'zehoro-toolkit' )
				}
			/>
			{ /* DEBT: `multiline` is soft-deprecated but functional through WP 6.9;
			     it keeps the render seam attribute-driven (see CHANGELOG). */ }
			<RichText
				tagName="ul"
				multiline="li"
				className="zehoro-pros-cons__list"
				value={ items }
				onChange={ onItems }
				placeholder={ label }
			/>
		</div>
	);
}

function Edit( { attributes, setAttributes } ) {
	const { prosTitle, consTitle, pros, cons, show, headingLevel } = attributes;
	const blockProps = useBlockProps( {
		className: `zehoro-pros-cons zehoro-pros-cons--${ show }`,
	} );
	const HeadingTag = `h${ normaliseLevel( headingLevel ) }`;

	const showPros = show !== 'cons';
	const showCons = show !== 'pros';

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						label={ __( 'Pros and cons', 'zehoro-toolkit' ) }
						isPressed={ show === 'both' }
						onClick={ () => setAttributes( { show: 'both' } ) }
					>
						{ __( 'Both', 'zehoro-toolkit' ) }
					</ToolbarButton>
					<ToolbarButton
						label={ __( 'Pros only', 'zehoro-toolkit' ) }
						isPressed={ show === 'pros' }
						onClick={ () => setAttributes( { show: 'pros' } ) }
					>
						{ __( 'Pros', 'zehoro-toolkit' ) }
					</ToolbarButton>
					<ToolbarButton
						label={ __( 'Cons only', 'zehoro-toolkit' ) }
						isPressed={ show === 'cons' }
						onClick={ () => setAttributes( { show: 'cons' } ) }
					>
						{ __( 'Cons', 'zehoro-toolkit' ) }
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={ __( 'Pros & Cons', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Show', 'zehoro-toolkit' ) }
						value={ show }
						options={ [
							{
								label: __( 'Pros and cons', 'zehoro-toolkit' ),
								value: 'both',
							},
							{
								label: __( 'Pros only', 'zehoro-toolkit' ),
								value: 'pros',
							},
							{
								label: __( 'Cons only', 'zehoro-toolkit' ),
								value: 'cons',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { show: value } )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Heading level', 'zehoro-toolkit' ) }
						value={ String( normaliseLevel( headingLevel ) ) }
						options={ HEADING_LEVELS.map( ( level ) => ( {
							label: `H${ level }`,
							value: String( level ),
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( {
								headingLevel: parseInt( value, 10 ),
							} )
						}
						help={ __(
							'Keep it below your post title (H1).',
							'zehoro-toolkit'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ showPros && (
					<Column
						variant="pros"
						title={ prosTitle }
						items={ pros }
						HeadingTag={ HeadingTag }
						onTitle={ ( value ) =>
							setAttributes( { prosTitle: value } )
						}
						onItems={ ( value ) =>
							setAttributes( { pros: value } )
						}
					/>
				) }
				{ showCons && (
					<Column
						variant="cons"
						title={ consTitle }
						items={ cons }
						HeadingTag={ HeadingTag }
						onTitle={ ( value ) =>
							setAttributes( { consTitle: value } )
						}
						onItems={ ( value ) =>
							setAttributes( { cons: value } )
						}
					/>
				) }
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );

/**
 * Legacy editor bridges for the retired lkst/pros, lkst/cons and lkst/pros-cons
 * blocks. Same purpose as the Key Takeaways bridge: keep old content VALID in
 * Gutenberg (no invalid-content warning, no Block-Recovery loss) and offer a
 * one-click transform to the consolidated zehoro/pros-cons. Each reproduces the
 * retired block's exact saved markup (verified against git main). Hidden from
 * the inserter — legacy-only.
 * @param variant
 * @param icon
 * @param label
 */
function legacyListSave( variant, icon, label ) {
	return function ( { attributes } ) {
		const blockProps = useBlockProps.save( {
			className: `lkst-${ variant }`,
		} );
		return (
			<div { ...blockProps }>
				<h4 className={ `lkst-${ variant }-heading` }>
					<span className={ `lkst-${ variant }-icon` }>{ icon }</span>{ ' ' }
					{ label }
				</h4>
				<RichText.Content
					tagName="ul"
					className={ `lkst-${ variant }-list` }
					value={ attributes.content }
				/>
			</div>
		);
	};
}

function legacyListEdit( variant, icon, label ) {
	return function ( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: `lkst-${ variant }` } );
		return (
			<div { ...blockProps }>
				<h4 className={ `lkst-${ variant }-heading` }>
					<span className={ `lkst-${ variant }-icon` }>{ icon }</span>{ ' ' }
					{ label }
				</h4>
				<RichText
					tagName="ul"
					multiline="li"
					className={ `lkst-${ variant }-list` }
					value={ attributes.content }
					onChange={ ( content ) => setAttributes( { content } ) }
				/>
			</div>
		);
	};
}

function registerLegacyList( name, variant, icon, label ) {
	registerBlockType( name, {
		apiVersion: 3,
		title: sprintf(
			/* translators: %s: the block label, e.g. "Pros". */
			__( '%s (legacy)', 'zehoro-toolkit' ),
			label
		),
		category: 'zehoro-toolkit',
		icon: variant === 'pros' ? 'thumbs-up' : 'thumbs-down',
		description: __(
			'Retired block — transform to Pros & Cons.',
			'zehoro-toolkit'
		),
		supports: { inserter: false, html: false },
		attributes: {
			content: {
				type: 'string',
				source: 'html',
				selector: `.lkst-${ variant }-list`,
			},
		},
		transforms: {
			to: [
				{
					type: 'block',
					blocks: [ 'zehoro/pros-cons' ],
					transform: ( { content } ) =>
						createBlock( 'zehoro/pros-cons', {
							show: variant,
							[ variant ]: content || '',
							[ variant === 'pros' ? 'prosTitle' : 'consTitle' ]:
								label,
						} ),
				},
			],
		},
		edit: legacyListEdit( variant, icon, label ),
		save: legacyListSave( variant, icon, label ),
	} );
}

registerLegacyList( 'lkst/pros', 'pros', '✅', __( 'Pros', 'zehoro-toolkit' ) );
registerLegacyList( 'lkst/cons', 'cons', '❌', __( 'Cons', 'zehoro-toolkit' ) );

const LEGACY_WRAPPER_CLASS = 'lkst-pros-cons-wrapper lkst-editorial-block';

registerBlockType( 'lkst/pros-cons', {
	apiVersion: 3,
	title: __( 'Pros & Cons (legacy)', 'zehoro-toolkit' ),
	category: 'zehoro-toolkit',
	icon: 'editor-ul',
	description: __(
		'Retired container — transform to Pros & Cons.',
		'zehoro-toolkit'
	),
	supports: { inserter: false, html: false },
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ 'zehoro/pros-cons' ],
				transform: ( attributes, innerBlocks ) => {
					const pros = innerBlocks.find(
						( b ) => b.name === 'lkst/pros'
					);
					const cons = innerBlocks.find(
						( b ) => b.name === 'lkst/cons'
					);
					return createBlock( 'zehoro/pros-cons', {
						show: 'both',
						pros: pros ? pros.attributes.content || '' : '',
						cons: cons ? cons.attributes.content || '' : '',
					} );
				},
			},
		],
	},
	edit: () => {
		const blockProps = useBlockProps( { className: LEGACY_WRAPPER_CLASS } );
		return (
			<div { ...blockProps }>
				<InnerBlocks allowedBlocks={ [ 'lkst/pros', 'lkst/cons' ] } />
			</div>
		);
	},
	save: () => {
		const blockProps = useBlockProps.save( {
			className: LEGACY_WRAPPER_CLASS,
		} );
		return (
			<div { ...blockProps }>
				<InnerBlocks.Content />
			</div>
		);
	},
} );
