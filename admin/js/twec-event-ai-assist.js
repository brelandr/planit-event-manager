/**
 * PlanIt event editor: AI assist panel (server-side WP AI Client via REST).
 */
( function( wp, cfg ) {
	'use strict';

	if ( ! cfg || ! cfg.postId || ! cfg.restRoot ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var PluginDocumentSettingPanel = wp.editPost && wp.editPost.PluginDocumentSettingPanel;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var TextareaControl = wp.components.TextareaControl;
	var i18n = cfg.i18n || {};

	var ACCEPT_ACTIONS = [ 'draft-description', 'publish-prep', 'alt-text', 'suggest-taxonomy' ];

	function postAi( endpoint, body ) {
		return apiFetch( {
			path: '/planit/v1/ai/' + endpoint,
			method: 'POST',
			data: Object.assign( { post_id: cfg.postId, nonce: cfg.nonce }, body || {} ),
		} );
	}

	function apiErrorMessage( err, fallback ) {
		if ( err && err.message ) {
			return err.message;
		}
		if ( err && err.data && err.data.message ) {
			return err.data.message;
		}
		return fallback;
	}

	function isAcceptableAction( action ) {
		return ACCEPT_ACTIONS.indexOf( action ) >= 0;
	}

	function acceptLabel( action ) {
		if ( 'alt-text' === action ) {
			return i18n.acceptAlt || 'Apply alt text';
		}
		if ( 'suggest-taxonomy' === action ) {
			return i18n.acceptTaxonomy || 'Apply categories & tags';
		}
		return i18n.accept || 'Accept';
	}

	function fetchOrCreateTerm( taxonomy, slug ) {
		return apiFetch( {
			path: '/wp/v2/' + taxonomy + '?slug=' + encodeURIComponent( slug ),
		} ).then( function( terms ) {
			if ( terms && terms[0] && terms[0].id ) {
				return terms[0].id;
			}
			return apiFetch( {
				path: '/wp/v2/' + taxonomy,
				method: 'POST',
				data: {
					name: slug.replace( /-/g, ' ' ),
					slug: slug,
				},
			} ).then( function( term ) {
				return term && term.id ? term.id : 0;
			} );
		} );
	}

	function applyAltText( text ) {
		var editorSelect = wp.data.select( 'core/editor' );
		var coreDispatch = wp.data.dispatch( 'core' );
		if ( ! editorSelect || ! coreDispatch || ! coreDispatch.editEntityRecord ) {
			return Promise.reject( new Error( i18n.noEditor || 'Editor is not available.' ) );
		}
		var mediaId = editorSelect.getEditedPostAttribute( 'featured_media' );
		if ( ! mediaId ) {
			return Promise.reject( new Error( i18n.noFeaturedImage || 'Set a featured image first.' ) );
		}
		coreDispatch.editEntityRecord( 'postType', 'attachment', mediaId, { alt_text: text } );
		if ( coreDispatch.saveEditedEntityRecord ) {
			coreDispatch.saveEditedEntityRecord( 'postType', 'attachment', mediaId );
		}
		return Promise.resolve();
	}

	function applyTaxonomies( categories, tags ) {
		var catSlugs = Array.isArray( categories ) ? categories : [];
		var tagSlugs = Array.isArray( tags ) ? tags : [];
		if ( ! catSlugs.length && ! tagSlugs.length ) {
			return Promise.reject( new Error( i18n.noTaxonomySuggestions || 'No categories or tags to apply.' ) );
		}
		return Promise.all( [
			Promise.all( catSlugs.map( function( slug ) {
				return fetchOrCreateTerm( 'twec_event_category', slug );
			} ) ),
			Promise.all( tagSlugs.map( function( slug ) {
				return fetchOrCreateTerm( 'twec_event_tag', slug );
			} ) ),
		] ).then( function( results ) {
			var catIds = ( results[0] || [] ).filter( function( id ) { return !! id; } );
			var tagIds = ( results[1] || [] ).filter( function( id ) { return !! id; } );
			var patch = {};
			if ( catIds.length ) {
				patch.twec_event_category = catIds;
			}
			if ( tagIds.length ) {
				patch.twec_event_tag = tagIds;
			}
			var editorDispatch = wp.data.dispatch( 'core/editor' );
			if ( editorDispatch && editorDispatch.editPost && Object.keys( patch ).length ) {
				editorDispatch.editPost( patch );
			}
		} );
	}

	function AiAssistPanel() {
		var previewState = useState( '' );
		var preview = previewState[0];
		var setPreview = previewState[1];
		var excerptState = useState( '' );
		var excerpt = excerptState[0];
		var setExcerpt = excerptState[1];
		var loadingState = useState( false );
		var loading = loadingState[0];
		var setLoading = loadingState[1];
		var lastActionState = useState( '' );
		var lastAction = lastActionState[0];
		var setLastAction = lastActionState[1];
		var acceptBodyState = useState( '' );
		var acceptBody = acceptBodyState[0];
		var setAcceptBody = acceptBodyState[1];
		var taxCatsState = useState( [] );
		var taxCats = taxCatsState[0];
		var setTaxCats = taxCatsState[1];
		var taxTagsState = useState( [] );
		var taxTags = taxTagsState[0];
		var setTaxTags = taxTagsState[1];
		var statusState = useState( '' );
		var status = statusState[0];
		var setStatus = statusState[1];

		function clearPreview() {
			setPreview( '' );
			setAcceptBody( '' );
			setExcerpt( '' );
			setTaxCats( [] );
			setTaxTags( [] );
			setStatus( '' );
		}

		function run( action, endpoint ) {
			setLoading( true );
			setLastAction( action );
			setStatus( '' );
			postAi( endpoint, {} )
				.then( function( res ) {
					if ( 'publish-prep' === endpoint && res ) {
						var lines = [];
						var prepCats = res.categories && res.categories.length ? res.categories : [];
						var prepTags = res.tags && res.tags.length ? res.tags : [];
						if ( res.summary ) {
							lines.push( res.summary );
						}
						if ( res.checks && res.checks.length ) {
							res.checks.forEach( function( row ) {
								if ( row.field && row.message ) {
									lines.push( row.field + ': ' + row.message );
								}
							} );
						}
						if ( prepCats.length ) {
							lines.push( __( 'Categories:', 'planit-event-manager' ) + ' ' + prepCats.join( ', ' ) );
						}
						if ( prepTags.length ) {
							lines.push( __( 'Tags:', 'planit-event-manager' ) + ' ' + prepTags.join( ', ' ) );
						}
						if ( res.description ) {
							lines.push( '\n' + ( res.description || '' ) );
						}
						setPreview( lines.join( '\n' ) );
						setAcceptBody( res.description || '' );
						setExcerpt( res.excerpt || '' );
						setTaxCats( prepCats );
						setTaxTags( prepTags );
					} else if ( 'draft-description' === endpoint && res ) {
						setPreview( res.description || '' );
						setAcceptBody( res.description || '' );
						setExcerpt( res.excerpt || '' );
					} else if ( 'social-snippet' === endpoint && res ) {
						setPreview( res.snippet || '' );
					} else if ( 'alt-text' === endpoint && res ) {
						var alt = res.alt_text || '';
						setPreview( alt );
						setAcceptBody( alt );
					} else if ( 'suggest-taxonomy' === endpoint && res ) {
						var lines = [];
						var cats = res.categories && res.categories.length ? res.categories : [];
						var tags = res.tags && res.tags.length ? res.tags : [];
						if ( cats.length ) {
							lines.push( __( 'Categories:', 'planit-event-manager' ) + ' ' + cats.join( ', ' ) );
						}
						if ( tags.length ) {
							lines.push( __( 'Tags:', 'planit-event-manager' ) + ' ' + tags.join( ', ' ) );
						}
						setPreview( lines.join( '\n' ) );
						setTaxCats( cats );
						setTaxTags( tags );
					}
				} )
				.catch( function( err ) {
					setPreview( apiErrorMessage( err, i18n.error || 'AI request failed.' ) );
				} )
				.finally( function() {
					setLoading( false );
				} );
		}

		function acceptPreview() {
			if ( ! isAcceptableAction( lastAction ) ) {
				return;
			}
			setLoading( true );
			setStatus( '' );

			var done = Promise.resolve();

			if ( 'alt-text' === lastAction ) {
				done = applyAltText( acceptBody || preview );
			} else if ( 'suggest-taxonomy' === lastAction ) {
				done = applyTaxonomies( taxCats, taxTags );
			} else {
				var editor = wp.data.dispatch( 'core/editor' );
				var promises = [];
				if ( editor && editor.editPost ) {
					var body = 'publish-prep' === lastAction ? acceptBody : preview;
					var patch = { content: body || preview };
					if ( excerpt ) {
						patch.excerpt = excerpt;
					}
					promises.push( Promise.resolve().then( function() {
						editor.editPost( patch );
					} ) );
				}
				if ( 'publish-prep' === lastAction && ( taxCats.length || taxTags.length ) ) {
					promises.push( applyTaxonomies( taxCats, taxTags ) );
				}
				if ( promises.length ) {
					done = Promise.all( promises );
				}
			}

			done.then( function() {
				setStatus( i18n.accepted || 'Applied.' );
				clearPreview();
				setLastAction( '' );
			} ).catch( function( err ) {
				setStatus( apiErrorMessage( err, i18n.error || 'Could not apply suggestion.' ) );
			} ).finally( function() {
				setLoading( false );
			} );
		}

		var canAccept = isAcceptableAction( lastAction ) && (
			( 'suggest-taxonomy' === lastAction && ( taxCats.length || taxTags.length ) ) ||
			( 'suggest-taxonomy' !== lastAction && ( preview || acceptBody ) )
		);

		return el(
			PluginDocumentSettingPanel,
			{ name: 'twec-ai-assist', title: i18n.panelTitle || 'PlanIt AI Assist', className: 'twec-ai-assist-panel' },
			el( 'p', { className: 'description' }, __( 'Suggestions are previews only until you click Accept.', 'planit-event-manager' ) ),
			el( Button, { variant: 'secondary', onClick: function() { run( 'publish-prep', 'publish-prep' ); }, disabled: loading }, i18n.publishPrep || 'Publish prep' ),
			' ',
			el( Button, { variant: 'secondary', onClick: function() { run( 'draft-description', 'draft-description' ); }, disabled: loading }, i18n.draftDesc || 'Generate description' ),
			' ',
			el( Button, { variant: 'secondary', onClick: function() { run( 'suggest-taxonomy', 'suggest-taxonomy' ); }, disabled: loading }, i18n.suggestTax || 'Suggest categories & tags' ),
			' ',
			el( Button, { variant: 'secondary', onClick: function() { run( 'social-snippet', 'social-snippet' ); }, disabled: loading }, i18n.social || 'Social snippet' ),
			' ',
			el( Button, { variant: 'secondary', onClick: function() { run( 'alt-text', 'alt-text' ); }, disabled: loading }, i18n.altText || 'Alt text' ),
			loading ? el( Spinner ) : null,
			status ? el( 'p', { className: 'twec-ai-assist-status' }, status ) : null,
			preview ? el(
				'div',
				{ className: 'twec-ai-assist-preview' },
				el( 'strong', null, i18n.previewLabel || 'Preview' ),
				el( TextareaControl, { value: preview, onChange: setPreview, rows: 10 } ),
				el( Button, { variant: 'primary', onClick: acceptPreview, disabled: loading || ! canAccept }, acceptLabel( lastAction ) ),
				' ',
				el( Button, { variant: 'secondary', onClick: function() { run( lastAction, lastAction ); }, disabled: loading || ! lastAction }, i18n.regenerate || 'Regenerate' ),
				' ',
				el( Button, { isDestructive: true, onClick: function() { clearPreview(); setLastAction( '' ); } }, i18n.discard || 'Discard' )
			) : null
		);
	}

	function registerPlugin() {
		if ( ! wp.plugins || ! wp.plugins.registerPlugin ) {
			return;
		}
		wp.plugins.registerPlugin( 'twec-event-ai-assist', {
			render: function() {
				return el( AiAssistPanel );
			},
		} );
	}

	function applyAiSeed() {
		try {
			var params = new URLSearchParams( window.location.search );
			var seed = params.get( 'twec_ai_seed' );
			if ( ! seed || ! wp.data || typeof wp.data.dispatch !== 'function' ) {
				return;
			}
			var editor = wp.data.dispatch( 'core/editor' );
			if ( editor && editor.editPost ) {
				var trimmed = String( seed ).trim().substring( 0, 200 );
				if ( trimmed ) {
					editor.editPost( { title: trimmed } );
				}
			}
		} catch ( e ) {
			// Ignore URL parse errors.
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			registerPlugin();
			applyAiSeed();
		} );
	} else {
		registerPlugin();
		applyAiSeed();
	}
}( window.wp, window.twecEventAiAssist ) );
