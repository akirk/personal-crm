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
			__( 'CardDAV Settings', 'personal-crm-carddav' ),
			__( 'CardDAV', 'personal-crm-carddav' ),
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
				<h2><?php esc_html_e( 'Client Setup Instructions', 'personal-crm-carddav' ); ?></h2>

				<h3><?php esc_html_e( 'macOS Contacts', 'personal-crm-carddav' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Open Contacts app', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Go to Contacts > Preferences > Accounts', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Click the + button to add a new account', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Select "Add CardDAV Account"', 'personal-crm-carddav' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: Server URL */
							esc_html__( 'Enter server: %s', 'personal-crm-carddav' ),
							'<code>' . esc_html( $base_url ) . '/</code>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Enter your WordPress username and password', 'personal-crm-carddav' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'iOS/iPadOS', 'personal-crm-carddav' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Open Settings > Contacts > Accounts', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Tap "Add Account"', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Select "Other" > "Add CardDAV Account"', 'personal-crm-carddav' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: Server URL */
							esc_html__( 'Enter server: %s', 'personal-crm-carddav' ),
							'<code>' . esc_html( parse_url( $base_url, PHP_URL_HOST ) ) . '</code>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Enter your WordPress username and password', 'personal-crm-carddav' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Thunderbird', 'personal-crm-carddav' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Install the "CardBook" add-on', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Right-click on "CardBook" in the sidebar', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Select "New Address Book" > "Remote" > "CardDAV"', 'personal-crm-carddav' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: Server URL */
							esc_html__( 'Enter URL: %s', 'personal-crm-carddav' ),
							'<code>' . esc_html( $base_url ) . '/[group-slug]/</code>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Enter your WordPress username and password', 'personal-crm-carddav' ); ?></li>
				</ol>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Features', 'personal-crm-carddav' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Bidirectional sync: Changes made in your CardDAV client will be synced back to Personal CRM', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Multiple address books: Each group appears as a separate address book', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Rich contact data: Includes emails, phone numbers, birthdays, websites, and more', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Secure: Uses WordPress authentication', 'personal-crm-carddav' ); ?></li>
				</ul>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Troubleshooting', 'personal-crm-carddav' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Make sure your server supports rewrite rules (most WordPress installations do)', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'If you get 404 errors, try visiting Settings > Permalinks and clicking "Save Changes" to flush rewrite rules', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'Some clients may require HTTPS - consider using SSL for your WordPress site', 'personal-crm-carddav' ); ?></li>
					<li><?php esc_html_e( 'If authentication fails, verify your WordPress username and password are correct', 'personal-crm-carddav' ); ?></li>
				</ul>
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
			<h3><?php esc_html_e( 'CardDAV Sync', 'personal-crm-carddav' ); ?></h3>
			<p><?php esc_html_e( 'Sync this address book with your devices:', 'personal-crm-carddav' ); ?></p>
			<code style="display: block; padding: 8px; background: #f0f0f0; word-break: break-all; margin-bottom: 10px;">
				<?php echo esc_html( $addressbook_url ); ?>
			</code>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=personal-crm-carddav' ) ); ?>">
					<?php esc_html_e( 'View setup instructions', 'personal-crm-carddav' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
