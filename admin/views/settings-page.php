<?php
/**
 * Settings screen.
 *
 * A WordPress screen, not a portal screen. It uses core's own `.wrap`,
 * `.form-table` and notice classes so it sits naturally beside every other
 * WooCommerce settings page, then layers a small amount of structure on top --
 * grouped cards, a live accent swatch, a contrast readout. Nothing here should
 * make an administrator feel they have left wp-admin.
 *
 * The form posts to `options.php`, which is where the Settings API verifies
 * the nonce, checks the capability and runs the sanitiser. Nothing on this page
 * writes an option itself.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp View context.
 */

defined( 'ABSPATH' ) || exit;

$settings  = $wcp['settings'];
$sections  = $wcp['sections'];
$pages     = $wcp['pages'];
$themes    = $wcp['themes'];
$visuals   = $wcp['visuals'];
$page_note = $wcp['page_note'];
$accent    = $wcp['accent'];
$option    = WCP_Settings::OPTION;

$enabled_sections = (array) $settings['sections'];
?>
<div class="wrap wcp-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Customer Portal', 'woocommerce-customer-portal' ); ?></h1>
	<p class="wcp-admin__lede">
		<?php esc_html_e( 'Control what customers see in the portal, which page hosts it, and how it looks.', 'woocommerce-customer-portal' ); ?>
	</p>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" class="wcp-admin__form" novalidate>
		<?php settings_fields( WCP_Settings::GROUP ); ?>

		<!-- Availability ------------------------------------------------- -->
		<section class="wcp-admin__card">
			<header class="wcp-admin__card-head">
				<h2><?php esc_html_e( 'Availability', 'woocommerce-customer-portal' ); ?></h2>
				<p><?php esc_html_e( 'Whether the portal renders at all, and which page hosts it.', 'woocommerce-customer-portal' ); ?></p>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Portal', 'woocommerce-customer-portal' ); ?></th>
					<td>
						<label class="wcp-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option ); ?>[enabled]"
								value="1"
								<?php checked( ! empty( $settings['enabled'] ) ); ?>
							/>
							<span class="wcp-toggle__track" aria-hidden="true"><span class="wcp-toggle__thumb"></span></span>
							<span class="wcp-toggle__label"><?php esc_html_e( 'Enable the customer portal', 'woocommerce-customer-portal' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'When disabled, the shortcode renders nothing for customers. Store managers see a short notice in its place so the page is not mistaken for empty.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wcp-portal-page"><?php esc_html_e( 'Portal page', 'woocommerce-customer-portal' ); ?></label>
					</th>
					<td>
						<select name="<?php echo esc_attr( $option ); ?>[portal_page]" id="wcp-portal-page" class="wcp-select">
							<option value="0"><?php esc_html_e( '— Detect automatically —', 'woocommerce-customer-portal' ); ?></option>
							<?php foreach ( $pages as $id => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $settings['portal_page'], (int) $id ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<p class="wcp-admin__note wcp-admin__note--<?php echo esc_attr( $page_note['tone'] ); ?>">
							<span class="wcp-admin__note-dot" aria-hidden="true"></span>
							<?php echo esc_html( $page_note['text'] ); ?>
						</p>

						<p class="description">
							<?php
							printf(
								/* translators: %s: the portal shortcode. */
								esc_html__( 'The portal appears on any page containing %s. Selecting the page here does not change that page; it only lets the plugin check the page is still valid.', 'woocommerce-customer-portal' ),
								'<code>[' . esc_html( WCP_SHORTCODE_TAG ) . ']</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</section>

		<!-- Sections ----------------------------------------------------- -->
		<section class="wcp-admin__card">
			<header class="wcp-admin__card-head">
				<h2><?php esc_html_e( 'Sections', 'woocommerce-customer-portal' ); ?></h2>
				<p><?php esc_html_e( 'Which parts of the portal customers can use. A disabled section is removed from the navigation and cannot be reached by URL.', 'woocommerce-customer-portal' ); ?></p>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled sections', 'woocommerce-customer-portal' ); ?></th>
					<td>
						<fieldset class="wcp-admin__checks">
							<legend class="screen-reader-text"><?php esc_html_e( 'Enabled sections', 'woocommerce-customer-portal' ); ?></legend>
							<?php foreach ( $sections as $slug => $label ) : ?>
								<label class="wcp-check">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $option ); ?>[sections][]"
										value="<?php echo esc_attr( $slug ); ?>"
										data-wcp-section="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $enabled_sections, true ) ); ?>
									/>
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'At least one section must stay enabled. If none are selected, all sections are restored on save.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wcp-default-section"><?php esc_html_e( 'Landing section', 'woocommerce-customer-portal' ); ?></label>
					</th>
					<td>
						<select name="<?php echo esc_attr( $option ); ?>[default_section]" id="wcp-default-section" class="wcp-select" data-wcp-default-section>
							<?php foreach ( $sections as $slug => $label ) : ?>
								<option
									value="<?php echo esc_attr( $slug ); ?>"
									<?php selected( $settings['default_section'], $slug ); ?>
									<?php disabled( ! in_array( $slug, $enabled_sections, true ) ); ?>
								>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Where customers land when they open the portal. If this section is later disabled, the portal falls back to Dashboard, then to the first enabled section.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</section>

		<!-- Appearance --------------------------------------------------- -->
		<section class="wcp-admin__card">
			<header class="wcp-admin__card-head">
				<h2><?php esc_html_e( 'Appearance', 'woocommerce-customer-portal' ); ?></h2>
				<p><?php esc_html_e( 'The visual theme, the default colour scheme, the accent colour and motion.', 'woocommerce-customer-portal' ); ?></p>
			</header>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default visual theme', 'woocommerce-customer-portal' ); ?></th>
					<td>
						<fieldset class="wcp-visuals">
							<legend class="screen-reader-text"><?php esc_html_e( 'Default visual theme', 'woocommerce-customer-portal' ); ?></legend>
							<?php foreach ( $visuals as $slug => $visual ) : ?>
								<label class="wcp-visual">
									<input
										type="radio"
										name="<?php echo esc_attr( $option ); ?>[visual_theme]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( $settings['visual_theme'], $slug ); ?>
									/>
									<span class="wcp-visual__card">
										<span class="wcp-visual__preview wcp-visual__preview--<?php echo esc_attr( $slug ); ?>" aria-hidden="true">
											<span class="wcp-visual__preview-side"></span>
											<span class="wcp-visual__preview-body">
												<span class="wcp-visual__preview-bar"></span>
												<span class="wcp-visual__preview-tiles">
													<span></span><span></span><span></span>
												</span>
											</span>
										</span>
										<span class="wcp-visual__name"><?php echo esc_html( $visual['label'] ); ?></span>
										<span class="wcp-visual__text"><?php echo esc_html( $visual['text'] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'The theme every visitor starts with. A customer who picks a theme in the portal keeps their choice; it is stored in their browser and never on the server.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wcp-accent"><?php esc_html_e( 'Accent colour', 'woocommerce-customer-portal' ); ?></label>
					</th>
					<td>
						<div class="wcp-accent">
							<input
								type="color"
								id="wcp-accent"
								name="<?php echo esc_attr( $option ); ?>[accent]"
								value="<?php echo esc_attr( $accent['hex'] ); ?>"
								class="wcp-accent__picker"
								data-wcp-accent
							/>
							<code class="wcp-accent__hex" data-wcp-accent-hex><?php echo esc_html( $accent['hex'] ); ?></code>

							<span
								class="wcp-accent__preview"
								data-wcp-accent-preview
								style="--wcp-admin-accent: <?php echo esc_attr( $accent['hex'] ); ?>; --wcp-admin-accent-on: <?php echo esc_attr( $accent['on'] ); ?>;"
								aria-hidden="true"
							>
								<?php esc_html_e( 'Sign in', 'woocommerce-customer-portal' ); ?>
							</span>

							<span class="wcp-accent__contrast wcp-accent__contrast--<?php echo $accent['passes'] ? 'ok' : 'warn'; ?>" data-wcp-accent-contrast>
								<?php
								printf(
									/* translators: %s: contrast ratio, e.g. 5.9. */
									esc_html__( '%s:1 contrast', 'woocommerce-customer-portal' ),
									esc_html( (string) $accent['contrast'] )
								);
								?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Used for buttons, links and the active navigation item in every visual theme. Leave it at the default and each theme uses its own accent; change it and the colour you choose overrides all three. Button text switches between white and near-black automatically to stay readable; a ratio under 4.5:1 means neither option reaches WCAG AA on this colour.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wcp-theme"><?php esc_html_e( 'Default colour scheme', 'woocommerce-customer-portal' ); ?></label>
					</th>
					<td>
						<select name="<?php echo esc_attr( $option ); ?>[theme]" id="wcp-theme" class="wcp-select">
							<?php foreach ( $themes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['theme'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'A customer who has chosen a scheme with the switcher in the portal keeps their choice. This setting applies to everyone who has not.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Premium motion', 'woocommerce-customer-portal' ); ?></th>
					<td>
						<label class="wcp-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option ); ?>[motion]"
								value="1"
								<?php checked( ! empty( $settings['motion'] ) ); ?>
							/>
							<span class="wcp-toggle__track" aria-hidden="true"><span class="wcp-toggle__thumb"></span></span>
							<span class="wcp-toggle__label"><?php esc_html_e( 'Enable entrance animations, section transitions and ambient motion', 'woocommerce-customer-portal' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Visitors who have asked their device for reduced motion never see these, whatever this setting says. Switch it off to give everyone the still version.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( '3D card effects', 'woocommerce-customer-portal' ); ?></th>
					<td>
						<label class="wcp-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option ); ?>[effects_3d]"
								value="1"
								<?php checked( ! empty( $settings['effects_3d'] ) ); ?>
							/>
							<span class="wcp-toggle__track" aria-hidden="true"><span class="wcp-toggle__thumb"></span></span>
							<span class="wcp-toggle__label"><?php esc_html_e( 'Tilt selected cards towards the pointer with a subtle depth effect', 'woocommerce-customer-portal' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Desktop only: the effect activates on devices with a mouse or trackpad and never on touch screens. Forms never tilt while a field is being edited.', 'woocommerce-customer-portal' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</section>

		<?php submit_button( __( 'Save changes', 'woocommerce-customer-portal' ) ); ?>
	</form>
</div>
