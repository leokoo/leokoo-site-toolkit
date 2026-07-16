/**
 * "We Tested" evaluation block — registration.
 *
 * Dynamic block: save() returns null; the render seam
 * (Zehoro\Modules\Evaluation::render_block) owns all front-end markup + the
 * guardrailed Review schema.
 */
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
