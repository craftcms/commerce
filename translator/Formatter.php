<?php

/**
 *
 */
class Formatter
{
	private $_sourcePath;
	private $_sourcePhraseAppPath;
	private $_destinationPath;

	/**
	 *
	 */
	public function init()
	{
		$this->_sourcePath = realpath(dirname(__FILE__).'/../commerce').'/';
		$this->_sourcePhraseAppPath = $this->_sourcePath.'../translator/PhraseAppFormat/';
		$this->_destinationPath = $this->_sourcePath.'translations/';
	}

	/**
	 *
	 */
	public function run()
	{
		$this->processFiles();
	}

	/**
	 * Gets all of the unique translatable messages within a build.
	 *
	 * @return array
	 */
	protected function processFiles()
	{
		$files = UtilsHelper::findFiles($this->_sourcePhraseAppPath, array('fileTypes' => array('php')));

		if ($files)
		{
			foreach ($files as $file)
			{
				$file = realpath($file);
				require_once($file);

				$fileName = str_replace('-', '_', pathinfo($file, PATHINFO_BASENAME));
				$newFile = "<?php\n\nreturn array(\n";

				foreach ($lang as $key => $value)
				{
					$key = str_replace("'", "\\'", $key);
					$value = str_replace("'", "\\'", $value);

					$newFile .= "\t'".$key."' => '".$value."',\n";
				}

				$newFile .= ');';

				file_put_contents($this->_destinationPath.strtolower($fileName), $newFile);
			}
		}

		return $newFile;
	}
}
