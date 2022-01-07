<?php declare( strict_types=1 );

namespace BotRiconferme\Utils;

class RegexUtils {
	/**
	 * Get a regex matching any element in the given array
	 *
	 * @param string $delimiter
	 * @param IRegexable ...$elements
	 * @return string
	 */
	public static function regexFromArray(
		string $delimiter = '/',
		IRegexable ...$elements
	): string {
		$bits = [];
		foreach ( $elements as $el ) {
			$bits[] = $el->getRegex( $delimiter );
		}
		return '(?:' . implode( '|', $bits ) . ')';
	}
}
