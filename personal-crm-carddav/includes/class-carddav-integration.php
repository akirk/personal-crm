<?php
/**
 * CardDAV Integration Class
 *
 * Main integration point between Personal CRM and CardDAV
 *
 * @package Personal_CRM_CardDAV
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personal_CRM_CardDAV_Integration
 *
 * Singleton class that manages the integration between Personal CRM and CardDAV
 */
class Personal_CRM_CardDAV_Integration {

	/**
	 * The single instance of the class
	 *
	 * @var Personal_CRM_CardDAV_Integration
	 */
	private static $instance = null;

	/**
	 * The CardDAV server instance
	 *
	 * @var Personal_CRM_CardDAV_Server
	 */
	private $carddav_server;

	/**
	 * The storage instance from Personal CRM
	 *
	 * @var object
	 */
	private $storage;

	/**
	 * Get the singleton instance
	 *
	 * @return Personal_CRM_CardDAV_Integration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook into Personal CRM loaded event
		add_action( 'personal_crm_loaded', array( $this, 'initialize' ) );

		// Register rewrite rules
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );

		// Handle CardDAV requests
		add_action( 'template_redirect', array( $this, 'handle_carddav_request' ), 1 );

		// Add settings page
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 99 );

		// Add CardDAV info to Personal CRM dashboard
		add_action( 'personal_crm_dashboard_sidebar', array( $this, 'add_carddav_info_to_dashboard' ), 10, 2 );
	}

	/**
	 * Initialize the integration after Personal CRM is loaded
	 *
	 * @param PersonalCrm $crm The Personal CRM instance
	 */
	public function initialize( $crm ) {
		// Get storage from Personal CRM
		$this->storage = $crm::get_globals()->storage;

		// Initialize CardDAV server
		$this->carddav_server = new Personal_CRM_CardDAV_Server();
		$this->carddav_server->set_storage( $this->storage );

		// Hook into Personal CRM events for vCard data
		add_filter( 'personal_crm_get_person', array( $this, 'add_vcard_data_to_person' ), 10, 2 );
		add_action( 'personal_crm_person_saved', array( $this, 'save_vcard_data' ), 10, 4 );
		add_action( 'personal_crm_person_deleting', array( $this, 'delete_vcard_data' ), 10, 2 );

		/**
		 * Action fired after CardDAV integration is initialized
		 *
		 * @param Personal_CRM_CardDAV_Integration $integration The integration instance
		 */
		do_action( 'personal_crm_carddav_initialized', $this );
	}

	/**
	 * Add extended vCard data to person when loading
	 *
	 * @param array $person_data The person data
	 * @param int   $person_id   The person ID
	 * @return array Modified person data
	 */
	public function add_vcard_data_to_person( $person_data, $person_id ) {
		$vcard_data = $this->storage->get_vcard_data( $person_id );
		if ( ! empty( $vcard_data ) ) {
			$person_data['vcard_data'] = $vcard_data;
		}
		return $person_data;
	}

	/**
	 * Save extended vCard data when person is saved
	 *
	 * @param int    $person_id   The person ID
	 * @param string $username    The username
	 * @param array  $person_data The person data
	 * @param bool   $is_new      Whether this is a new person
	 */
	public function save_vcard_data( $person_id, $username, $person_data, $is_new ) {
		// Only save vCard data if it's provided
		if ( isset( $person_data['vcard_data'] ) && is_array( $person_data['vcard_data'] ) ) {
			$this->storage->save_vcard_data( $person_id, $person_data['vcard_data'] );
		}
	}

	/**
	 * Delete extended vCard data when person is deleted
	 *
	 * @param int    $person_id The person ID
	 * @param string $username  The username
	 */
	public function delete_vcard_data( $person_id, $username ) {
		$this->storage->delete_vcard_data( $person_id );
	}

	/**
	 * Register rewrite rules for CardDAV endpoints
	 */
	public function register_rewrite_rules() {
		// CardDAV endpoint: /carddav/
		add_rewrite_rule(
			'^carddav(/.*)?$',
			'index.php?carddav_request=1&carddav_path=$matches[1]',
			'top'
		);

		// Add query vars
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Add query vars for CardDAV
	 *
	 * @param array $vars The query vars
	 * @return array Modified query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'carddav_request';
		$vars[] = 'carddav_path';
		return $vars;
	}

