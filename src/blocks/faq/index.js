/**
 * FAQ block — editor script.
 *
 * A container for zehoro/faq-item blocks. Dynamic: save() persists the inner
 * items; the PHP seam (Zehoro\Modules\FAQ::render_container) wraps them.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

const TEMPLATE = [ [ 'zehoro/faq-item' ], [ 'zehoro/faq-item' ] ];

function Edit() {
	const blockProps = useBlockProps( { className: 'zehoro-faq' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: [ 'zehoro/faq-item' ],
		template: TEMPLATE,
	} );
	return <div { ...innerProps } />;
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
