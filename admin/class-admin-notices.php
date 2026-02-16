<?php
/**
 * Admin Notices for LianaMailer GF.
 *
 * @package LianaMailer
 */

namespace GF_LianaMailer;

/**
 * Admin_Notices class.
 *
 * Displays admin notices in the WordPress dashboard.
 */
class Admin_Notices {

	/**
	 * Transient key prefix for dismissal.
	 */
	private const DISMISSAL_TRANSIENT_PREFIX = 'liana_deprecation_dismissed_';

	/**
	 * Dismissal duration in seconds (7 days).
	 */
	private const DISMISSAL_DURATION = 604800; // 7 * DAY_IN_SECONDS

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		// Use priority 20 to ensure all Liana plugins have registered their deprecation filters.
		\add_action( 'admin_notices', array( $this, 'deprecation_notice' ), 20 );
		\add_action( 'admin_head', array( $this, 'notice_styles' ) );
		\add_action( 'admin_footer', array( $this, 'dismiss_script' ) );
		\add_action( 'wp_ajax_liana_dismiss_deprecation_notice', array( $this, 'ajax_dismiss_notice' ) );
		\add_filter( 'liana_deprecated_plugins', array( $this, 'register_deprecated_plugin' ) );
	}

	/**
	 * Check if the notice has been dismissed.
	 *
	 * @return bool True if dismissed, false otherwise.
	 */
	private function is_notice_dismissed(): bool {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return (bool) \get_transient( self::DISMISSAL_TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * AJAX handler to dismiss the notice.
	 */
	public function ajax_dismiss_notice() {
		\check_ajax_referer( 'liana_dismiss_deprecation_notice', 'nonce' );

		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			\wp_send_json_error( 'Not logged in' );
		}

		\set_transient( self::DISMISSAL_TRANSIENT_PREFIX . $user_id, true, self::DISMISSAL_DURATION );
		\wp_send_json_success();
	}

	/**
	 * Register this plugin as deprecated.
	 *
	 * @param array $plugins List of deprecated plugins.
	 * @return array Updated list of deprecated plugins.
	 */
	public function register_deprecated_plugin( array $plugins ): array {
		$plugins['lianaautomation-gf'] = __( 'LianaAutomation for Gravity Forms', 'lianaautomation-gf' );
		return $plugins;
	}

	/**
	 * Display deprecation notice.
	 */
	public function deprecation_notice() {
		// Don't show if GrowthStack is already active.
		if ( class_exists( 'Liana\Growthstack\Plugin' ) || defined( 'GROWTHSTACK_VERSION' ) ) {
			return;
		}

		// Don't show if dismissed.
		if ( $this->is_notice_dismissed() ) {
			return;
		}

		// Check if another plugin has already displayed the notice.
		global $liana_deprecation_notice_displayed;
		if ( $liana_deprecation_notice_displayed === true ) {
			return;
		}

		// Mark the notice as displayed.
		$liana_deprecation_notice_displayed = true;

		// Collect all deprecated Liana plugins.
		$deprecated_plugins = \apply_filters( 'liana_deprecated_plugins', array() );

		if ( $deprecated_plugins === array() ) {
			return;
		}

		$icon              = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 14 14" fill="none"><g clip-path="url(#clip0_127_4784)"><path d="M0.650024 11.2019L2.81115 13.9907C5.12129 12.1979 6.60801 9.39455 6.60801 6.24399C6.60801 5.03201 6.38801 3.87144 5.98577 2.79999H2.05069C2.70139 3.78853 3.08001 4.97203 3.08001 6.24399C3.08001 8.26035 2.12852 10.0545 0.650024 11.2019Z" fill="#4CA74E"/><path d="M8.0143 11.2H11.9494C11.2986 10.2114 10.92 9.02794 10.92 7.75598C10.92 5.73962 11.8715 3.94549 13.35 2.79809L11.1889 0.00927734C8.87874 1.80207 7.39203 4.60539 7.39203 7.75598C7.39203 8.96796 7.61202 10.1285 8.0143 11.2Z" fill="#4CA74E"/></g><defs><clipPath id="clip0_127_4784"><rect width="14" height="14" fill="white"/></clipPath></defs></svg>';
		$has_single_plugin = count( $deprecated_plugins ) === 1;
		$growthstack_url   = 'https://wordpress.org/plugins/liana-with-growthstack/';
		$nonce             = \wp_create_nonce( 'liana_dismiss_deprecation_notice' );

		?>
		<div class="notice notice-warning liana-deprecation-notice is-dismissible" data-nonce="<?php echo \esc_attr( $nonce ); ?>">
			<div class="liana-notice-icon">
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded. ?>
			</div>
			<div class="liana-notice-content">
				<?php if ( $has_single_plugin ) : ?>
					<p>
						<strong><?php \esc_html_e( 'Deprecation notice!', 'lianaautomation-gf' ); ?></strong>
						<?php
						printf(
							/* translators: 1: plugin name, 2: opening bold tag, 3: closing bold tag, 4: opening link tag, 5: closing link tag */
							\__( "%1\$s's functionality has now been included in %2\$sLiana with GrowthStack%3\$s. Please install %4\$sLiana with GrowthStack%5\$s in order to keep receiving updates.", 'lianaautomation-gf' ),
							\esc_html( reset( $deprecated_plugins ) ),
							'<strong>',
							'</strong>',
							'<a href="' . \esc_url( $growthstack_url ) . '" target="_blank" rel="noopener">',
							'</a>'
						);
						?>
					</p>
				<?php else : ?>
					<p>
						<strong><?php \esc_html_e( 'Deprecation notice!', 'lianaautomation-gf' ); ?></strong>
						<?php
						printf(
							/* translators: 1: opening bold tag, 2: closing bold tag, 3: opening link tag, 4: closing link tag */
							\__( 'The following Liana plugins have been deprecated and their functionality is now included in %1$sLiana with GrowthStack%2$s. Please install %3$sLiana with GrowthStack%4$s to keep receiving updates:', 'lianaautomation-gf' ),
							'<strong>',
							'</strong>',
							'<a href="' . \esc_url( $growthstack_url ) . '" target="_blank" rel="noopener">',
							'</a>'
						);
						?>
					</p>
					<ul>
						<?php foreach ( $deprecated_plugins as $plugin_name ) : ?>
							<li><?php echo \esc_html( $plugin_name ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add inline styles for the notice.
	 */
	public function notice_styles() {
		?>
		<style>
			.liana-deprecation-notice {
				display: flex;
				align-items: flex-start;
				padding: 12px 16px;
			}
			.liana-notice-icon {
				flex-shrink: 0;
				margin-right: 12px;
				margin-top: 2px;
			}
			.liana-notice-icon svg {
				display: block;
			}
			.liana-notice-content p {
				margin: 0;
			}
			.liana-notice-content ul {
				margin: 0;
				padding-left: 20px;
				list-style: disc outside;
			}
			.liana-notice-content ul li {
				margin: 0;
			}
		</style>
		<?php
	}

	/**
	 * Add inline script for dismiss functionality.
	 */
	public function dismiss_script() {
		?>
		<script>
		(function() {
			var notice = document.querySelector('.liana-deprecation-notice');
			if (!notice) return;

			notice.addEventListener('click', function(e) {
				if (!e.target.classList.contains('notice-dismiss')) return;

				var nonce = notice.getAttribute('data-nonce');
				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxurl, true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=liana_dismiss_deprecation_notice&nonce=' + encodeURIComponent(nonce));
			});
		})();
		</script>
		<?php
	}
}
