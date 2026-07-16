/**
 * "We Tested" evaluation block — editor.
 *
 * A dynamic block: the canvas is an editing surface; all front-end markup comes
 * from the PHP render seam. Reuses the star-picker + list-editor UX from Pro's
 * Review Box, minus the affiliate machinery.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { Fragment } from '@wordpress/element';

// ── Star picker (0–5) ─────────────────────────────────────────────────────────
const StarPicker = ( { value, onChange } ) => (
	<span className="zehoro-eval-editor__stars">
		{ [ 1, 2, 3, 4, 5 ].map( ( n ) => (
			<button
				key={ n }
				type="button"
				className={ `zehoro-eval-editor__star ${ n <= value ? 'is-filled' : '' }` }
				onClick={ () => onChange( n === value ? 0 : n ) }
				aria-label={ `${ n } ${ n !== 1 ? __( 'stars', 'zehoro-toolkit' ) : __( 'star', 'zehoro-toolkit' ) }` }
			>
				★
			</button>
		) ) }
		<span className="zehoro-eval-editor__star-val">
			{ value > 0 ? `${ value }/5` : '–' }
		</span>
	</span>
);

// ── Simple string-list editor (pros / cons) ───────────────────────────────────
const ListEditor = ( { label, items, onChange, placeholder } ) => {
	const add = () => onChange( [ ...items, '' ] );
	const remove = ( i ) => onChange( items.filter( ( _, idx ) => idx !== i ) );
	const update = ( i, val ) => {
		const next = [ ...items ];
		next[ i ] = val;
		onChange( next );
	};
	return (
		<div className="zehoro-eval-editor__list">
			{ label && <p className="zehoro-eval-editor__list-label">{ label }</p> }
			{ items.map( ( item, i ) => (
				<div key={ i } className="zehoro-eval-editor__list-row">
					<TextControl
						value={ item }
						placeholder={ placeholder }
						onChange={ ( v ) => update( i, v ) }
						__nextHasNoMarginBottom
					/>
					<Button
						isDestructive
						variant="tertiary"
						icon="no-alt"
						label={ __( 'Remove', 'zehoro-toolkit' ) }
						onClick={ () => remove( i ) }
					/>
				</div>
			) ) }
			<Button variant="secondary" icon="plus" onClick={ add }>
				{ __( 'Add item', 'zehoro-toolkit' ) }
			</Button>
		</div>
	);
};

function computeAverage( criteria ) {
	const scored = criteria.filter( ( c ) => c.score > 0 );
	if ( ! scored.length ) {
		return 0;
	}
	return scored.reduce( ( sum, c ) => sum + c.score, 0 ) / scored.length;
}

// Client-side mirror of Evaluation::is_self_serving() — a courtesy warning only;
// the PHP seam is the authoritative guard.
function looksSelfServing( subjectName, subjectUrl, site ) {
	if ( ! site ) {
		return false;
	}
	try {
		if ( subjectUrl && site.url ) {
			const subjHost = new URL( subjectUrl, site.url ).host.toLowerCase();
			const siteHost = new URL( site.url ).host.toLowerCase();
			if ( subjHost && subjHost === siteHost ) {
				return true;
			}
		}
	} catch ( e ) {
		// malformed URL — ignore, PHP guard still applies
	}
	if (
		subjectName &&
		site.title &&
		subjectName.trim().toLowerCase() === site.title.trim().toLowerCase()
	) {
		return true;
	}
	return false;
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		subjectName,
		subjectUrl,
		itemType,
		criteria,
		methodology,
		verdict,
		pros,
		cons,
		emitSchema,
		headingLevel,
	} = attributes;

	const blockProps = useBlockProps( { className: 'zehoro-eval zehoro-eval--editor' } );
	const avg = computeAverage( criteria );

	const site = useSelect( ( select ) => {
		const core = select( 'core' );
		return core && core.getSite ? core.getSite() : undefined;
	}, [] );
	const selfServing = emitSchema && looksSelfServing( subjectName, subjectUrl, site );

	const addCriterion = () =>
		setAttributes( { criteria: [ ...criteria, { name: '', score: 0 } ] } );
	const removeCriterion = ( i ) =>
		setAttributes( { criteria: criteria.filter( ( _, idx ) => idx !== i ) } );
	const updateCriterion = ( i, field, val ) => {
		const next = criteria.map( ( c, idx ) =>
			idx === i ? { ...c, [ field ]: val } : c
		);
		setAttributes( { criteria: next } );
	};

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Subject', 'zehoro-toolkit' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'What you tested', 'zehoro-toolkit' ) }
						value={ subjectName }
						onChange={ ( v ) => setAttributes( { subjectName: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Subject link (optional)', 'zehoro-toolkit' ) }
						help={ __( 'The third-party product/service page. Used for schema.', 'zehoro-toolkit' ) }
						value={ subjectUrl }
						type="url"
						onChange={ ( v ) => setAttributes( { subjectUrl: v } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Subject heading level', 'zehoro-toolkit' ) }
						value={ String( headingLevel ) }
						options={ [
							{ label: 'H2', value: '2' },
							{ label: 'H3', value: '3' },
							{ label: 'H4', value: '4' },
						] }
						onChange={ ( v ) => setAttributes( { headingLevel: Number( v ) } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody title={ __( 'Review schema', 'zehoro-toolkit' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Emit Review JSON-LD', 'zehoro-toolkit' ) }
						help={ __( 'Structured data for a genuine third-party review. Auto-suppressed for a review of your own site.', 'zehoro-toolkit' ) }
						checked={ emitSchema }
						onChange={ ( v ) => setAttributes( { emitSchema: v } ) }
						__nextHasNoMarginBottom
					/>
					{ emitSchema && (
						<SelectControl
							label={ __( 'Item type (schema.org)', 'zehoro-toolkit' ) }
							value={ itemType }
							options={ [
								{ label: __( 'Product', 'zehoro-toolkit' ), value: 'Product' },
								{ label: __( 'Book', 'zehoro-toolkit' ), value: 'Book' },
								{ label: __( 'Software / App', 'zehoro-toolkit' ), value: 'SoftwareApplication' },
								{ label: __( 'Movie', 'zehoro-toolkit' ), value: 'Movie' },
								{ label: __( 'Music Album', 'zehoro-toolkit' ), value: 'MusicAlbum' },
								{ label: __( 'Local Business', 'zehoro-toolkit' ), value: 'LocalBusiness' },
								{ label: __( 'Course', 'zehoro-toolkit' ), value: 'Course' },
								{ label: __( 'Recipe', 'zehoro-toolkit' ), value: 'Recipe' },
								{ label: __( 'Game', 'zehoro-toolkit' ), value: 'Game' },
							] }
							onChange={ ( v ) => setAttributes( { itemType: v } ) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					) }
					{ selfServing && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'This looks like a review of your own site or brand. Review schema will be suppressed to stay within Google’s guidelines — the visible card still shows.', 'zehoro-toolkit' ) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="zehoro-eval__header">
					<TextControl
						className="zehoro-eval-editor__subject"
						placeholder={ __( 'What you tested…', 'zehoro-toolkit' ) }
						value={ subjectName }
						onChange={ ( v ) => setAttributes( { subjectName: v } ) }
						__nextHasNoMarginBottom
					/>
					{ avg > 0 && (
						<div className="zehoro-eval__score">
							<span className="zehoro-eval__score-num">{ avg.toFixed( 1 ) }</span>
							<span className="zehoro-eval__score-max">/5</span>
						</div>
					) }
				</div>

				<div className="zehoro-eval-editor__section">
					<p className="zehoro-eval-editor__label">{ __( 'Rating criteria', 'zehoro-toolkit' ) }</p>
					{ criteria.map( ( c, i ) => (
						<div key={ i } className="zehoro-eval-editor__criterion">
							<TextControl
								placeholder={ __( 'Criterion…', 'zehoro-toolkit' ) }
								value={ c.name }
								onChange={ ( v ) => updateCriterion( i, 'name', v ) }
								__nextHasNoMarginBottom
							/>
							<StarPicker
								value={ c.score }
								onChange={ ( v ) => updateCriterion( i, 'score', v ) }
							/>
							<Button
								isDestructive
								variant="tertiary"
								icon="no-alt"
								label={ __( 'Remove criterion', 'zehoro-toolkit' ) }
								onClick={ () => removeCriterion( i ) }
							/>
						</div>
					) ) }
					<Button variant="secondary" icon="plus" onClick={ addCriterion }>
						{ __( 'Add criterion', 'zehoro-toolkit' ) }
					</Button>
				</div>

				<div className="zehoro-eval-editor__section">
					<TextareaControl
						label={ __( 'How we tested', 'zehoro-toolkit' ) }
						placeholder={ __( 'What you did, for how long, against what…', 'zehoro-toolkit' ) }
						value={ methodology }
						rows={ 3 }
						onChange={ ( v ) => setAttributes( { methodology: v } ) }
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="zehoro-eval-editor__proscons">
					<ListEditor
						label={ __( 'Pros', 'zehoro-toolkit' ) }
						items={ pros }
						onChange={ ( v ) => setAttributes( { pros: v } ) }
						placeholder={ __( 'A pro…', 'zehoro-toolkit' ) }
					/>
					<ListEditor
						label={ __( 'Cons', 'zehoro-toolkit' ) }
						items={ cons }
						onChange={ ( v ) => setAttributes( { cons: v } ) }
						placeholder={ __( 'A con…', 'zehoro-toolkit' ) }
					/>
				</div>

				<div className="zehoro-eval-editor__section">
					<TextareaControl
						label={ __( 'Verdict', 'zehoro-toolkit' ) }
						placeholder={ __( 'The bottom line…', 'zehoro-toolkit' ) }
						value={ verdict }
						rows={ 2 }
						onChange={ ( v ) => setAttributes( { verdict: v } ) }
						__nextHasNoMarginBottom
					/>
				</div>
			</div>
		</Fragment>
	);
}
