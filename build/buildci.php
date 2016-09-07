<?php

require_once "./UtilsHelper.php";

$branch = getenv('CIRCLE_BRANCH');
$parts = explode('/', $branch);
$branch = $parts[1];
$allBranches = ['beta', 'stable'];
$buildRepoPath = '';
$newBuildNumber = '';
$version = '';

date_default_timezone_set('UTC');

echo "Detected Branch: ".$branch.PHP_EOL.PHP_EOL;

if (in_array($branch, $allBranches)) {
    $buildRepoPath = "/home/ubuntu/gitreposci/commerce-".$branch."/build/";

    echo "On branch `release/".$branch."`".PHP_EOL.PHP_EOL;
    putenv('COMMERCE_BRANCH='.$branch);
    putenv('COMMERCE_RELEASE_BRANCH=true');

    echo "Making folder at /home/ubuntu/gitreposci".PHP_EOL.PHP_EOL;
    mkdir('/home/ubuntu/gitreposci');

    foreach ($allBranches as $aBranch) {
        echo "Executing: cd /home/ubuntu/gitreposci/commerce-".$aBranch.";git clone https://github.com/takobell/commerce-".$aBranch.PHP_EOL.PHP_EOL;
        exec('cd /home/ubuntu/gitreposci;git clone https://github.com/takobell/commerce-'.$aBranch);
    }

    //$newBuildNumber = (int)getLastBuildNumberFromTag() + 1;
    $newBuildNumber = 1300;

    echo "Purging ".$buildRepoPath.PHP_EOL.PHP_EOL;
    UtilsHelper::purgeDirectory($buildRepoPath);

    echo "Copying from /home/ubuntu/Commerce/commerce/ to ".$buildRepoPath.'commerce/'.PHP_EOL.PHP_EOL;
    UtilsHelper::copyDirectory("/home/ubuntu/Commerce/commerce/", $buildRepoPath.'commerce/');

    echo "Copying from /home/ubuntu/Commerce/templates/ to ".$buildRepoPath.'templates/'.PHP_EOL.PHP_EOL;
    UtilsHelper::copyDirectory("/home/ubuntu/Commerce/templates/", $buildRepoPath.'templates/');

    updateVersionBuild();
    processFiles();
    cleanDestinationDirectories();
    processRepo();
}

function updateVersionBuild()
{
    global $buildRepoPath, $newBuildNumber, $version;

    $pluginPath = $buildRepoPath.'commerce/CommercePlugin.php';
    echo 'Loading the contents of CommercePlugin.php at: '.$pluginPath.PHP_EOL.PHP_EOL;
    $contents = file_get_contents($pluginPath);

    preg_match('/(\d\.\d{1,2})\.(\d){4}/', $contents, $matches);

    if ($matches && isset($matches[1]))
    {
        $version = $matches[1];
    }

    $variables = array(
        '0000' => $newBuildNumber,
    );

    $newContents = str_replace(
        array_keys($variables),
        array_values($variables),
        $contents
    );

    echo 'Saving CommercePlugin.php... ';
    file_put_contents($pluginPath, $newContents);
    echo 'Done.'.PHP_EOL.PHP_EOL;
}

function getLastBuildNumberFromTag()
{
    global $allBranches;
    $highestBuild = '';

    foreach ($allBranches as $branch) {
        echo 'Executing: cd /home/ubuntu/gitreposci/commerce-'.$branch.';git describe --tags $(git rev-list --tags --max-count=1)'.PHP_EOL.PHP_EOL;
        exec('cd /home/ubuntu/gitreposci/commerce-'.$branch.';git describe --tags $(git rev-list --tags --max-count=1) 2>&1', $output, $status);

        $output = implode(PHP_EOL, $output);
        $buildNumber = (int)$output;

        echo "Build Number: ".$buildNumber.PHP_EOL.PHP_EOL;

        if ($buildNumber) {
            if ($buildNumber > (int)$highestBuild) {
                $highestBuild = $buildNumber;
            }
        }
    }

    return $highestBuild;
}

