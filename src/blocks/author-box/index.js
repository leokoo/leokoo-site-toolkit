/**
 * Author Box block — editor script.
 *
 * Auto-fills from the post author (or the site organization), so the editor
 * preview is a real ServerSideRender of the render seam
 * (Zehoro\Modules\AuthorBox::render_block). save() returns null. The block emits
 * no schema — ArticleSchema owns the canonical author Person.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

function Edit( { attributes, setAttributes } ) {
	const { mode, showBio, showCredentials, showSocials } = attributes;
	const blockProps = useBlockProps();
	const isPerson = mode !== 'organization';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Author Box', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Show', 'zehoro-toolkit' ) }
						value={ isPerson ? 'person' : 'organization' }
						options={ [
							{
								label: __(
									'Post author (Person)',
									'zehoro-toolkit'
								),
								value: 'person',
							},
							{
								label: __( 'Organization', 'zehoro-toolkit' ),
								value: 'organization',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { mode: value } )
						}
						help={ __(
							'Person auto-fills from the post author profile.',
							'zehoro-toolkit'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show bio', 'zehoro-toolkit' ) }
						checked={ showBio !== false }
						onChange={ ( value ) =>
							setAttributes( { showBio: value } )
						}
						__nextHasNoMarginBottom
					/>
					{ isPerson && (
						<>
							<ToggleControl
								label={ __(
									'Show credentials',
									'zehoro-toolkit'
								) }
								checked={ showCredentials !== false }
								onChange={ ( value ) =>
									setAttributes( { showCredentials: value } )
								}
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __(
									'Show social links',
									'zehoro-toolkit'
								) }
								checked={ showSocials !== false }
								onChange={ ( value ) =>
									setAttributes( { showSocials: value } )
								}
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="zehoro/author-box"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
