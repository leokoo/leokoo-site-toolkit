/**
 * Pros & Cons block — editor script.
 *
 * Server-side rendered: save() returns null and the PHP render seam
 * (Zehoro\Modules\ProsCons::render_html) produces the front-end markup. One
 * consolidated block replaces the retired lkst/pros-cons container + standalone
 * lkst/pros and lkst/cons — the `show` toggle covers the single-list cases.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
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

/** One editable column (pros or cons). */
function Column( { variant, title, items, HeadingTag, onTitle, onItems } ) {
	const label =
		variant === 'pros'
			? __( 'Add a pro…', 'zehoro-toolkit' )
			: __( 'Add a con…', 'zehoro-toolkit' );
	return (
		<div className={ `zehoro-pros-cons__col zehoro-pros-cons__${ variant }` }>
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
				<PanelBody title={ __( 'Pros & Cons', 'zehoro-toolkit' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Show', 'zehoro-toolkit' ) }
						value={ show }
						options={ [
							{ label: __( 'Pros and cons', 'zehoro-toolkit' ), value: 'both' },
							{ label: __( 'Pros only', 'zehoro-toolkit' ), value: 'pros' },
							{ label: __( 'Cons only', 'zehoro-toolkit' ), value: 'cons' },
						] }
						onChange={ ( value ) => setAttributes( { show: value } ) }
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
						onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
						help={ __( 'Keep it below your post title (H1).', 'zehoro-toolkit' ) }
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
						onTitle={ ( value ) => setAttributes( { prosTitle: value } ) }
						onItems={ ( value ) => setAttributes( { pros: value } ) }
					/>
				) }
				{ showCons && (
					<Column
						variant="cons"
						title={ consTitle }
						items={ cons }
						HeadingTag={ HeadingTag }
						onTitle={ ( value ) => setAttributes( { consTitle: value } ) }
						onItems={ ( value ) => setAttributes( { cons: value } ) }
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
