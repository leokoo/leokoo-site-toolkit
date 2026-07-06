/**
 * Modules page — client-side filter UX.
 *
 * Adapted from the WPExtended pattern in
 * zorasi-roadmaps/specs/phase-0-module-filtering.md. Vanilla JS, no jQuery.
 *
 * State precedence on initial load:
 *   URL params  >  localStorage  >  defaults
 *
 * After interaction, both localStorage and the URL update in sync so deep
 * links are shareable ("here are my inactive Pro SEO modules").
 *
 * @param {Object} data Localized config from PHP: { storageKey, defaults }
 */
( function ( data ) {
	'use strict';

	var STORAGE_KEY = data.storageKey || 'zehoroModuleSettings';
	var DEFAULTS    = data.defaults || { search: '', status: 'all', layout: 'grid', group: 'all' };
	var DEBOUNCE_MS = 300;
	var STAGGER_MS  = 30; // 30ms per card — feels tactile without being slow

	var els = {};
	var state = loadInitialState();

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		els.root          = document.querySelector( '#zehoro-modules-grid' );
		els.search        = document.querySelector( '#zehoro-modules-search' );
		els.searchClear   = document.querySelector( '.zehoro-module-filters__search-clear' );
		els.pills         = document.querySelectorAll( '.zehoro-status-pill' );
		els.layoutBtns    = document.querySelectorAll( '.zehoro-module-filters__layout-button' );
		els.navLinks      = document.querySelectorAll( '.zehoro-module-nav__link' );
		els.empty         = document.querySelector( '#zehoro-modules-empty' );
		els.totalCounter  = document.querySelector( '#zehoro-modules-result-visible' );
		els.liveRegion    = document.querySelector( '.zehoro-live-region' );

		if ( ! els.root || ! els.search ) return; // not on the modules page

		// Restore from state.
		els.search.value = state.search;
		setActivePill( state.status );
		setActiveLayout( state.layout );
		setActiveGroup( state.group );
		updateSearchClearVisibility();

		bindEvents();
		bindToggleEvents();
		bindSuiteEvents();
		bindBulkEvents();
		bindPresetEvents();
		// applyFilter() recomputes the (bucket-scoped) pill counts from live
		// card state, so the server-rendered pills can never sit stale.
		applyFilter( /* skipAnimation = */ true );
	}

	/**
	 * Bulk enable/disable. Scope follows the sidebar group selection:
	 * "All" = every module, a category = that category. Confirm first —
	 * one click flips up to the whole plugin.
	 */
	function bindBulkEvents() {
		if ( ! data.rest || ! data.rest.root || ! data.rest.bulkRoute ) return;
		var btns = document.querySelectorAll( '.zehoro-bulk-btn' );

		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var enable = btn.dataset.bulk === 'enable';
				var group  = state.group || 'all';

				var scope = ( data.i18n && data.i18n.allModules ) || 'all modules';
				if ( group !== 'all' ) {
					var link  = document.querySelector( '.zehoro-module-nav__link[data-group="' + group + '"]' );
					var label = link ? link.textContent.replace( /\(\d+\)\s*$/, '' ).trim() : group;
					scope = ( ( data.i18n && data.i18n.groupModules ) || 'all modules in "{group}"' ).replace( '{group}', label );
				}

				var template = enable
					? ( ( data.i18n && data.i18n.bulkEnableConfirm )  || 'Enable {scope}?' )
					: ( ( data.i18n && data.i18n.bulkDisableConfirm ) || 'Disable {scope}?' );
				if ( ! window.confirm( template.replace( '{scope}', scope ) ) ) return;

				btns.forEach( function ( b ) { b.disabled = true; } );

				fetch( data.rest.root + data.rest.bulkRoute, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':   data.rest.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( { enable: enable, group: group === 'all' ? '' : group } ),
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					btns.forEach( function ( b ) { b.disabled = false; } );
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Bulk update failed.' );
						return;
					}
					( res.json.slugs || [] ).forEach( function ( slug ) { applyBulkToSlug( slug, enable ); } );
					updatePillCounts();
					applyFilter();
				} )
				.catch( function () {
					btns.forEach( function ( b ) { b.disabled = false; } );
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Bulk update failed.' );
				} );
			} );
		} );
	}

	/**
	 * Persona presets — "enable this curated set". Only ever ENABLES;
	 * the confirm copy says so explicitly.
	 */
	function bindPresetEvents() {
		if ( ! data.rest || ! data.rest.root || ! data.rest.bulkRoute ) return;

		document.querySelectorAll( '.zehoro-preset-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var slugs = [];
				try { slugs = JSON.parse( btn.dataset.presetSlugs || '[]' ); } catch ( e ) { slugs = []; }
				if ( ! slugs.length ) return;

				var msg = ( ( data.i18n && data.i18n.presetConfirm ) || 'Enable the {count} modules in “{title}”? Nothing will be disabled.' )
					.replace( '{count}', String( slugs.length ) )
					.replace( '{title}', btn.dataset.presetTitle || '' );
				if ( ! window.confirm( msg ) ) return;

				btn.disabled = true;

				fetch( data.rest.root + data.rest.bulkRoute, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':   data.rest.nonce,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( { enable: true, slugs: slugs } ),
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Could not apply the preset.' );
						return;
					}
					( res.json.slugs || [] ).forEach( function ( slug ) { applyBulkToSlug( slug, true ); } );
					updatePillCounts();
					applyFilter();
				} )
				.catch( function () {
					btn.disabled = false;
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Could not apply the preset.' );
				} );
			} );
		} );
	}

	/**
	 * Recompute every status-pill count from live card state, SCOPED to the
	 * active bucket + search. Each pill shows how many cards you'd see if that
	 * pill were the selected status (group + search held fixed) — so "All (n)"
	 * always equals the visible count for the current bucket, never the whole
	 * plugin. (The old version counted every card and only touched Active /
	 * Inactive, so the top pills read 34/32 while the Loop bucket held 14.)
	 */
	function updatePillCounts() {
		var group  = state.group || 'all';
		var q      = state.search || '';
		var tokens = q ? q.split( /\s+/ ).filter( Boolean ) : [];
		var cards  = els.root.querySelectorAll( '.lkst-module-card' );

		var all = 0, active = 0, free = 0, pro = 0;
		cards.forEach( function ( c ) {
			var hay       = ( c.dataset.moduleHaystack || '' ).toLowerCase();
			var cardGroup = c.dataset.moduleBucket || c.dataset.moduleGroup || 'other';
			var matchesGroup  = group === 'all' || cardGroup === group;
			var matchesSearch = tokens.length === 0
				|| tokens.every( function ( t ) { return hay.indexOf( t ) !== -1; } );
			if ( ! matchesGroup || ! matchesSearch ) return;

			all++;
			if ( c.dataset.moduleActive === '1' ) active++;
			if ( ( c.dataset.moduleTier || 'free' ) === 'pro' ) pro++; else free++;
		} );

		var set = function ( status, n ) {
			var count = document.querySelector( '.zehoro-status-pill[data-status="' + status + '"] .zehoro-status-pill__count' );
			if ( count ) count.textContent = '(' + n + ')';
		};
		set( 'all',      all );
		set( 'active',   active );
		set( 'inactive', all - active );
		set( 'free',     free );
		set( 'pro',      pro );
	}

	function bindToggleEvents() {
		if ( ! data.rest || ! data.rest.root ) return; // no REST config — fall back to Save button
		var switches = document.querySelectorAll( '.lkst-module-card:not(.zehoro-suite-card) .lkst-switch input[type="checkbox"]' );
		switches.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var card = input.closest( '.lkst-module-card' );
				if ( ! card ) return;
				var slug = card.dataset.moduleSlug || '';
				if ( ! slug ) return;

				input.disabled = true;
				var prevChecked = ! input.checked; // we just flipped — previous is the inverse

				var url = data.rest.root + data.rest.toggleRoute.replace( '{slug}', encodeURIComponent( slug ) );
				fetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':   data.rest.nonce,
						'Content-Type': 'application/json',
					},
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					input.disabled = false;
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						input.checked = prevChecked; // rollback UI
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
						return;
					}
					// Update card state for the filter logic + live pill counts.
					var enabled = !! res.json.enabled;
					card.dataset.moduleActive = enabled ? '1' : '0';
					card.classList.toggle( 'active',   enabled );
					card.classList.toggle( 'inactive', ! enabled );
					updatePillCounts();
				} )
				.catch( function () {
					input.disabled = false;
					input.checked = prevChecked;
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
				} );
			} );
		} );
	}

	/**
	 * Suite cards (Blocks / Schema / Reading & Trust): one card whose master
	 * toggle flips every member at once (bulk route), with a sub-list of
	 * per-member toggles (per-module route). The regular per-card handler skips
	 * suite cards (see the :not(.zehoro-suite-card) selector above).
	 */
	function bindSuiteEvents() {
		if ( ! data.rest || ! data.rest.root ) return;

		document.querySelectorAll( '.zehoro-suite-card' ).forEach( function ( c ) { syncSuiteMaster( c ); } );

		// Master toggle → bulk enable/disable all members.
		document.querySelectorAll( '.zehoro-suite-card .zehoro-suite-master input[type="checkbox"]' ).forEach( function ( master ) {
			master.addEventListener( 'change', function () {
				var card = master.closest( '.zehoro-suite-card' );
				if ( ! card ) return;
				var members = ( card.dataset.suiteMembers || '' ).split( ',' ).filter( Boolean );
				if ( ! members.length ) return;
				var enable = master.checked;
				master.disabled = true;
				master.indeterminate = false;

				fetch( data.rest.root + data.rest.bulkRoute, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': data.rest.nonce, 'Content-Type': 'application/json' },
					body: JSON.stringify( { enable: enable, slugs: members } ),
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					master.disabled = false;
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						master.checked = ! enable;
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Update failed.' );
						return;
					}
					card.querySelectorAll( '.zehoro-suite-member input[type="checkbox"]' ).forEach( function ( mi ) { mi.checked = enable; } );
					updateSuiteCard( card );
					updatePillCounts();
				} )
				.catch( function () {
					master.disabled = false;
					master.checked = ! enable;
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Update failed.' );
				} );
			} );
		} );

		// Member toggle → per-module toggle route.
		document.querySelectorAll( '.zehoro-suite-member input[type="checkbox"]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var li = input.closest( '.zehoro-suite-member' );
				if ( ! li ) return;
				var slug = li.dataset.moduleSlug || '';
				if ( ! slug ) return;
				input.disabled = true;
				var prev = ! input.checked;

				var url = data.rest.root + data.rest.toggleRoute.replace( '{slug}', encodeURIComponent( slug ) );
				fetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': data.rest.nonce, 'Content-Type': 'application/json' },
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					input.disabled = false;
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						input.checked = prev;
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
						return;
					}
					input.checked = !! res.json.enabled;
					updateSuiteCard( input.closest( '.zehoro-suite-card' ) );
					updatePillCounts();
				} )
				.catch( function () {
					input.disabled = false;
					input.checked = prev;
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
				} );
			} );
		} );
	}

	/** Recompute a suite card's active count + card state + master from its members. */
	/**
	 * Sync one module's UI after a server-side bulk/preset change. Handles BOTH a
	 * top-level module card AND a folded suite MEMBER — a bucket-scoped bulk (e.g.
	 * "Blocks") spans whole suites whose members live inside a suite card, not as
	 * top-level cards, so looking them up as top-level cards silently skipped them
	 * and left the suite toggles + pill counts stale until a reload.
	 */
	function applyBulkToSlug( slug, enable ) {
		var card = els.root.querySelector( '.lkst-module-card[data-module-slug="' + slug + '"]' );
		if ( card ) {
			card.dataset.moduleActive = enable ? '1' : '0';
			card.classList.toggle( 'active',   enable );
			card.classList.toggle( 'inactive', ! enable );
			var input = card.querySelector( '.lkst-switch input[type="checkbox"]' );
			if ( input ) input.checked = enable;
			return;
		}
		// Folded suite member: set its checkbox + resync the enclosing suite card.
		var li = els.root.querySelector( '.zehoro-suite-member[data-module-slug="' + slug + '"]' );
		if ( ! li ) return;
		var mi = li.querySelector( 'input[type="checkbox"]' );
		if ( mi ) mi.checked = enable;
		updateSuiteCard( li.closest( '.zehoro-suite-card' ) );
	}

	function updateSuiteCard( card ) {
		if ( ! card ) return;
		var members = card.querySelectorAll( '.zehoro-suite-member input[type="checkbox"]' );
		var active  = 0;
		members.forEach( function ( m ) { if ( m.checked ) active++; } );
		card.dataset.moduleActive = active > 0 ? '1' : '0';
		card.classList.toggle( 'active',   active > 0 );
		card.classList.toggle( 'inactive', active === 0 );
		var count = card.querySelector( '.zehoro-suite-count' );
		if ( count ) count.textContent = String( active );
		syncSuiteMaster( card, active, members.length );
	}

	/** Master checkbox: checked when all on, indeterminate when only some on. */
	function syncSuiteMaster( card, active, total ) {
		var master = card.querySelector( '.zehoro-suite-master input[type="checkbox"]' );
		if ( ! master ) return;
		if ( typeof active === 'undefined' ) {
			var members = card.querySelectorAll( '.zehoro-suite-member input[type="checkbox"]' );
			total  = members.length;
			active = 0;
			members.forEach( function ( m ) { if ( m.checked ) active++; } );
		}
		master.checked = total > 0 && active === total;
		master.indeterminate = active > 0 && active < total;
	}

	function bindEvents() {
		// Debounced search.
		var searchTimer = null;
		els.search.addEventListener( 'input', function () {
			updateSearchClearVisibility();
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function () {
				state.search = ( els.search.value || '' ).trim().toLowerCase();
				persistState();
				applyFilter();
			}, DEBOUNCE_MS );
		} );

		if ( els.searchClear ) {
			els.searchClear.addEventListener( 'click', function () {
				els.search.value = '';
				state.search = '';
				updateSearchClearVisibility();
				persistState();
				applyFilter();
				els.search.focus();
			} );
		}

		els.pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				state.status = pill.dataset.status || 'all';
				setActivePill( state.status );
				persistState();
				applyFilter();
			} );
		} );

		els.layoutBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				state.layout = btn.dataset.layout || 'grid';
				setActiveLayout( state.layout );
				persistState( /* skipUrlForLayout = */ true );
				applyLayout();
			} );
		} );

		els.navLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				state.group = link.dataset.group || 'all';
				setActiveGroup( state.group );
				persistState();
				applyFilter();
			} );
		} );
	}

	function applyFilter( skipAnimation ) {
		var q       = state.search;
		var tokens  = q ? q.split( /\s+/ ).filter( Boolean ) : [];
		var status  = state.status;
		var group   = state.group;
		var cards   = els.root.querySelectorAll( '.lkst-module-card' );
		var visible = 0;

		cards.forEach( function ( card, idx ) {
			var hay      = ( card.dataset.moduleHaystack || '' ).toLowerCase();
			var isActive = card.dataset.moduleActive === '1';
			var tier     = card.dataset.moduleTier || 'free';
			// The left-nav filters by BUCKET (Loop / Blocks / Conversion / Toolkit),
			// one level above the fine-grained data-module-group.
			var cardGroup = card.dataset.moduleBucket || card.dataset.moduleGroup || 'other';

			var matchesSearch = tokens.length === 0
				|| tokens.every( function ( t ) { return hay.indexOf( t ) !== -1; } );
			var matchesStatus = status === 'all'
				|| ( status === 'active'   && isActive )
				|| ( status === 'inactive' && ! isActive )
				|| ( status === 'free'     && tier === 'free' )
				|| ( status === 'pro'      && tier === 'pro' );
			var matchesGroup = group === 'all' || cardGroup === group;

			var show = matchesSearch && matchesStatus && matchesGroup;

			if ( show ) {
				visible++;
				card.style.display = '';
				card.classList.remove( 'lkst-module-card--fade-out' );
				if ( ! skipAnimation ) {
					card.style.setProperty( '--stagger-delay', ( visible * STAGGER_MS ) + 'ms' );
					card.classList.remove( 'lkst-module-card--fade-in' );
					// Force reflow so the animation re-triggers cleanly.
					void card.offsetWidth;
					card.classList.add( 'lkst-module-card--fade-in' );
				}
			} else {
				card.style.display = 'none';
				card.classList.remove( 'lkst-module-card--fade-in' );
			}
		} );

		if ( els.totalCounter ) els.totalCounter.textContent = String( visible );
		if ( els.empty ) els.empty.style.display = visible === 0 ? 'block' : 'none';
		if ( els.root  ) els.root.style.display  = visible === 0 ? 'none'  : '';

		// Pills are scoped to the current bucket + search, so refresh them
		// whenever the filter changes (switching buckets, typing a search).
		updatePillCounts();
		announce( visible );
	}

	function applyLayout() {
		if ( ! els.root ) return;
		els.root.classList.remove( 'zehoro-modules--grid', 'zehoro-modules--list' );
		els.root.classList.add( state.layout === 'list' ? 'zehoro-modules--list' : 'zehoro-modules--grid' );
	}

	function setActivePill( status ) {
		els.pills.forEach( function ( pill ) {
			pill.setAttribute( 'aria-pressed', pill.dataset.status === status ? 'true' : 'false' );
		} );
	}

	function setActiveLayout( layout ) {
		els.layoutBtns.forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.dataset.layout === layout ? 'true' : 'false' );
		} );
		applyLayout();
	}

	function setActiveGroup( group ) {
		var matched = false;
		els.navLinks.forEach( function ( link ) {
			var isMatch = link.dataset.group === group;
			link.setAttribute( 'aria-current', isMatch ? 'true' : 'false' );
			link.classList.toggle( 'is-active', isMatch );
			if ( isMatch ) matched = true;
		} );
		// Saved group no longer exists (e.g. module that owned the only entry
		// in that group got deactivated) — fall back to "all" instead of
		// silently hiding everything.
		if ( ! matched && group !== 'all' ) {
			state.group = 'all';
			setActiveGroup( 'all' );
		}
	}

	function updateSearchClearVisibility() {
		if ( ! els.searchClear ) return;
		els.searchClear.style.display = els.search.value ? 'inline-flex' : 'none';
	}

	function announce( visible ) {
		if ( ! els.liveRegion ) return;
		// String built server-side — JS just injects the count.
		els.liveRegion.textContent = String( visible ) + ' modules shown';
	}

	function loadInitialState() {
		var stored = {};
		try { stored = JSON.parse( localStorage.getItem( STORAGE_KEY ) ) || {}; } catch ( e ) { stored = {}; }

		var url    = new URLSearchParams( window.location.search );

		return {
			search: url.get( 'search' ) || stored.search || DEFAULTS.search,
			status: url.get( 'status' ) || stored.status || DEFAULTS.status,
			group:  url.get( 'group' )  || stored.group  || DEFAULTS.group,
			layout: stored.layout       || DEFAULTS.layout,
		};
	}

	function persistState( skipUrlForLayout ) {
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) { /* private window etc. — silently ignore */ }

		// Update URL (without reload) so deep links are shareable.
		// Layout is a personal preference — kept in localStorage only.
		var url = new URL( window.location.href );
		if ( state.search )                     url.searchParams.set( 'search', state.search ); else url.searchParams.delete( 'search' );
		if ( state.status && state.status !== 'all' ) url.searchParams.set( 'status', state.status ); else url.searchParams.delete( 'status' );
		if ( state.group  && state.group  !== 'all' ) url.searchParams.set( 'group',  state.group  ); else url.searchParams.delete( 'group' );
		window.history.replaceState( {}, '', url.toString() );
	}
} )( window.zehoroModulesAdmin || {} );
