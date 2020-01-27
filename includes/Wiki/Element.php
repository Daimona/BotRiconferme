<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Base class for wiki elements
 */
abstract class Element {
	/**
	 * Return a regex for matching the name of the element
	 *
	 * @param string $delimiter
	 * @return string
	 */
	abstract public function getRegex( string $delimiter = '/' ) : string;

	/**
	 * Get a regex matching any element in the given array
	 *
	 * @param self[] $elements
	 * @param string $delimiter
	 * @return string
	 * @todo Is this the right place?
	 */
	public static function regexFromArray( array $elements, string $delimiter = '/' ) : string {
		$bits = [];
		foreach ( $elements as $el ) {
			$bits[] = $el->getRegex( $delimiter );
		}
		return '(?:' . implode( '|', $bits ) . ')';
	}
}
