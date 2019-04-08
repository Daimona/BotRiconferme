<?php declare( strict_types=1 );

namespace BotRiconferme;

/**
 *
 */
class Message {
	/** @var string */
	private $key;
	/** @var string */
	private $value;

	/**
	 * @param string $key
	 */
	public function __construct( string $key ) {
		$this->key = $key;
		$this->value = Config::getInstance()->getWikiMessage( $key );
	}

	/**
	 * @param array $args
	 * @return self
	 */
	public function params( array $args ) : self {
		$this->value = strtr( $this->value, $args );
		return $this;
	}

	/**
	 * @return string
	 */
	public function text() : string {
		$this->parsePlurals();
		return $this->value;
	}

	/**
	 * Replace {{$plur|<amount>|sing|plur}}
	 */
	protected function parsePlurals() {
		$this->value = preg_replace_callback(
			'!\{\{$plur|(?P<amount>\d+)|(?P<sing>[^}|]+)|(?P<plur>[^|}]+)}}!',
			function ( $matches ) {
				return intval( $matches['amount'] ) > 1 ? trim( $matches['plur'] ) : trim( $matches['sing'] );
			},
			$this->value
		);
	}

	/**
	 * Get a localized version of article + day + time
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public static function getTimeWithArticle( int $timestamp ) : string {
		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$timeString = strftime( '%e %B alle %R', $timestamp );
		// Remove the left space if day has a single digit
		$timeString = ltrim( $timeString );
		$artic = in_array( date( 'j', $timestamp ), [ 8, 11 ] ) ? "l'" : "il ";
		setlocale( LC_TIME, $oldLoc );

		return $artic . $timeString;
	}

	/**
	 * Get a timestamp from a localized time string
	 *
	 * @param string $timeString
	 * @return int
	 * @todo Is there a better way?
	 */
	public static function getTimestampFromLocalTime( string $timeString ) : int {
		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$bits = strptime( $timeString, '%e %m %Y alle %H:%M' );
		$timestamp = mktime(
			$bits['tm_hour'],
			$bits['tm_min'],
			0,
			$bits['tm_mon'] + 1,
			$bits['tm_mday'],
			$bits['tm_year'] + 1900
		);
		setlocale( LC_TIME, $oldLoc );

		return $timestamp;
	}

	/**
	 * Given an array of data, returns a list of its elements using commas, and " e " before
	 * the last one. $emptyText can be used to specify the text in case $data is empty.
	 *
	 * @param array $data
	 * @param string $emptyText
	 * @return string
	 */
	public static function commaList( array $data, string $emptyText = 'nessuno' ) : string {
		if ( count( $data ) > 1 ) {
			$last = array_pop( $data );
			$ret = implode( ', ', $data ) . " e $last";
		} elseif ( $data ) {
			$ret = $data[0];
		} else {
			$ret = $emptyText;
		}

		return $ret;
	}
}
