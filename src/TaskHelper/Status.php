<?php declare( strict_types=1 );

namespace BotRiconferme\TaskHelper;

/**
 * Status codes for a task.
 */
enum Status: int {
	/** Nothing to do */
	case NOTHING = 0;
	/** Everything's fine */
	case GOOD = 1;
	/** Non-fatal errors */
	case ERROR = 3;

	public function combinedWith( self $other ): self {
		return self::from( $this->value | $other->value );
	}

	public function isOK(): bool {
		return $this === self::NOTHING || $this === self::GOOD;
	}
}
