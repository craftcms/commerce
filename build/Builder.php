<?php

/**
 * Craft Builder
 */
class Builder
{
	private $_finalRemoteRepoPath = '/www/eh21814/gitrepostc/';
	private $_sourceBaseDir;
	private $_composerPath = '/www/eh21814/composer.phar';
	private $_tempDir;
	private $_version;

	private $_args;
	private $_startTime;
	private $_repo;


	/**
	 * The first argument is expected to be the build number.
	 *
	 * @param $args
	 *
	 * @throws Exception
	 */
	public function __construct($args)
	{
		require __DIR__.'/lib/PHPGit/Command.php';
		require __DIR__.'/lib/PHPGit/Configuration.php';
		require __DIR__.'/lib/PHPGit/Repository.php';

		$this->_args = array_merge(array(
			'build'         => '1',
			'track'         => 'stable',
		), $args);

		$this->_sourceBaseDir = str_replace('\\', '/', realpath(__DIR__.'/..')).'/';
		$this->_tempDir = $this->_sourceBaseDir.UtilsHelper::UUID().'/';
		$this->_finalRemoteRepoPath .= 'commerce-'.$this->_args['track'].'/';

		UtilsHelper::createDir($this->_tempDir);

		date_default_timezone_set('UTC');
		$this->_releaseDate = new DateTime(null, new \DateTimeZone('UTC'));
	}

	public function run()
	{
		$this->_startTime = UtilsHelper::getBenchmarkTime();

		$this->updateComposer();
		$this->copyFiles();
		$this->updateVersionBuild();
		$this->processFiles();
		$this->cleanDirectories();
		$this->processRepo();

		$totalTime = UtilsHelper::getBenchmarkTime() - $this->_startTime;
		echo PHP_EOL.'Execution Time: '.$totalTime.' seconds.'.PHP_EOL;
	}

	public function updateComposer()
	{
		// Clear composer cache
		$this->_executeComposer('clear-cache');

		// Update composer itself.
		$this->_executeComposer('self-update');

		// Now update Commerce dependencies
		$this->_executeComposer('update');

		// Remove dev dependencies
		$this->_executeComposer('remove --update-no-dev');

		// Optimize that shiz.
		$this->_executeComposer('dumpautoload -o');
	}

	/**
	 *
	 */
	protected function copyFiles()
	{
		echo ('Copying code from '.$this->_sourceBaseDir.'templates to '.$this->_tempDir.'templates/'.PHP_EOL);
		UtilsHelper::createDir($this->_tempDir.'templates/');
		UtilsHelper::copyDirectory($this->_sourceBaseDir.'templates', $this->_tempDir.'templates/');
		echo ('Finished copying code from '.$this->_sourceBaseDir.'templates to '.$this->_tempDir.'templates/'.PHP_EOL.PHP_EOL);

		echo ('Copying code from '.$this->_sourceBaseDir.'commerce to '.$this->_tempDir.'commerce/'.PHP_EOL);
		UtilsHelper::createDir($this->_tempDir.'commerce/');
		UtilsHelper::copyDirectory($this->_sourceBaseDir.'commerce', $this->_tempDir.'commerce/');
		echo ('Finished copying code from '.$this->_sourceBaseDir.'commerce to '.$this->_tempDir.'commerce/'.PHP_EOL.PHP_EOL);

		echo ('Copying file from '.$this->_sourceBaseDir.'LICENSE.md to '.$this->_tempDir.'LICENSE.md'.PHP_EOL);
		UtilsHelper::copyFile($this->_sourceBaseDir.'LICENSE.md', $this->_tempDir.'LICENSE.md');
		echo ('Finished copying file from '.$this->_sourceBaseDir.'LICENSE.md to '.$this->_tempDir.'LICENSE.md'.PHP_EOL.PHP_EOL);
	}

	/**
	 *
	 */
	protected function cleanDirectories()
	{
		$dsStores = UtilsHelper::findFiles($this->_tempDir, array('fileTypes' => array('DS_Store'), 'level' => -1));

		echo ('Found '.count($dsStores).' DS_Store files. Nuking them.'.PHP_EOL);
		foreach ($dsStores as $dsStore)
		{
			unlink($dsStore);
		}
		echo ('Done nuking DS_Store files.'.PHP_EOL.PHP_EOL);

		$gitFolders = UtilsHelper::getGitFolders($this->_tempDir.'commerce/vendor/');

		if ($gitFolders)
		{
			echo('Found '.count($gitFolders).' .git folders. Nuking them.'.PHP_EOL);

			foreach ($gitFolders as $gitFolder)
			{
				echo('Path'.$gitFolder.PHP_EOL);
				UtilsHelper::purgeDirectory($gitFolder);
				rmdir($gitFolder);
			}
			echo('Done nuking .git folders.'.PHP_EOL.PHP_EOL);
		}
	}

