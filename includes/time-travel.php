<?php
/**
 * Time Travel functionality for viewing data as of a specific date
 */

namespace PersonalCRM;

class TimeTravel {
	/**
	 * Get the current simulated date from URL parameter or default
	 */
	public static function get_current_date() {
		if ( isset( $_GET['as_of'] ) && ! empty( $_GET['as_of'] ) ) {
			$date = $_GET['as_of'];
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && strtotime( $date ) !== false ) {
				return $date;
			}
		}

		return date( 'Y-m-d' );
	}

	/**
	 * Check if currently time traveling (as_of parameter is set)
	 */
	public static function is_time_traveling() {
		return isset( $_GET['as_of'] ) && ! empty( $_GET['as_of'] );
	}

	/**
	 * Initialize time travel - set simulated date on DateTime classes
	 */
	public static function init() {
		if ( self::is_time_traveling() ) {
			$date = self::get_current_date();
			DateTime::set_simulated_date( $date );
			DateTimeImmutable::set_simulated_date( $date );
		}
	}

	/**
	 * Add as_of parameter to URLs to maintain time travel context
	 */
	public static function add_to_urls( $params, $base_url ) {
		if ( self::is_time_traveling() && ! isset( $params['as_of'] ) ) {
			$params['as_of'] = $_GET['as_of'];
		}
		return $params;
	}

	/**
	 * Render header content showing current time travel date
	 */
	public static function render_header() {
		if ( self::is_time_traveling() ) {
			$current_date = self::get_current_date();
			?>
			<div style="font-size: 14px; color: #666; margin-top: 5px;">
				Viewing as of: <?php echo date( 'F j, Y', strtotime( $current_date ) ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Render footer navigation links for time travel
	 */
	public static function render_footer_links() {
		$is_time_traveling = self::is_time_traveling();

		if ( ! $is_time_traveling ) {
			$today = new DateTimeImmutable();
			$previous_month_first = $today->modify( 'first day of last month' )->format( 'Y-m-d' );
			$current_month_15th = $today->modify( 'first day of this month' )->modify( '+14 days' )->format( 'Y-m-d' );
			?>
			<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'as_of' => $previous_month_first ) ) ); ?>" class="footer-link">📅 <?php echo date( 'M j', strtotime( $previous_month_first ) ); ?></a>
			<?php if ( strtotime( $current_month_15th ) > time() ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'as_of' => $current_month_15th ) ) ); ?>" class="footer-link">📅 <?php echo date( 'M j', strtotime( $current_month_15th ) ); ?></a>
			<?php endif; ?>
			<?php
		} else {
			$current_date = self::get_current_date();
			$current_datetime = new DateTimeImmutable( $current_date );

			$last_month_first_day = $current_datetime->modify( 'first day of last month' );
			$next_month_first_day = $current_datetime->modify( 'first day of next month' );
			?><span>As of:</span>
			<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'as_of' => $last_month_first_day->format( 'Y-m-d' ) ) ) ); ?>" class="footer-link">📅 <?php echo $last_month_first_day->format( 'M j' ); ?></a>
			<strong class="footer-link">📅 <?php echo $current_datetime->format( 'M j' ); ?></strong>
			<?php if ( $next_month_first_day < new DateTime() ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'as_of' => $next_month_first_day->format( 'Y-m-d' ) ) ) ); ?>" class="footer-link">📅 <?php echo $next_month_first_day->format( 'M j' ); ?></a>
			<?php endif; ?>
			<a href="?<?php echo http_build_query( array_diff_key( $_GET, array( 'as_of' => '' ) ) ); ?>" class="footer-link">📅 Today</a>
			<?php
		}
	}
}