function processFiles()
{
    global $buildRepoPath;

    echo('Beginning to process app files'.PHP_EOL.PHP_EOL);
    $extensions = ['html', 'txt', 'scss', 'css', 'js', 'php', 'config', ''];
    $allFiles = UtilsHelper::dirContents($buildRepoPath, $extensions);

    foreach ($allFiles as $file) {
        if (is_file($file)) {
            if (excludePathSegments($file)) {
                processFile($file);
            }
        }
    }

    echo('Finished processing app files'.PHP_EOL.PHP_EOL);
}

/**
 * @param $file
 */
function processFile($file)
{
    echo('Processing '.$file.'... ');

    $contents = $newContents = file_get_contents($file);

    // Normalize newlines
    $newContents = str_replace("\r\n", "\n", $newContents);
    $newContents = str_replace("\r", "\n", $newContents);

    $extension = pathinfo($file, PATHINFO_EXTENSION);

    $newContents = parseComments($newContents, $extension);
    saveContents($newContents, $contents, $file);

    echo(PHP_EOL);
}

function parseComments($contents, $extension)
{
    if ($extension == 'html') {
        $leftOnlyComment = '\<\!\-\-';
        $rightOnlyComment = '\-\-\>';
    } else {
        $leftOnlyComment = '\/\*';
        $rightOnlyComment = '\*\/';
    }

    // <!-- HIDE --> ... <!-- end HIDE -->
    return preg_replace("/[\t ]*{$leftOnlyComment}\s+HIDE\s+{$rightOnlyComment}[\t ]*\n(.*?\n)[\t ]*{$leftOnlyComment}\s+end HIDE\s+{$rightOnlyComment}[\t ]*\n/s", '', $contents);
}

function saveContents($newContents, $oldContents, $file)
{
    if ($newContents != $oldContents) {
        echo('Saving... ');
        file_put_contents($file, $newContents);
        echo('Done.');
    } else {
        echo('No changes.');
    }
}

function cleanDestinationDirectories()
{
    global $buildRepoPath;

    $dsStores = UtilsHelper::findFiles($this->_tempDir, array('fileTypes' => array('DS_Store'), 'level' => -1));

    echo ('Found '.count($dsStores).' DS_Store files. Nuking them.'.PHP_EOL);
    foreach ($dsStores as $dsStore) {
        unlink($dsStore);
    }
    echo ('Done nuking DS_Store files.'.PHP_EOL.PHP_EOL);

    $gitFolders = UtilsHelper::getGitFolders($buildRepoPath.'commerce/vendor/');

    if ($gitFolders) {
        echo('Found '.count($gitFolders).' .git folders. Nuking them.'.PHP_EOL);

        foreach ($gitFolders as $gitFolder) {
            echo('Path: '.$gitFolder.PHP_EOL);
            UtilsHelper::purgeDirectory($gitFolder);
            rmdir($gitFolder);
        }
        echo('Done nuking .git folders.'.PHP_EOL.PHP_EOL);
    }
}

function processRepo()
{
    global $buildRepoPath, $newBuildNumber;

    echo 'Setting git email.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git config --global user.email "brad@pixelandtonic.com" . 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Setting git name.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git config --global user.name "Brad Bell" . 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Adding all files to the repo.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git add . 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Committing all files to the repo.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git commit -a -m "Build '.$newBuildNumber.'" 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Tagging release.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git tag '.$newBuildNumber.' 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Pushing all files to the repo.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git push 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

    echo 'Pushing all tags to the repo.'.PHP_EOL.PHP_EOL;
    exec('cd '.$buildRepoPath.';git push --tags 2>&1', $output, $status);
    $output = implode(PHP_EOL, $output);
    echo 'Output: '.$output.PHP_EOL.PHP_EOL;

}

function excludePathSegments($path, $extraTests = array())
{
    $path = str_replace('\\', '/', $path);

    if (strpos($path, '/vendor/') !== false) {
        return false;
    }

    foreach ($extraTests as $test) {
        if (strpos($path, $test) !== false) {
            return false;
        }
    }

    return true;
}