	/**
	 *
	 */
	protected function processFiles()
	{
		echo ('Beginning to process app files'.PHP_EOL.PHP_EOL);
		$extensions = array('html', 'txt', 'scss', 'css', 'js', 'php', 'config', '');
		$allFiles = UtilsHelper::dirContents($this->_tempDir, $extensions);

		foreach ($allFiles as $file)
		{
			if (is_file($file))
			{
				if ($this->_excludePathSegments($file))
				{
					$this->processFile($file);
				}
			}
		}

		echo ('Finished processing app files'.PHP_EOL.PHP_EOL);
	}

	/**
	 * @param $file
	 */
	protected function processFile($file)
	{
		echo ('Processing '.$file.'... ');

		$contents = $newContents = file_get_contents($file);

		// Normalize newlines
		$newContents = str_replace("\r\n", "\n", $newContents);
		$newContents = str_replace("\r", "\n", $newContents);

		$extension = pathinfo($file, PATHINFO_EXTENSION);

		$newContents = $this->_parseComments($newContents, $extension);
		$this->_saveContents($newContents, $contents, $file);

		echo (PHP_EOL);
	}

	/**
	 *
	 */
	protected function updateVersionBuild()
	{
		$path = $this->_tempDir.'commerce/CommercePlugin.php';
		echo 'Loading the contents of CommercePlugin.php at '.$path.PHP_EOL;
		$contents = file_get_contents($path);

		preg_match('/(\d\.\d{1,2})\.(\d){4}/', $contents, $matches);

		if ($matches && isset($matches[1]))
		{
			$this->_version = $matches[1];
		}

		$variables = array(
			'0000' => $this->_args['build'],
		);

		$newContents = str_replace(
			array_keys($variables),
			array_values($variables),
			$contents
		);

		echo $path.PHP_EOL;
		file_put_contents($path, $newContents);
		echo 'Done.'.PHP_EOL.PHP_EOL;
	}

	/**
	 *
	 */
	protected function processRepo()
	{
		echo 'Copying everything from '.$this->_tempDir.' to '.$this->_finalRemoteRepoPath.'.'.PHP_EOL;
		UtilsHelper::copyDirectory($this->_tempDir, $this->_finalRemoteRepoPath);

		$repo = $this->_getRepo();
		echo 'Adding all files to the repo.'.PHP_EOL;
		$output = $repo->git('add .');
		echo 'Output: '.$output.PHP_EOL.PHP_EOL;

		echo 'Committing all files to the repo'.PHP_EOL;
		$output = $repo->git('commit -a -m "Build '.$this->_args['build'].'"');
		echo 'Output: '.$output.PHP_EOL.PHP_EOL;

		echo 'Pushing all files to remote'.PHP_EOL;
		$output = $repo->git('git push');
		echo 'Output: '.$output.PHP_EOL.PHP_EOL;
	}

	/**
	 * Removes // <!-- HIDE --> ... <!-- end HIDE --> comments
	 *
	 * @param $contents
	 * @param $extension
	 * @return mixed
	 */
	private function _parseComments($contents, $extension)
	{
		if ($extension == 'html')
		{
			$commentStyles = array(
				array('<!--', '-->'),
				array('{#', '#}'),
			);
		}
		else
		{
			$commentStyles = array(
				array('/*', '*/'),
			);
		}

		foreach ($commentStyles as $style)
		{
			$ld = preg_quote($style[0], '/');
			$rd = preg_quote($style[1], '/');
			$contents = preg_replace("/[\t ]*{$ld}\s+HIDE\s+{$rd}[\t ]*\n(.*?\n)[\t ]*{$ld}\s+end HIDE\s+{$rd}[\t ]*\n/s", '', $contents);
		}

		return $contents;
	}

	/**
	 * @param $newContents
	 * @param $oldContents
	 * @param $file
	 */
	private function _saveContents($newContents, $oldContents, $file)
	{
		if ($newContents != $oldContents)
		{
			echo ('Saving... ');
			file_put_contents($file, $newContents);
			echo ('Done.');
		}
		else
		{
			echo ('No changes.');
		}
	}

	/**
	 * @param string $path
	 * @param array $extraTests
	 * @return bool
	 */
	private function _excludePathSegments($path, $extraTests = array())
	{
		$path = str_replace('\\', '/', $path);

		if (strpos($path, '/vendor/') !== false)
		{
			return false;
		}

		foreach ($extraTests as $test)
		{
			if (strpos($path, $test) !== false)
			{
				return false;
			}
		}

		return true;
	}

	private function _executeComposer($command)
	{
		$command = "cd {$this->_sourceBaseDir};/usr/bin/php {$this->_composerPath} {$command}";

		echo 'Executing: '.$command.PHP_EOL;
		exec($command.' 2>&1', $output, $status);

		echo 'Status: '.$status.PHP_EOL;
		$output = implode(PHP_EOL, $output);
		echo 'Results: '.$output.PHP_EOL.PHP_EOL;

		if ($status == 1)
		{
			throw new Exception('There was an error running composer.');
		}
	}

	/**
	 * @return PHPGit_Repository
	 */
	private function _getRepo()
	{
		if (!isset($this->_repo))
		{
			$this->_repo = new \PHPGit_Repository($this->_finalRemoteRepoPath, false, array('git_executable' => '/usr/bin/git', 'win' => false));
		}

		return $this->_repo;
	}
}
