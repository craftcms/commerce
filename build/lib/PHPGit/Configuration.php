<?php

/**
 * Simple PHP wrapper for Git configuration
 *
 * @link      http://github.com/ornicar/php-git-repo
 * @version   1.3.0
 * @author    Moritz Schwoerer <moritz.schwoerer at gmail dot com>
 * @license   MIT License
 *
 * Documentation: http://github.com/ornicar/php-git-repo/blob/master/README.markdown
 * Tickets:       http://github.com/ornicar/php-git-repo/issues
 */
class PHPGit_Configuration
{
	const USER_NAME = 'user.name';
	const USER_EMAIL = 'user.email';

	/**
	 * Holds the actual configuration
	 * @var array
	 */
	protected $configuration = array();

	/**
	 * Holds the Git repository instance.
	 * @var PHPGit_Repository
	 */
	protected $repository;

	/**
	 * @param PHPGit_Repository $gitRepo
	 */
	public function __construct(PHPGit_Repository $gitRepo)
	{
		$this->repository = $gitRepo;
	}

	/**
	 * Get a config option
	 *
	 * @param string $configOption The config option to read
	 * @param mixed  $fallback  Value will be returned, if $configOption is not set
	 *
	 * @return string
	 */
	public function get($configOption, $fallback = null)
	{
		if (isset($this->configuration[$configOption])) {
			$optionValue = $this->configuration[$configOption];
		} else {
			if (array_key_exists($configOption, $this->configuration)) {
				$optionValue = $fallback;
			}

			try {
				$optionValue = $this->repository->git(sprintf('config --get ' . $configOption));
				$this->configuration[$configOption] = $optionValue;
			} catch (GitRuntimeException $e) {
				$optionValue = $fallback;
				$this->configuration[$configOption] = null;
			}
		}

		return $optionValue;
	}

	/**
	 * Set or change a *repository* config option
	 *
	 * @param string $configOption
	 * @param mixed  $configValue
	 */
	public function set($configOption, $configValue)
	{
		$this->repository->git(sprintf('config --local %s %s', $configOption, $configValue));
		unset($this->configuration[$configOption]);
	}

	/**
	 * Removes a option from local config
	 *
	 * @param string $configOption
	 */
	public function remove($configOption)
	{
		$this->repository->git(sprintf('config --local --unset %s', $configOption));
		unset($this->configuration[$configOption]);
	}

}
