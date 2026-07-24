<?php
/**
 * Two Factor Guard core class.
 *
 * @package Two_Factor_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Two_Factor_Guard
 */
class Two_Factor_Guard {

	const OPTION = 'two_factor_guard_settings';
	const META_SECRET       = '_tfg_secret';
	const META_ENABLED      = '_tfg_enabled';
	const META_BACKUP       = '_tfg_backup_codes';
	const META_CONFIRMED    = '_tfg_confirmed';
	const TIME_STEP         = 30;
	const CODE_LENGTH       = 6;

	/**
	 * Initialize.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'render_user_profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_profile' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_profile' ) );

		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 101, 3 );
		add_action( 'login_form', array( __CLASS__, 'login_form_field' ) );
	}

	/**
	 * Get settings.
     *
     * @return array
     */
	public static function get_settings() {
		$defaults = array(
			'enabled'      => 0,
			'enforce'      => array(),
			'allow_opt_in' => 1,
		);
		$settings = get_option( self::OPTION, array() );
		$settings = wp_parse_args( $settings, $defaults );
		if ( ! is_array( $settings['enforce'] ) ) {
			$settings['enforce'] = array();
		}
		return $settings;
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu() {
		add_management_page(
			esc_html__( 'Two Factor Guard', 'two-factor-guard' ),
			esc_html__( 'Two Factor Guard', 'two-factor-guard' ),
			'manage_options',
			'two-factor-guard',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Enqueue assets.
     *
     * @param string $hook Admin hook.
     */
	public static function enqueue_assets( $hook ) {
		if ( 'tools_page_two-factor-guard' !== $hook && 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'tfg-admin', TFG_URL . 'assets/css/admin.css', array(), TFG_VERSION );
		wp_enqueue_script( 'tfg-admin', TFG_URL . 'assets/js/admin.js', array(), TFG_VERSION, true );
	}

	/**
	 * Save admin settings.
	 */
	public static function save_settings() {
		if ( ! isset( $_POST['tfg_save'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tfg_settings' ) ) {
			return;
		}

		$settings = self::get_settings();
		$settings['enabled']      = isset( $_POST['tfg_enabled'] ) ? 1 : 0;
		$settings['allow_opt_in'] = isset( $_POST['tfg_allow_opt_in'] ) ? 1 : 0;
		$settings['enforce']      = isset( $_POST['tfg_enforce'] ) ? array_map( 'sanitize_text_field', (array) $_POST['tfg_enforce'] ) : array();

		update_option( self::OPTION, $settings );
		wp_safe_redirect( add_query_arg( 'tfg_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Render admin settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'two-factor-guard' ) );
		}

		$settings = self::get_settings();
		$roles    = wp_roles()->get_names();
		?>
		<div class="wrap tfg-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( isset( $_GET['tfg_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'two-factor-guard' ); ?></p></div>
			<?php endif; ?>

			<form method="post" class="tfg-form">
				<?php wp_nonce_field( 'tfg_settings' ); ?>

				<div class="tfg-card">
					<h2><?php esc_html_e( 'Global Settings', 'two-factor-guard' ); ?></h2>
					<label class="tfg-toggle">
						<input type="checkbox" name="tfg_enabled" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
						<span><?php esc_html_e( 'Enable two-factor authentication', 'two-factor-guard' ); ?></span>
					</label>
					<label class="tfg-toggle">
						<input type="checkbox" name="tfg_allow_opt_in" value="1" <?php checked( 1, $settings['allow_opt_in'] ); ?>>
						<span><?php esc_html_e( 'Allow users to opt in from their profile', 'two-factor-guard' ); ?></span>
					</label>
				</div>

				<div class="tfg-card">
					<h2><?php esc_html_e( 'Enforce for Roles', 'two-factor-guard' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Selected roles must set up 2FA and will be prompted for a code on login.', 'two-factor-guard' ); ?></p>
					<?php foreach ( $roles as $role => $label ) : ?>
						<label class="tfg-check">
							<input type="checkbox" name="tfg_enforce[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $settings['enforce'], true ) ); ?>>
							<span><?php echo esc_html( translate_user_role( $label ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

				<?php submit_button( __( 'Save Settings', 'two-factor-guard' ), 'primary', 'tfg_save' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render user profile 2FA setup.
     *
     * @param WP_User $user User object.
     */
	public static function render_user_profile( $user ) {
		$settings = self::get_settings();
		if ( ! $settings['enabled'] && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$secret       = get_user_meta( $user->ID, self::META_SECRET, true );
		$enabled      = (bool) get_user_meta( $user->ID, self::META_ENABLED, true );
		$confirmed    = (bool) get_user_meta( $user->ID, self::META_CONFIRMED, true );
		$backup_codes = get_user_meta( $user->ID, self::META_BACKUP, true );
		if ( ! is_array( $backup_codes ) ) {
			$backup_codes = array();
		}

		$site_name = get_bloginfo( 'name' );
		$otpauth   = '';
		$qr_url    = '';

		if ( ! $secret ) {
			$secret = self::generate_secret();
			update_user_meta( $user->ID, self::META_SECRET, $secret );
			update_user_meta( $user->ID, self::META_ENABLED, 0 );
			update_user_meta( $user->ID, self::META_CONFIRMED, 0 );
		}

		$otpauth = 'otpauth://totp/' . rawurlencode( $site_name . ':' . $user->user_login ) . '?secret=' . $secret . '&issuer=' . rawurlencode( $site_name );
		$qr_url  = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $otpauth );

		wp_nonce_field( 'tfg_profile', '_tfg_nonce' );
		?>
		<div class="tfg-profile-section">
			<h2><?php esc_html_e( 'Two Factor Guard', 'two-factor-guard' ); ?></h2>

			<?php if ( $enabled && $confirmed ) : ?>
				<div class="tfg-status enabled"><?php esc_html_e( '2FA is enabled for this account.', 'two-factor-guard' ); ?></div>
			<?php else : ?>
				<div class="tfg-status disabled"><?php esc_html_e( '2FA is not enabled yet. Scan the QR code and enter a code to confirm.', 'two-factor-guard' ); ?></div>
			<?php endif; ?>

			<div class="tfg-grid">
				<div class="tfg-card">
					<h3><?php esc_html_e( 'Step 1: Scan QR Code', 'two-factor-guard' ); ?></h3>
					<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'QR Code', 'two-factor-guard' ); ?>" class="tfg-qr">
					<p><strong><?php esc_html_e( 'Secret:', 'two-factor-guard' ); ?></strong> <code class="tfg-secret"><?php echo esc_html( $secret ); ?></code></p>
				</div>

				<div class="tfg-card">
					<h3><?php esc_html_e( 'Step 2: Verify Code', 'two-factor-guard' ); ?></h3>
					<input type="text" name="tfg_verify_code" id="tfg_verify_code" class="regular-text" placeholder="<?php esc_attr_e( 'Enter 6-digit code', 'two-factor-guard' ); ?>">
					<label class="tfg-toggle">
						<input type="checkbox" name="tfg_enable_2fa" id="tfg_enable_2fa" value="1" <?php checked( true, $enabled ); ?>>
						<span><?php esc_html_e( 'Enable 2FA for my account', 'two-factor-guard' ); ?></span>
					</label>
				</div>

				<?php if ( ! empty( $backup_codes ) ) : ?>
					<div class="tfg-card">
						<h3><?php esc_html_e( 'Backup Codes', 'two-factor-guard' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Save these one-time codes in a safe place.', 'two-factor-guard' ); ?></p>
						<ul class="tfg-codes">
							<?php foreach ( $backup_codes as $code ) : ?>
								<li><code><?php echo esc_html( $code ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save user profile 2FA settings.
     *
     * @param int $user_id User ID.
     */
	public static function save_user_profile( $user_id ) {
		if ( ! isset( $_POST['_tfg_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_tfg_nonce'] ) ), 'tfg_profile' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$secret = get_user_meta( $user_id, self::META_SECRET, true );
		if ( ! $secret ) {
			$secret = self::generate_secret();
			update_user_meta( $user_id, self::META_SECRET, $secret );
		}

		$want_enable = isset( $_POST['tfg_enable_2fa'] );
		$code        = isset( $_POST['tfg_verify_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tfg_verify_code'] ) ) : '';

		if ( $want_enable ) {
			if ( self::verify_totp( $secret, $code ) ) {
				update_user_meta( $user_id, self::META_ENABLED, 1 );
				update_user_meta( $user_id, self::META_CONFIRMED, 1 );

				$backup = self::generate_backup_codes( 8 );
				update_user_meta( $user_id, self::META_BACKUP, $backup );

				add_settings_error( 'tfg_profile', 'tfg_enabled', __( '2FA enabled. Save your backup codes.', 'two-factor-guard' ), 'success' );
			} else {
				update_user_meta( $user_id, self::META_ENABLED, 0 );
				update_user_meta( $user_id, self::META_CONFIRMED, 0 );
				add_settings_error( 'tfg_profile', 'tfg_invalid', __( 'Invalid verification code. 2FA not enabled.', 'two-factor-guard' ), 'error' );
			}
		} else {
			update_user_meta( $user_id, self::META_ENABLED, 0 );
			update_user_meta( $user_id, self::META_CONFIRMED, 0 );
		}
	}

	/**
	 * Add 2FA field to login form.
	 */
	public static function login_form_field() {
		$settings = self::get_settings();
		if ( ! $settings['enabled'] ) {
			return;
		}
		?>
		<p>
			<label for="tfg_code"><?php esc_html_e( '2FA Code (optional)', 'two-factor-guard' ); ?></label>
			<input type="text" name="tfg_code" id="tfg_code" class="input" value="" size="20" autocomplete="off" placeholder="<?php esc_attr_e( '6-digit code', 'two-factor-guard' ); ?>">
		</p>
		<?php
	}

	/**
	 * Authenticate filter.
     *
     * @param WP_User|WP_Error|null $user     Current user object.
     * @param string                $username Username.
     * @param string                $password Password.
     * @return WP_User|WP_Error|null
     */
	public static function authenticate( $user, $username, $password ) {
		if ( ! $user instanceof WP_User ) {
			return $user;
		}

		$settings = self::get_settings();
		if ( ! $settings['enabled'] ) {
			return $user;
		}

		$enabled  = (bool) get_user_meta( $user->ID, self::META_ENABLED, true );
		$secret   = get_user_meta( $user->ID, self::META_SECRET, true );
		$roles    = (array) $user->roles;
		$enforced = ! empty( $settings['enforce'] ) && array_intersect( $settings['enforce'], $roles );

		if ( ! $enabled && ! $enforced ) {
			return $user;
		}

		if ( ! $secret ) {
			// Enforced role without setup; block until configured.
			if ( $enforced ) {
				return new WP_Error( 'tfg_required', __( 'Two-factor authentication is required. Please set it up from your profile.', 'two-factor-guard' ) );
			}
			return $user;
		}

		$code = isset( $_POST['tfg_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tfg_code'] ) ) : '';

		if ( ! $code ) {
			if ( $enforced || $enabled ) {
				return new WP_Error( 'tfg_required', __( 'Two-factor authentication code is required.', 'two-factor-guard' ) );
			}
			return $user;
		}

		if ( self::verify_totp( $secret, $code ) ) {
			return $user;
		}

		if ( self::verify_backup_code( $user->ID, $code ) ) {
			return $user;
		}

		return new WP_Error( 'tfg_invalid', __( 'Invalid two-factor authentication code.', 'two-factor-guard' ) );
	}

	/**
	 * Generate a random base32 secret.
     *
     * @param int $length Length in bytes.
     * @return string
     */
	public static function generate_secret( $length = 20 ) {
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$secret .= $chars[ random_int( 0, 31 ) ];
		}
		return $secret;
	}

	/**
	 * Generate backup codes.
     *
     * @param int $count Number of codes.
     * @return array
     */
	public static function generate_backup_codes( $count = 8 ) {
		$codes = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$codes[] = strtoupper( self::base32_encode( random_bytes( 5 ) ) );
		}
		return $codes;
	}

	/**
	 * Base32 encode.
     *
     * @param string $data Binary data.
     * @return string
     */
	private static function base32_encode( $data ) {
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$len    = strlen( $data );
		$output = '';
		$bits   = 0;
		$value  = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$value  = ( $value << 8 ) | ord( $data[ $i ] );
			$bits  += 8;
			while ( $bits >= 5 ) {
				$output .= $chars[ ( $value >> ( $bits - 5 ) ) & 31 ];
				$bits   -= 5;
			}
		}
		if ( $bits > 0 ) {
			$output .= $chars[ ( $value << ( 5 - $bits ) ) & 31 ];
		}
		return $output;
	}

	/**
	 * Base32 decode.
     *
     * @param string $input Base32 string.
     * @return string
     */
	private static function base32_decode( $input ) {
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$map    = array();
		for ( $i = 0; $i < 32; $i++ ) {
			$map[ $chars[ $i ] ] = $i;
		}
		$input  = strtoupper( $input );
		$len    = strlen( $input );
		$output = '';
		$bits   = 0;
		$value  = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$char = $input[ $i ];
			if ( ! isset( $map[ $char ] ) ) {
				continue;
			}
			$value = ( $value << 5 ) | $map[ $char ];
			$bits += 5;
			while ( $bits >= 8 ) {
				$output .= chr( ( $value >> ( $bits - 8 ) ) & 255 );
				$bits   -= 8;
			}
		}
		return $output;
	}

	/**
	 * Verify a TOTP code.
     *
     * @param string $secret Base32 secret.
     * @param string $code   User supplied code.
     * @return bool
     */
	public static function verify_totp( $secret, $code ) {
		if ( ! ctype_digit( $code ) || strlen( $code ) !== self::CODE_LENGTH ) {
			return false;
		}
		$secret = self::base32_decode( $secret );
		if ( ! $secret ) {
			return false;
		}

		$time_step = self::TIME_STEP;
		$time      = floor( time() / $time_step );

		for ( $i = -1; $i <= 1; $i++ ) {
			$expected = self::totp( $secret, $time + $i );
			if ( hash_equals( $expected, $code ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate a TOTP code for a time step.
     *
     * @param string $secret  Decoded binary secret.
     * @param int    $counter Time counter.
     * @return string
     */
	private static function totp( $secret, $counter ) {
		$counter = pack( 'N*', 0, $counter );
		$hash    = hash_hmac( 'sha1', $counter, $secret, true );
		$offset  = ord( $hash[19] ) & 0x0F;
		$code    = (
			( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xFF )
		) % 1000000;
		return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Verify a backup code.
     *
     * @param int    $user_id User ID.
     * @param string $code    Code.
     * @return bool
     */
	private static function verify_backup_code( $user_id, $code ) {
		$code   = strtoupper( sanitize_text_field( $code ) );
		$codes  = get_user_meta( $user_id, self::META_BACKUP, true );
		if ( ! is_array( $codes ) ) {
			return false;
		}

		foreach ( $codes as $i => $stored ) {
			if ( hash_equals( strtoupper( $stored ), $code ) ) {
				unset( $codes[ $i ] );
				update_user_meta( $user_id, self::META_BACKUP, array_values( $codes ) );
				return true;
			}
		}
		return false;
	}
}
