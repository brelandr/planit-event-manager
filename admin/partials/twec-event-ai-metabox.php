<?php
/**
 * Classic editor AI assist metabox.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $post_id ) ? (int) $post_id : 0;
?>
<div class="twec-ai-classic-wrap">
	<p class="description"><?php esc_html_e( 'Suggestions are previews only until you click Accept.', 'planit-event-manager' ); ?></p>
	<p>
		<button type="button" class="button twec-ai-classic-run" data-endpoint="publish-prep"><?php esc_html_e( 'Publish prep', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-ai-classic-run" data-endpoint="draft-description"><?php esc_html_e( 'Generate description', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-ai-classic-run" data-endpoint="suggest-taxonomy"><?php esc_html_e( 'Suggest categories & tags', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-ai-classic-run" data-endpoint="social-snippet"><?php esc_html_e( 'Social snippet', 'planit-event-manager' ); ?></button>
		<span class="spinner twec-ai-classic-spinner" style="float:none;"></span>
	</p>
	<p>
		<label for="twec-ai-classic-preview"><strong><?php esc_html_e( 'Preview', 'planit-event-manager' ); ?></strong></label>
		<textarea id="twec-ai-classic-preview" class="widefat" rows="8" readonly="readonly"></textarea>
	</p>
	<p>
		<button type="button" class="button button-primary twec-ai-classic-accept" disabled="disabled"><?php esc_html_e( 'Accept into content', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-ai-classic-regenerate" disabled="disabled"><?php esc_html_e( 'Regenerate', 'planit-event-manager' ); ?></button>
		<button type="button" class="button twec-ai-classic-discard"><?php esc_html_e( 'Discard', 'planit-event-manager' ); ?></button>
	</p>
	<input type="hidden" id="twec-ai-classic-post-id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
	<input type="hidden" id="twec-ai-classic-nonce" value="<?php echo esc_attr( wp_create_nonce( 'twec_ai_assist_' . $post_id ) ); ?>" />
	<input type="hidden" id="twec-ai-classic-last-endpoint" value="" />
	<input type="hidden" id="twec-ai-classic-excerpt" value="" />
	<input type="hidden" id="twec-ai-classic-accept-body" value="" />
</div>
