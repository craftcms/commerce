<?php

class PHPGit_Command
{
	/**
	 * @var string Real filesystem path of the repository
	 */
	protected $dir;

	/**
	 * @var string Git command to run
	 */
	protected $commandString;

	/**
	 * @var boolean Whether to enable debug mode or not
	 * When debug mode is on, commands and their output are displayed
	 */
	protected $debug;

	/**
	 * Instantiate a new Git command
	 *
	 * @param   string $dir real filesystem path of the repository
	 * @param          $commandString
	 * @param          $debug
	 */
	public function __construct($dir, $commandString, $debug)
	{
		$commandString = trim($commandString);

		$this->dir            = $dir;
		$this->commandString  = $commandString;
		$this->debug          = $debug;
	}

	/**
	 * @param bool $win
	 * @throws GitRuntimeException
	 * @return string
	 */
	public function run($win = false)
	{
		if ($win)
		{
			$commandToRun = sprintf('cd /d %s && %s', escapeshellarg($this->dir), $this->commandString);
		}
		else
		{
			$commandToRun = sprintf('cd %s && %s', escapeshellarg($this->dir), $this->commandString);
		}

		if($this->debug) {
			print $commandToRun."\n";
		}

		ob_start();
		passthru($commandToRun, $returnVar);
		$output = ob_get_clean();

		if($this->debug) {
			print $output."\n";
		}

		if(0 !== $returnVar) {
			// Git 1.5.x returns 1 when running "git status"
			if(1 === $returnVar && 0 === strncmp($this->commandString, 'git status', 10)) {
				// it's ok
			}
			else {
				throw new GitRuntimeException(sprintf(
					'Command %s failed with code %s: %s',
					$commandToRun,
					$returnVar,
					$output
				), $returnVar);
			}
		}

		return trim($output);
	}
}

/**
 *
 */
class GitRuntimeException extends RuntimeException {}
