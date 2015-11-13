<?php

/**
 *
 */
class Translator
{
	private $_sourcePath;
	private $_translationFileSavePath;
	private $_buildPath;
	private $_fileTypes;
	private $_exclusions;

	/**
	 *
	 */
	public function init()
	{
		$this->_sourcePath = realpath(dirname(__FILE__).'/../commerce').'/';
		$this->_translationFileSavePath = $this->_sourcePath.'../translator/SourceTranslations.php';
		$this->_buildPath = '/Users/Brad/Dropbox/Sites/craft.dev/craft/plugins/commerce/';
		$this->_fileTypes = array('php', 'html', 'js');
		$this->_exclusions = array(
			'/vendor',
		);
	}

	/**
	 *
	 */
	public function run()
	{
		echo 'Extracting messages from the build directory...'.PHP_EOL.PHP_EOL;
		$messages = $this->getMessages();

		echo 'Generating new source translation file...'.PHP_EOL.PHP_EOL;
		$this->generateSourceMessageFile($messages);
	}

	/**
	 * Gets all of the unique translatable messages within a build.
	 *
	 * @return array
	 */
	protected function getMessages()
	{
		$options = array();
		$options['fileTypes'] = $this->_fileTypes;
		$options['exclude'] = $this->_exclusions;

		$files = UtilsHelper::findFiles($this->_buildPath, $options);

		$messages = array();

		foreach ($files as $file)
		{
			$messages = array_merge($messages, $this->extractMessages($file));
		}

		$messages = array_unique(array_filter($messages));
		asort($messages);

		return $messages;
	}

	/**
	 * Extracts the messages from a file, by looking for Craft::t("message") or "message"|t
	 *
	 * @param string $fileName
	 * @return array
	 */
	protected function extractMessages($fileName)
	{
		echo "Extracting messages from $fileName...\n";

		$subject = file_get_contents($fileName);
		$messages = array();
		$pattern = '';
		$extension = pathinfo($fileName, PATHINFO_EXTENSION);

		// First match Craft::t(''), but only in .php files.
		if ($extension == 'php')
		{
			$pattern = '/\bCraft::t\s*\(\s*(\'.*?(?<!\\\\)\'|".*?(?<!\\\\)")\s*[,\)]/s';
		}
		// Match template syntax {{ ""|t }}, but only in .html files.
		elseif ($extension == 'html')
		{
			$pattern = '/(\'[^\']*?\'|"[^"]*?")\|t/m';
		}
		// Match JavaScript syntax craft.(''), but only in .js files.
		elseif ($extension == 'js')
		{
			$pattern = '/\bCraft\.t\s*\(\s*(\'.*?(?<!\\\\)\'|".*?(?<!\\\\)")\s*[,\)]/s';
		}

		$n = preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER);

		for ($i = 0; $i < $n; ++$i)
		{
			$message = $matches[$i][1];

			$message = trim($message, '"');
			$message = trim($message, "'");
			$messages[$message] = $message;
		}

		return $messages;
	}

	/**
	 * Outputs the new source translation file
	 *
	 * @param array $messages
	 */
	protected function generateSourceMessageFile($messages)
	{
		$content = "<?php\n\n".$this->_generateTranslationLines($messages);

		echo 'Saving '.$this->_translationFileSavePath.PHP_EOL;
		file_put_contents($this->_translationFileSavePath, $content);
	}

	/**
	 * Generates the message lines.
	 *
	 * @param array $messages
	 * @return string
	 */
	private function _generateTranslationLines($messages)
	{
		$lines = '';

		foreach ($messages as $key => $message)
		{
			$lines .= '$lang[\''.$key."'] = '".$message."';\n";
		}

		return $lines;
	}
}
