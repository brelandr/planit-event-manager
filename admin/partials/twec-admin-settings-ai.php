<?php
/**
 * AI settings section for PlanIt Event Manager.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals.

$ai_enabled            = isset( $settings['ai_enabled'] ) ? (string) $settings['ai_enabled'] : 'no';
$ai_admin_assist       = isset( $settings['ai_admin_assist'] ) ? (string) $settings['ai_admin_assist'] : 'no';
$ai_abilities          = isset( $settings['ai_abilities'] ) ? (string) $settings['ai_abilities'] : 'no';
$ai_public_assistant   = isset( $settings['ai_public_assistant'] ) ? (string) $settings['ai_public_assistant'] : 'no';
$ai_command_palette    = isset( $settings['ai_command_palette'] ) ? (string) $settings['ai_command_palette'] : 'no';
$ai_bulk_publish_prep  = isset( $settings['ai_bulk_publish_prep'] ) ? (string) $settings['ai_bulk_publish_prep'] : 'no';
$ai_temperature_preset = isset( $settings['ai_temperature_preset'] ) ? (string) $settings['ai_temperature_preset'] : 'factual';
$connectors_url        = class_exists( 'TWEC_AI', false ) ? TWEC_AI::get_connectors_admin_url() : admin_url( 'options-connectors.php' );
$can_manage_connectors = class_exists( 'TWEC_AI', false ) && TWEC_AI::current_user_can_manage_connectors();
$ai_client_ready       = class_exists( 'TWEC_AI', false ) && TWEC_AI::is_text_generation_available();
?>
<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
	<h2><?php esc_html_e( 'AI (WordPress 7.0+)', 'planit-event-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'PlanIt can use your site’s AI Connectors (Settings → Connectors). All features are off by default. No API keys are stored in PlanIt.', 'planit-event-manager' ); ?>
	</p>
	<?php if ( ! function_exists( 'wp_ai_client_prompt' ) ) : ?>
		<p><strong><?php esc_html_e( 'WordPress AI Client is not available on this site (requires WordPress 7.0+ and a configured connector).', 'planit-event-manager' ); ?></strong></p>
	<?php elseif ( class_exists( 'TWEC_AI', false ) && ! TWEC_AI::is_ai_environment_enabled() ) : ?>
		<p><strong><?php esc_html_e( 'WordPress AI is disabled on this site (WP_AI_SUPPORT is false or filtered off).', 'planit-event-manager' ); ?></strong></p>
	<?php elseif ( ! $ai_client_ready ) : ?>
		<p>
			<?php
			$connectors_label = esc_html__( 'Settings → Connectors', 'planit-event-manager' );
			$connectors_markup = $can_manage_connectors
				? '<a href="' . esc_url( $connectors_url ) . '">' . $connectors_label . '</a>'
				: $connectors_label;
			printf(
				/* translators: %s: Settings → Connectors link or label. */
				esc_html__( 'No AI provider is ready for text generation. Configure one under %s.', 'planit-event-manager' ),
				$connectors_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_url/esc_html above.
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Install and activate the provider plugin shown on the Connectors screen (for example “AI Provider for OpenAI”), save your API key, and confirm the connector status is Connected.', 'planit-event-manager' ); ?>
		</p>
	<?php else : ?>
		<p class="description" style="color:#00a32a;">
			<strong><?php esc_html_e( 'AI text generation is available on this site.', 'planit-event-manager' ); ?></strong>
			<?php
			$provider_labels = class_exists( 'TWEC_AI', false ) ? TWEC_AI::get_configured_ai_provider_labels() : array();
			if ( ! empty( $provider_labels ) ) {
				echo ' ';
				printf(
					/* translators: %s: comma-separated provider names, e.g. OpenAI */
					esc_html__( 'Connected providers: %s.', 'planit-event-manager' ),
					esc_html( implode( ', ', $provider_labels ) )
				);
			}
			?>
		</p>
	<?php endif; ?>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable PlanIt AI', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_enabled]" value="yes" <?php checked( $ai_enabled, 'yes' ); ?> /> <?php esc_html_e( 'Master switch (required for all AI features below)', 'planit-event-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Event editor assist', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_admin_assist]" value="yes" <?php checked( $ai_admin_assist, 'yes' ); ?> /> <?php esc_html_e( 'Draft descriptions, taxonomy suggestions, social snippets, alt text', 'planit-event-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Abilities API / agents', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_abilities]" value="yes" <?php checked( $ai_abilities, 'yes' ); ?> /> <?php esc_html_e( 'Expose list/search/get/create-draft abilities for MCP and automation', 'planit-event-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Public event assistant', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_public_assistant]" value="yes" <?php checked( $ai_public_assistant, 'yes' ); ?> /> <?php esc_html_e( 'Allow the Event Assistant block to answer visitor questions (rate limited)', 'planit-event-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Command palette', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_command_palette]" value="yes" <?php checked( $ai_command_palette, 'yes' ); ?> /> <?php esc_html_e( 'PlanIt commands in the block editor command palette', 'planit-event-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Events list bulk publish prep', 'planit-event-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="twec_settings[ai_bulk_publish_prep]" value="yes" <?php checked( $ai_bulk_publish_prep, 'yes' ); ?> <?php disabled( 'yes' !== $ai_admin_assist || ! $ai_client_ready ); ?> /> <?php esc_html_e( 'Add “AI Publish prep (apply)” to Bulk actions on Events → All Events', 'planit-event-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Runs publish prep on selected events and applies description, excerpt, categories, tags, featured image alt text, and stores a social snippet in event meta. Requires Event editor assist and a configured AI connector.', 'planit-event-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ai_temperature_preset"><?php esc_html_e( 'Public query style', 'planit-event-manager' ); ?></label></th>
			<td>
				<select name="twec_settings[ai_temperature_preset]" id="ai_temperature_preset">
					<option value="factual" <?php selected( $ai_temperature_preset, 'factual' ); ?>><?php esc_html_e( 'Factual (recommended)', 'planit-event-manager' ); ?></option>
					<option value="creative" <?php selected( $ai_temperature_preset, 'creative' ); ?>><?php esc_html_e( 'More conversational', 'planit-event-manager' ); ?></option>
				</select>
			</td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Use Save Changes at the bottom of this page to store AI settings.', 'planit-event-manager' ); ?></p>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