	/**
	 * Handle CardDAV requests
	 */
	public function handle_carddav_request() {
		$is_carddav = get_query_var( 'carddav_request' );

		if ( ! $is_carddav ) {
			return;
		}

		// Ensure Personal CRM is loaded
		if ( ! $this->carddav_server ) {
			header( 'HTTP/1.1 503 Service Unavailable' );
			echo 'Personal CRM not initialized';
			exit;
		}

		$path = get_query_var( 'carddav_path' );
		$path = trim( $path, '/' );

		// Handle the request
		$this->carddav_server->handle_request( $path );
	}

	/**
	 * Add settings page
	 */
	public function add_settings_page() {
		add_submenu_page(
			'crm',
			__( 'Contact Sync Settings', 'personal-crm-carddav' ),
			__( 'Contact Sync', 'personal-crm-carddav' ),
			'manage_options',
			'personal-crm-carddav',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_url = home_url( '/carddav' );
		$current_user = wp_get_current_user();

		// Get available groups
		$storage = PersonalCrm::get_globals()->storage;
		$groups = $storage->get_available_groups();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Show Application Password setup notice
			if ( Personal_CRM_CardDAV_Auth::is_application_passwords_available() ) :
				?>
				<div class="notice notice-info">
					<h3 style="margin-top: 0.5em;">🔐 Application Password Required</h3>
					<p><strong>For security, this CardDAV server does NOT accept your regular WordPress password.</strong></p>
					<p>You must create an Application Password:</p>
					<ol style="margin-left: 20px;">
						<li>Go to <a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>">your WordPress profile</a></li>
						<li>Scroll to "Application Passwords" section</li>
						<li>Enter name: <code>CardDAV - iPhone</code> (or your device name)</li>
						<li>Click "Add New Application Password"</li>
						<li><strong>Copy the generated password</strong> (shown only once!)</li>
						<li>Use that password in your CardDAV client</li>
					</ol>
					<p><strong>Benefits:</strong> Your admin password stays secure, revoke per-device, track usage.</p>
				</div>
				<?php
			else :
				?>
				<div class="notice notice-error">
					<h3 style="margin-top: 0.5em;">⚠️ Application Passwords Not Available</h3>
					<p><strong>CardDAV requires Application Passwords, but they're not available on this site.</strong></p>
					<p><strong>Requirements:</strong></p>
					<ul style="margin-left: 20px;">
						<li>WordPress 5.6 or higher</li>
						<li>HTTPS enabled (SSL certificate)</li>
					</ul>
					<p>Please update WordPress and enable SSL to use CardDAV securely.</p>
				</div>
				<?php
			endif;
			?>

			<div class="card">
				<h2><?php esc_html_e( 'CardDAV Server Information', 'personal-crm-carddav' ); ?></h2>

				<p><?php esc_html_e( 'Your Personal CRM is now accessible via CardDAV. You can sync your contacts with any CardDAV-compatible client.', 'personal-crm-carddav' ); ?></p>

				<h3><?php esc_html_e( 'Server Configuration', 'personal-crm-carddav' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'CardDAV Server URL', 'personal-crm-carddav' ); ?></th>
						<td>
							<code><?php echo esc_html( $base_url ); ?>/</code>
							<p class="description">
								<?php esc_html_e( 'Use this URL as the CardDAV server address in your client.', 'personal-crm-carddav' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Username', 'personal-crm-carddav' ); ?></th>
						<td>
							<code><?php echo esc_html( $current_user->user_login ); ?></code>
							<p class="description">
								<?php esc_html_e( 'Use your WordPress username.', 'personal-crm-carddav' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Password', 'personal-crm-carddav' ); ?></th>
						<td>
							<p class="description" style="color: #d63638; font-weight: 600;">
								⚠️ <?php esc_html_e( 'Use an Application Password (NOT your WordPress password)', 'personal-crm-carddav' ); ?>
							</p>
							<p class="description">
								<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>">
									<?php esc_html_e( 'Create Application Password →', 'personal-crm-carddav' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Available Address Books', 'personal-crm-carddav' ); ?></h3>

				<p><?php esc_html_e( 'Each group in your Personal CRM is available as a separate address book:', 'personal-crm-carddav' ); ?></p>

				<ul>
					<?php foreach ( $groups as $group ) : ?>
						<li>
							<strong><?php echo esc_html( $group->group_name ); ?></strong>:
							<code><?php echo esc_html( $base_url . '/' . $group->slug . '/' ); ?></code>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Setup Instructions by Platform', 'personal-crm-carddav' ); ?></h2>
				<p><?php esc_html_e( 'Choose your device or operating system:', 'personal-crm-carddav' ); ?></p>

				<style>
					.platform-tabs { margin: 20px 0; border-bottom: 2px solid #ddd; }
					.platform-tabs button {
						background: #f0f0f0;
						border: none;
						padding: 12px 20px;
						cursor: pointer;
						font-size: 14px;
						border-top-left-radius: 4px;
						border-top-right-radius: 4px;
						margin-right: 4px;
					}
					.platform-tabs button.active { background: #0073aa; color: white; font-weight: 600; }
					.platform-tabs button:hover { background: #0085ba; color: white; }
					.platform-content { display: none; padding: 20px 0; }
					.platform-content.active { display: block; }
					.platform-content h3 { margin-top: 0; color: #0073aa; }
					.platform-content ol { margin-left: 20px; }
					.platform-content li { margin-bottom: 10px; line-height: 1.6; }
					.platform-note { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; }
					.platform-note strong { color: #856404; }
					.download-link { display: inline-block; background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
					.download-link:hover { background: #0085ba; color: white; }
				</style>

				<div class="platform-tabs">
					<button class="platform-tab active" onclick="showPlatform(event, 'ios')">📱 iPhone/iPad</button>
					<button class="platform-tab" onclick="showPlatform(event, 'android')">🤖 Android</button>
					<button class="platform-tab" onclick="showPlatform(event, 'macos')">🍎 macOS</button>
					<button class="platform-tab" onclick="showPlatform(event, 'windows')">🪟 Windows</button>
					<button class="platform-tab" onclick="showPlatform(event, 'linux')">🐧 Linux</button>
					<button class="platform-tab" onclick="showPlatform(event, 'thunderbird')">✉️ Thunderbird</button>
				</div>

				<!-- iOS/iPadOS -->
				<div id="ios" class="platform-content active">
					<h3>iPhone & iPad (iOS/iPadOS)</h3>
					<p><strong>✅ Native support - no additional apps needed!</strong></p>
					<ol>
						<li>Open <strong>Settings</strong> on your device</li>
						<li>Scroll down and tap <strong>Contacts</strong></li>
						<li>Tap <strong>Accounts</strong></li>
						<li>Tap <strong>Add Account</strong></li>
						<li>Tap <strong>Other</strong> (at the bottom)</li>
						<li>Tap <strong>Add CardDAV Account</strong></li>
						<li>Enter the following:
							<ul>
								<li><strong>Server:</strong> <code><?php echo esc_html( parse_url( $base_url, PHP_URL_HOST ) ); ?></code></li>
								<li><strong>User Name:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password (NOT WordPress password)</li>
								<li><strong>Description:</strong> Personal CRM</li>
							</ul>
						</li>
						<li>Tap <strong>Next</strong> in the top right</li>
						<li>Wait for verification (may take a few seconds)</li>
						<li>Your contacts will start syncing automatically! 🎉</li>
					</ol>
					<div class="platform-note">
						<strong>💡 Tip:</strong> After setup, your CRM contacts will appear in the Contacts app alongside your other contacts. Changes sync automatically when you're online.
					</div>
				</div>

				<!-- Android -->
				<div id="android" class="platform-content">
					<h3>Android Devices</h3>
					<p><strong>Android requires a CardDAV sync app.</strong> We recommend DAVx⁵ (free & open source):</p>

					<h4>Option 1: DAVx⁵ (Recommended - Works on all Android devices)</h4>
					<ol>
						<li>Install <strong>DAVx⁵</strong> from:
							<ul>
								<li><a href="https://play.google.com/store/apps/details?id=at.bitfire.davdroid" target="_blank" class="download-link">📥 Google Play Store</a></li>
								<li><a href="https://f-droid.org/packages/at.bitfire.davdroid/" target="_blank" class="download-link">📥 F-Droid (free version)</a></li>
							</ul>
						</li>
						<li>Open DAVx⁵ and tap the <strong>+</strong> button</li>
						<li>Select <strong>"Login with URL and user name"</strong></li>
						<li>Enter the following:
							<ul>
								<li><strong>Base URL:</strong> <code><?php echo esc_html( $base_url ); ?>/</code></li>
								<li><strong>User name:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Tap <strong>Login</strong></li>
						<li>Select which address books to sync</li>
						<li>Grant permissions when prompted</li>
						<li>Your contacts will appear in the Contacts app! 🎉</li>
					</ol>

					<h4>Option 2: Native CardDAV (Samsung, Xiaomi, Some Other Manufacturers)</h4>
					<p>Some Android devices have built-in CardDAV support:</p>
					<ol>
						<li>Open <strong>Settings</strong></li>
						<li>Go to <strong>Accounts</strong> or <strong>Cloud and Accounts</strong></li>
						<li>Tap <strong>Add Account</strong></li>
						<li>Look for <strong>CardDAV</strong> or <strong>Exchange/Corporate</strong></li>
						<li>Enter your server details and credentials</li>
					</ol>
					<div class="platform-note">
						<strong>⚠️ Note:</strong> If your device doesn't have native CardDAV support, use DAVx⁵. It's the most reliable option for Android.
					</div>
				</div>

				<!-- macOS -->
				<div id="macos" class="platform-content">
					<h3>macOS Contacts</h3>
					<p><strong>✅ Native support - no additional apps needed!</strong></p>
					<ol>
						<li>Open the <strong>Contacts</strong> app</li>
						<li>In the menu bar, click <strong>Contacts > Settings</strong> (or Preferences)</li>
						<li>Click the <strong>Accounts</strong> tab</li>
						<li>Click the <strong>+</strong> button at the bottom left</li>
						<li>Select <strong>Add CardDAV Account</strong></li>
						<li>Select <strong>Manual</strong> (don't use automatic)</li>
						<li>Enter the following:
							<ul>
								<li><strong>Account Type:</strong> Manual</li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
								<li><strong>Server Address:</strong> <code><?php echo esc_html( $base_url ); ?>/</code></li>
							</ul>
						</li>
						<li>Click <strong>Sign In</strong></li>
						<li>Your contacts will start syncing! 🎉</li>
					</ol>
					<div class="platform-note">
						<strong>💡 Tip:</strong> After setup, your CRM contacts appear alongside iCloud and other contacts. Use Smart Groups to organize them.
					</div>
				</div>

				<!-- Windows -->
				<div id="windows" class="platform-content">
					<h3>Windows PC</h3>
					<p><strong>⚠️ Windows doesn't have native CardDAV support.</strong> Choose one of these apps:</p>

					<h4>Option 1: Thunderbird + CardBook (Free & Recommended)</h4>
					<ol>
						<li>Install <a href="https://www.thunderbird.net/" target="_blank" class="download-link">📥 Download Thunderbird</a> (free email/contacts app)</li>
						<li>Open Thunderbird</li>
						<li>Go to <strong>Tools > Add-ons and Themes</strong> (or press Ctrl+Shift+A)</li>
						<li>Search for <strong>"CardBook"</strong></li>
						<li>Install the CardBook add-on and restart Thunderbird</li>
						<li>Click the <strong>CardBook</strong> icon in the toolbar</li>
						<li>Right-click in the CardBook window and select <strong>New Address Book</strong></li>
						<li>Choose <strong>Remote > CardDAV</strong></li>
						<li>Enter the following:
							<ul>
								<li><strong>URL:</strong> <code><?php echo esc_html( $base_url ); ?>/[group-slug]/</code></li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Click <strong>Validate</strong> then <strong>OK</strong></li>
						<li>Your contacts will sync! 🎉</li>
					</ol>

					<h4>Option 2: eM Client (Commercial - Free for 2 accounts)</h4>
					<ol>
						<li>Download <a href="https://www.emclient.com/" target="_blank" class="download-link">📥 eM Client</a></li>
						<li>Open eM Client</li>
						<li>Go to <strong>Menu > Accounts</strong></li>
						<li>Click <strong>+</strong> to add a new account</li>
						<li>Choose <strong>Contacts (CardDAV)</strong></li>
						<li>Enter:
							<ul>
								<li><strong>Server:</strong> <code><?php echo esc_html( $base_url ); ?>/</code></li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Click <strong>Connect</strong></li>
						<li>Your contacts will appear in eM Client!</li>
					</ol>

					<h4>Option 3: Outlook (Requires Third-Party Plugin)</h4>
					<p>Microsoft Outlook doesn't natively support CardDAV. You'll need:</p>
					<ul>
						<li><a href="https://www.outlookdav.com/" target="_blank">Outlook CalDav Synchronizer</a> (free/paid plugin)</li>
						<li>Or use Thunderbird instead (recommended)</li>
					</ul>

					<div class="platform-note">
						<strong>💡 Recommendation:</strong> For Windows, we recommend <strong>Thunderbird + CardBook</strong> - it's free, open-source, and works great!
					</div>
				</div>

				<!-- Linux -->
				<div id="linux" class="platform-content">
					<h3>Linux Desktop</h3>
					<p>Choose your desktop environment:</p>

					<h4>GNOME (Ubuntu, Fedora, etc.) - Evolution</h4>
					<ol>
						<li>Open <strong>GNOME Contacts</strong> or <strong>Evolution</strong></li>
						<li>Click the hamburger menu (☰) or go to <strong>Edit > Accounts</strong></li>
						<li>Click <strong>+</strong> or <strong>Add Account</strong></li>
						<li>Select <strong>CardDAV</strong> or <strong>Other</strong></li>
						<li>Enter the following:
							<ul>
								<li><strong>Server:</strong> <code><?php echo esc_html( parse_url( $base_url, PHP_URL_HOST ) ); ?></code></li>
								<li><strong>Path:</strong> <code><?php echo esc_html( parse_url( $base_url, PHP_URL_PATH ) ); ?>/</code></li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Click <strong>Connect</strong> or <strong>Add</strong></li>
						<li>Your contacts will sync! 🎉</li>
					</ol>

					<h4>KDE Plasma - KAddressBook / Kontact</h4>
					<ol>
						<li>Open <strong>KAddressBook</strong> or <strong>Kontact</strong></li>
						<li>Go to <strong>Settings > Configure KAddressBook</strong></li>
						<li>Go to the <strong>Accounts</strong> section</li>
						<li>Click <strong>Add Account > DAV groupware resource</strong></li>
						<li>Select <strong>CardDAV</strong></li>
						<li>Enter:
							<ul>
								<li><strong>URL:</strong> <code><?php echo esc_html( $base_url ); ?>/</code></li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Click <strong>OK</strong></li>
						<li>Your contacts will sync!</li>
					</ol>

					<h4>Universal Option: Thunderbird</h4>
					<p>Works on any Linux distribution - follow the Windows Thunderbird instructions above!</p>
					<a href="https://www.thunderbird.net/" target="_blank" class="download-link">📥 Download Thunderbird for Linux</a>
				</div>

				<!-- Thunderbird (Cross-platform) -->
				<div id="thunderbird" class="platform-content">
					<h3>Mozilla Thunderbird (Windows, macOS, Linux)</h3>
					<p><strong>Free, open-source, and works great on all platforms!</strong></p>
					<ol>
						<li>If you don't have Thunderbird, download it:
							<ul>
								<li><a href="https://www.thunderbird.net/" target="_blank" class="download-link">📥 Download Thunderbird</a></li>
							</ul>
						</li>
						<li>Open Thunderbird</li>
						<li>Go to <strong>Tools > Add-ons and Themes</strong></li>
						<li>Search for <strong>"CardBook"</strong> and install it</li>
						<li>Restart Thunderbird</li>
						<li>Click the <strong>CardBook</strong> button in the toolbar</li>
						<li>Right-click in the CardBook sidebar and select <strong>New Address Book</strong></li>
						<li>Choose <strong>Remote > CardDAV</strong></li>
						<li>Enter the following:
							<ul>
								<li><strong>URL:</strong> <code><?php echo esc_html( $base_url ); ?>/[group-slug]/</code>
									<br><small>Replace [group-slug] with your address book slug from the list above</small>
								</li>
								<li><strong>Username:</strong> <code><?php echo esc_html( $current_user->user_login ); ?></code></li>
								<li><strong>Password:</strong> Your Application Password</li>
							</ul>
						</li>
						<li>Click <strong>Validate</strong> to test the connection</li>
						<li>Click <strong>OK</strong> to save</li>
						<li>Your contacts will sync automatically! 🎉</li>
					</ol>
					<div class="platform-note">
						<strong>💡 Pro Tip:</strong> Thunderbird can also sync your email and calendar. It's a complete communication hub!
					</div>
				</div>

				<script>
				function showPlatform(evt, platformName) {
					// Hide all platform content
					var contents = document.getElementsByClassName('platform-content');
					for (var i = 0; i < contents.length; i++) {
						contents[i].classList.remove('active');
					}

					// Remove active class from all tabs
					var tabs = document.getElementsByClassName('platform-tab');
					for (var i = 0; i < tabs.length; i++) {
						tabs[i].classList.remove('active');
					}

					// Show the selected platform
					document.getElementById(platformName).classList.add('active');
					evt.currentTarget.classList.add('active');
				}
				</script>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Features', 'personal-crm-carddav' ); ?></h2>
				<ul>
					<li>✅ <?php esc_html_e( 'Bidirectional sync: Changes made in your CardDAV client will be synced back to Personal CRM', 'personal-crm-carddav' ); ?></li>
					<li>✅ <?php esc_html_e( 'Multiple address books: Each group appears as a separate address book', 'personal-crm-carddav' ); ?></li>
					<li>✅ <?php esc_html_e( 'Rich contact data: Includes emails, phone numbers, birthdays, websites, and more', 'personal-crm-carddav' ); ?></li>
					<li>✅ <?php esc_html_e( 'Secure: Uses Application Passwords (not your admin password)', 'personal-crm-carddav' ); ?></li>
					<li>✅ <?php esc_html_e( 'Automatic sync: Changes sync whenever you\'re online', 'personal-crm-carddav' ); ?></li>
				</ul>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Troubleshooting', 'personal-crm-carddav' ); ?></h2>
				<h3>Common Issues:</h3>
				<ul>
					<li><strong><?php esc_html_e( 'Authentication fails:', 'personal-crm-carddav' ); ?></strong> <?php esc_html_e( 'Make sure you\'re using an Application Password, NOT your WordPress password. ', 'personal-crm-carddav' ); ?><a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"><?php esc_html_e( 'Create one here', 'personal-crm-carddav' ); ?></a></li>
					<li><strong><?php esc_html_e( '404 errors:', 'personal-crm-carddav' ); ?></strong> <?php esc_html_e( 'Go to Settings > Permalinks and click "Save Changes" to flush rewrite rules', 'personal-crm-carddav' ); ?></li>
					<li><strong><?php esc_html_e( 'Connection refused:', 'personal-crm-carddav' ); ?></strong> <?php esc_html_e( 'Some clients require HTTPS. Make sure your WordPress site has an SSL certificate.', 'personal-crm-carddav' ); ?></li>
					<li><strong><?php esc_html_e( 'Contacts not appearing:', 'personal-crm-carddav' ); ?></strong> <?php esc_html_e( 'Wait a few minutes for initial sync. Check that contacts exist in your Personal CRM group.', 'personal-crm-carddav' ); ?></li>
				</ul>
				<h3>Still need help?</h3>
				<p><?php esc_html_e( 'Check the', 'personal-crm-carddav' ); ?> <a href="<?php echo esc_url( 'https://github.com/akirk/personal-crm' ); ?>" target="_blank"><?php esc_html_e( 'Personal CRM documentation', 'personal-crm-carddav' ); ?></a> <?php esc_html_e( 'or open an issue on GitHub.', 'personal-crm-carddav' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Add CardDAV information to the Personal CRM dashboard sidebar
	 *
	 * @param array  $group_data     The group data
	 * @param object $current_group  The current group
	 */
	public function add_carddav_info_to_dashboard( $group_data, $current_group ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_url = home_url( '/carddav' );
		$addressbook_url = $base_url . '/' . $current_group->slug . '/';

		?>
		<div class="crm-card" style="margin-top: 20px;">
			<h3><?php esc_html_e( 'Sync to Devices', 'personal-crm-carddav' ); ?></h3>
			<p><?php esc_html_e( 'Sync these contacts to your phone, tablet, or computer:', 'personal-crm-carddav' ); ?></p>
			<code style="display: block; padding: 8px; background: #f0f0f0; word-break: break-all; margin-bottom: 10px;">
				<?php echo esc_html( $addressbook_url ); ?>
			</code>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=personal-crm-carddav' ) ); ?>">
					<?php esc_html_e( 'Setup instructions →', 'personal-crm-carddav' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
