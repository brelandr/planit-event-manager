<?php
/**
 * Venue / organizer AI assist metabox.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id   = isset( $post ) && $post instanceof WP_Post ? (int) $post->ID : 0;
$post_type = isset( $post ) && $post instanceof WP_Post ? (string) $post->post_type : '';
$endpoint  = 'twec_venue' === $post_type ? 'venue-description' : 'organizer-bio';
$label     = 'twec_venue' === $post_type
	? __( 'Generate venue description', 'planit-event-manager' )
	: __( 'Generate organizer bio', 'planit-event-manager' );
?>
<div class="twec-entity-ai-wrap">
	<p class="description"><?php esc_html_e( 'Preview AI copy before accepting it into the editor.', 'planit-event-manager' ); ?></p>
	<p>
		<button type="button" class="button twec-entity-ai-run" data-endpoint="<?php echo esc_attr( $endpoint ); ?>"><?php echo esc_html( $label ); ?></button>
		<span class="spinner twec-entity-ai-spinner" style="float:none;"></span>
	</p>
	<p>
		<label for="twec-entity-ai-preview"><strong><?php esc_html_e( 'Preview', 'planit-event-manager' ); ?></strong></label>
		<textarea id="twec-entity-ai-preview" class="widefat" rows="8" readonly="readonly"></textarea>
	</p>
	<p>
		<button type="button" class="button button-primary twec-entity-ai-accept" disabled="disabled"><?php esc_html_e( 'Accept into content', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-entity-ai-discard"><?php esc_html_e( 'Discard', 'planit-event-manager' ); ?></button>
	</p>
	<input type="hidden" id="twec-entity-ai-post-id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
	<input type="hidden" id="twec-entity-ai-nonce" value="<?php echo esc_attr( $post_id > 0 ? wp_create_nonce( 'twec_ai_assist_' . $post_id ) : '' ); ?>" />
</div>
