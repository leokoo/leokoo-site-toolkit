/**
 * Key Takeaways block — editor script.
 *
 * Server-side rendered: save() returns null and the PHP render seam
 * (Zehoro\Modules\KeyTakeaways::render_html) produces the front-end markup,
 * so the future connected/smart version can intercept the source without a
 * block deprecation. The editor is WYSIWYG — the same section/heading/list
 * markup and classes the front end emits.
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
	return HEADING_LEVELS.includes( level ) ? level : 2;
}

function Edit( { attributes, setAttributes } ) {
	const { heading, headingLevel, mode, items, text } = attributes;
	const blockProps = useBlockProps( { className: 'zehoro-key-takeaways' } );
	const isParagraph = mode === 'paragraph';
	const HeadingTag = `h${ normaliseLevel( headingLevel ) }`;

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="editor-ul"
						label={ __( 'Bulleted list', 'zehoro-toolkit' ) }
						isPressed={ ! isParagraph }
						onClick={ () => setAttributes( { mode: 'list' } ) }
					/>
					<ToolbarButton
						icon="editor-paragraph"
						label={ __( 'Paragraph (TL;DR)', 'zehoro-toolkit' ) }
						isPressed={ isParagraph }
						onClick={ () => setAttributes( { mode: 'paragraph' } ) }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={ __( 'Key Takeaways', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Format', 'zehoro-toolkit' ) }
						value={ isParagraph ? 'paragraph' : 'list' }
						options={ [
							{
								label: __( 'Bulleted list', 'zehoro-toolkit' ),
								value: 'list',
							},
							{
								label: __(
									'Short paragraph (TL;DR)',
									'zehoro-toolkit'
								),
								value: 'paragraph',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { mode: value } )
						}
						help={ __(
							'Best placed right after your introduction.',
							'zehoro-toolkit'
						) }
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

			<section { ...blockProps }>
				<RichText
					identifier="heading"
					tagName={ HeadingTag }
					className="zehoro-key-takeaways__title"
					value={ heading }
					allowedFormats={ [] }
					onChange={ ( value ) =>
						setAttributes( { heading: value } )
					}
					placeholder={ __( 'Key takeaways', 'zehoro-toolkit' ) }
				/>
				{ isParagraph ? (
					<RichText
						identifier="text"
						tagName="p"
						className="zehoro-key-takeaways__summary"
						value={ text }
						onChange={ ( value ) =>
							setAttributes( { text: value } )
						}
						placeholder={ __(
							'Write a short, answer-first summary a reader — or an AI — can quote…',
							'zehoro-toolkit'
						) }
					/>
				) : (
					// DEBT: `multiline` is soft-deprecated by WordPress but fully
					// functional through 6.9; it keeps the render seam attribute-
					// driven. Migrating to InnerBlocks would break the single seam,
					// so this is a deliberate deferral (see CHANGELOG known limits).
					<RichText
						identifier="items"
						tagName="ul"
						multiline="li"
						className="zehoro-key-takeaways__list"
						value={ items }
						onChange={ ( value ) =>
							setAttributes( { items: value } )
						}
						placeholder={ __(
							'Add a key takeaway…',
							'zehoro-toolkit'
						) }
					/>
				) }
			</section>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
