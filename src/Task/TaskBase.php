<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\ContextSource;
use BotRiconferme\Message\MessageProvider;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\TaskHelper\TaskDataProvider;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\WikiGroup;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Base framework for all kind of tasks and subtasks
 */
abstract class TaskBase extends ContextSource {
	/** @var string[] */
	protected array $errors = [];

	/**
	 * Final to keep calls linear in the TaskManager
	 */
	final public function __construct(
		LoggerInterface $logger,
		WikiGroup $wikiGroup,
		MessageProvider $mp,
		PageBotList $pbl,
		protected TaskDataProvider $dataProvider,
	) {
		parent::__construct( $logger, $wikiGroup, $mp, $pbl );
	}

	/**
	 * Entry point
	 */
	final public function run(): TaskResult {
		$class = ( new ReflectionClass( $this ) )->getShortName();
		$opName = $this->getOperationName();
		$this->getLogger()->info( "Starting $opName $class" );

		$status = $this->runInternal();

		$msg = match ( $status ) {
			Status::GOOD => ucfirst( $opName ) . " $class completed successfully.",
			Status::NOTHING => ucfirst( $opName ) . " $class: nothing to do.",
			// We're fine with it, but don't run other tasks
			Status::ERROR => ucfirst( $opName ) . " $class completed with warnings."
		};

		$this->getLogger()->info( $msg );
		return new TaskResult( $status, $this->errors );
	}

	/**
	 * Actual main routine.
	 */
	abstract protected function runInternal(): Status;

	/**
	 * How this operation should be called in logs
	 */
	abstract public function getOperationName(): string;

	protected function getDataProvider(): TaskDataProvider {
		return $this->dataProvider;
	}
}
