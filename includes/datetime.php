<?php
/**
 * DateTime wrapper classes that support simulated dates for time travel
 * These shadow the global \DateTime and \DateTimeImmutable classes within the PersonalCRM namespace
 */

namespace PersonalCRM;

class DateTime extends \DateTime {
	private static $simulated_date = null;

	public function __construct( $datetime = 'now', $timezone = null ) {
		if ( $datetime === 'now' && self::$simulated_date !== null ) {
			parent::__construct( self::$simulated_date, $timezone );
		} else {
			parent::__construct( $datetime, $timezone );
		}
	}

	public static function set_simulated_date( $date ) {
		self::$simulated_date = $date;
	}

	public static function clear_simulated_date() {
		self::$simulated_date = null;
	}

	public static function get_simulated_date() {
		return self::$simulated_date;
	}

	public static function createFromFormat( string $format, string $datetime, ?\DateTimeZone $timezone = null ): DateTime|false {
		$obj = parent::createFromFormat( $format, $datetime, $timezone );
		if ( $obj === false ) {
			return false;
		}
		$new = new self( '@' . $obj->getTimestamp() );
		$new->setTimezone( $obj->getTimezone() );
		return $new;
	}
}

class DateTimeImmutable extends \DateTimeImmutable {
	private static $simulated_date = null;

	public function __construct( $datetime = 'now', $timezone = null ) {
		if ( $datetime === 'now' && self::$simulated_date !== null ) {
			parent::__construct( self::$simulated_date, $timezone );
		} else {
			parent::__construct( $datetime, $timezone );
		}
	}

	public static function set_simulated_date( $date ) {
		self::$simulated_date = $date;
	}

	public static function clear_simulated_date() {
		self::$simulated_date = null;
	}

	public static function get_simulated_date() {
		return self::$simulated_date;
	}

	public static function createFromFormat( string $format, string $datetime, ?\DateTimeZone $timezone = null ): DateTimeImmutable|false {
		$obj = parent::createFromFormat( $format, $datetime, $timezone );
		if ( $obj === false ) {
			return false;
		}
		return new self( '@' . $obj->getTimestamp() );
	}
}
