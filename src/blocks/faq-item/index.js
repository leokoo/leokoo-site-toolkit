/**
 * FAQ Item block — editor script.
 *
 * A single question + a rich answer (InnerBlocks). Dynamic: save() persists the
 * answer inner blocks; the PHP seam (Zehoro\Modules\FAQ::render_item) wraps them
 * in an accessible <details>/<summary> accordion on the front end. The editor
 * shows it always-expanded (a plain block, not a real <details>) so the answer
 * stays editable.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { question, startOpen } = attributes;
	const blockProps = useBlockProps( {
		className: 'zehoro-faq__item zehoro-faq__item--editing',
	} );
	const innerProps = useInnerBlocksProps(
		{ className: 'zehoro-faq__answer' },
		{
			template: [
				[
					'core/paragraph',
					{ placeholder: __( 'Answer…', 'zehoro-toolkit' ) },
				],
			],
			templateLock: false,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'FAQ item', 'zehoro-toolkit' ) }>
					<ToggleControl
						label={ __( 'Open by default', 'zehoro-toolkit' ) }
						checked={ !! startOpen }
						onChange={ ( value ) =>
							setAttributes( { startOpen: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<RichText
					tagName="span"
					className="zehoro-faq__question"
					value={ question }
					allowedFormats={ [] }
					onChange={ ( value ) =>
						setAttributes( { question: value } )
					}
					placeholder={ __( 'Question?', 'zehoro-toolkit' ) }
				/>
				<div { ...innerProps } />
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
