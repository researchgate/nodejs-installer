<?php
namespace Mouf\NodeJsInstaller;

use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;

class NodeJsInstaller
{

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var RemoteFilesystem
     */
    protected $rfs;

    /**
     * @var string
     */
    private $binDir;

    /**
     * @var string
     */
    private $vendorDir;

    /**
     * @param IOInterface $io
     * @param string      $binDir absolute path to the composer bin directory
     * @param string      $vendorDir
     */
    public function __construct(IOInterface $io, $binDir, $vendorDir)
    {
        $this->io = $io;
        $this->rfs = new RemoteFilesystem($io);
        $this->binDir = $binDir;
        $this->vendorDir = $vendorDir;
    }

    /**
     * Checks if NodeJS is installed globally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsGlobalInstallVersion()
    {
        $returnCode = 0;
        $output = "";

        ob_start();
        $version = exec("nodejs -v 2>&1", $output, $returnCode);
        ob_end_clean();

        if ($returnCode !== 0) {
            ob_start();
            $version = exec("node -v 2>&1", $output, $returnCode);
            ob_end_clean();

            if ($returnCode !== 0) {
                return;
            }
        }

        return ltrim($version, "v");
    }

    /**
     * Returns the full path to NodeJS global install (if available).
     */
    public function getNodeJsGlobalInstallPath()
    {
        $pathToNodeJS = $this->getGlobalInstallPath("nodejs");
        if (!$pathToNodeJS) {
            $pathToNodeJS = $this->getGlobalInstallPath("node");
        }

        return $pathToNodeJS;
    }

    /**
     * Returns the full install path to a command
     *
     * @param string $command
     *
     * @return string
     */
    public function getGlobalInstallPath($command)
    {
        if (Environment::isWindows()) {
            $result = trim(shell_exec("where /F ".escapeshellarg($command)), "\n\r");

            // "Where" can return several lines.
            $lines = explode("\n", $result);

            return $lines[0];
        } else {
            // We want to get output from stdout, not from stderr.
            // Therefore, we use proc_open.
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w"),  // stderr
            );
            $pipes = array();

            $process = proc_open("which ".escapeshellarg($command), $descriptorspec, $pipes);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Let's ignore stderr (it is possible we do not find anything and depending on the OS, stderr will
            // return things or not)
            fclose($pipes[2]);

            proc_close($process);

            return trim($stdout, "\n\r");
        }
    }

    /**
     * Checks if NodeJS is installed locally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsLocalInstallVersion()
    {
        $returnCode = 0;
        $output = "";

        if (!file_exists($this->binDir.DIRECTORY_SEPARATOR.'node')) {
            return;
        }

        ob_start();

        $version = exec($this->binDir.DIRECTORY_SEPARATOR.'node -v 2>&1', $output, $returnCode);

        ob_end_clean();

        if ($returnCode !== 0) {
            return;
        } else {
            return ltrim($version, "v");
        }
    }

    /**
     * Checks if NPM is installed locally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNPMLocalInstallVersion()
    {
        $returnCode = 0;
        $output = "";

        if (!file_exists($this->binDir.DIRECTORY_SEPARATOR.'npm')) {
            return;
        }

        ob_start();

        $version = exec($this->binDir.DIRECTORY_SEPARATOR.'npm -v 2>&1', $output, $returnCode);

        ob_end_clean();

        if ($returnCode !== 0) {
            return;
        } else {
            return $version;
        }
    }

    /**
     * Checks if Yarn is installed locally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getYarnLocalInstallVersion($targetDirectory)
    {
        $returnCode = 0;
        $output = "";

        if (
            !file_exists($this->binDir.DIRECTORY_SEPARATOR.'yarnpkg') ||
            !file_exists($targetDirectory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'yarn')
        ) {
            return;
        }

        ob_start();

        $version = exec($this->binDir.DIRECTORY_SEPARATOR.'yarnpkg --version', $output, $returnCode);

        ob_end_clean();

        if ($returnCode !== 0) {
            return;
        } else {
            return $version;
        }
    }

    /**
     * Returns URL based on version.
     * URL is dependent on environment
     * @param  string                   $version
     * @return string
     * @throws NodeJsInstallerException
     */
    public function getNodeJSUrl($version)
    {
        if (Environment::isWindows() && Environment::getArchitecture() == 32) {
            if (version_compare($version, '4.0.0') >= 0) {
                return "https://nodejs.org/dist/v".$version."/win-x86/node.exe";
            } else {
                return "https://nodejs.org/dist/v".$version."/node.exe";
            }
        } elseif (Environment::isWindows() && Environment::getArchitecture() == 64) {
            if (version_compare($version, '4.0.0') >= 0) {
                return "https://nodejs.org/dist/v".$version."/win-x64/node.exe";
            } else {
                return "https://nodejs.org/dist/v".$version."/x64/node.exe";
            }
        } elseif (Environment::isMacOS() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x86.tar.gz";
        } elseif (Environment::isMacOS() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x64.tar.gz";
        } elseif (Environment::isSunOS() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x86.tar.gz";
        } elseif (Environment::isSunOS() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x64.tar.gz";
        } elseif (Environment::isLinux() && Environment::isArm()) {
            if (version_compare($version, '4.0.0') >= 0) {
                if (Environment::isArmV6l()) {
                    return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-armv6l.tar.gz";
                } elseif (Environment::isArmV7l()) {
                    return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-armv7l.tar.gz";
                } elseif (Environment::getArchitecture() == 64) {
                    return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-arm64.tar.gz";
                } else {
                    throw new NodeJsInstallerException('NodeJS-installer cannot install Node on computers with ARM 32bits processors that are not v6l or v7l. Please install NodeJS globally on your machine first, then run composer again.');
                }
            } else {
                throw new NodeJsInstallerException('NodeJS-installer cannot install Node <4.0 on computers with ARM processors. Please install NodeJS globally on your machine first, then run composer again, or consider installing a version of NodeJS >=4.0.');
            }
        } elseif (Environment::isLinux() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-x86.tar.gz";
        } elseif (Environment::isLinux() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-x64.tar.gz";
        } else {
            throw new NodeJsInstallerException('Unsupported architecture: '.PHP_OS.' - '.Environment::getArchitecture().' bits');
        }
    }

    /**
     * Installs NodeJS
     *
     * @param  string $version
     * @param  string $targetDirectory
     *
     * @throws NodeJsInstallerException
     */
    public function install($version, $targetDirectory)
    {
        $this->io->write("Installing <info>NodeJS v".$version."</info>");
        $url = $this->getNodeJSUrl($version);
        $this->io->write("  Downloading from $url");

        $fileName = $this->vendorDir.DIRECTORY_SEPARATOR.pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);

        $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);
        // rfs is not adding a newline after finished
        $this->io->writeError('');

        if (!file_exists($fileName)) {
            throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
                .' directory is writable and you have internet connectivity');
        }

        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        if (!is_writable($targetDirectory)) {
            throw new NodeJsInstallerException("'$targetDirectory' is not writable");
        }

        if (!Environment::isWindows()) {
            // Now, if we are not in Windows, let's untar.
            $this->extractTo($fileName, $targetDirectory);

            // Let's delete the downloaded file.
            unlink($fileName);
        } else {
            // If we are in Windows, let's move.
            rename($fileName, $targetDirectory.'/'.basename($fileName));
        }
    }

    /**
     * @param string $versionConstraint
     * @param string $targetDirectory
     *
     * @throws NodeJsInstallerException
     */
    public function installNpm($versionConstraint, $targetDirectory)
    {
        $localNPMVersion = $this->getNPMLocalInstallVersion();
        $isInitialWindowsInstall = Environment::isWindows() && !$localNPMVersion;

        if ($isInitialWindowsInstall) {
            // We have to download the latest available version in a bin for Windows, then upgrade it:
            $url = 'https://nodejs.org/dist/npm/npm-1.4.12.zip';
            $npmFileName = $this->vendorDir.'/npm-1.4.12.zip';
            $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $npmFileName);
            // rfs is not adding a newline after finished
            $this->io->writeError('');

            $this->unzip($npmFileName, $targetDirectory);

            unlink($npmFileName);
        }

        if ($versionConstraint || $isInitialWindowsInstall) {
            $versionString = '';
            if ($versionConstraint) {
                $this->io->write("Installing <info>NPM ".$versionConstraint."</info>");
                $versionString = '@"'.$versionConstraint.'"';
            }

            $output = null;
            $returnCode = 0;
            exec('npm -g install npm'.$versionString, $output, $returnCode);
            if (!$localNPMVersion || $localNPMVersion !== $this->getNPMLocalInstallVersion()) {
                // clean cache if the npm version changed
                exec('npm cache clean');
            }
            if ($returnCode !== 0) {
                throw new NodeJsInstallerException('An error occurred while updating NPM to latest version.');
            }
        }
    }

    /**
     * @param string $version
     * @param string $targetDirectory
     *
     * @throws NodeJsInstallerException
     */
    public function installYarn($version, $targetDirectory)
    {
        if (!$version) {
            return;
        }

        $localYarnVersion = $this->getYarnLocalInstallVersion($targetDirectory);
        if ($localYarnVersion === $version) {
            // Nothing to do already up2date
            $this->io->write("<info>Yarn v".$version."</info> already installed");
            return;
        }

        if (Environment::isWindows()) {
            $this->io->write("Cannot install yarn on windows yet");
            return;
        }


        $this->io->write("Installing <info>Yarn v".$version."</info>");

        $url = 'https://github.com/yarnpkg/yarn/releases/download/v'.$version.'/yarn-v'.$version.'.tar.gz';
        $yarnFileName = $this->vendorDir.'/yarn-v'.$version.'.tar.gz';
        $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $yarnFileName);
        // rfs is not adding a newline after finished
        $this->io->writeError('');

        $output = $return_var = null;

        exec("rm -rf ".escapeshellarg($targetDirectory));

        $result = mkdir($targetDirectory, 0775, true);
        if ($result === false) {
            throw new NodeJsInstallerException("Unable to create directory ".$targetDirectory);
        }

        exec("tar -xf ".$yarnFileName." -C ".escapeshellarg($targetDirectory)." --strip 1", $output, $return_var);

        if ($return_var !== 0) {
            throw new NodeJsInstallerException("An error occurred while untaring Yarn ($yarnFileName) to $targetDirectory");
        }

        unlink($yarnFileName);
    }

    /**
     * Extract tar.gz file to target directory.
     *
     * @param string $tarGzFile
     * @param string $targetDir
     *
     * @throws NodeJsInstallerException
     */
    private function extractTo($tarGzFile, $targetDir)
    {
        // Note: we cannot use PharData class because it does not keeps symbolic links.
        // Also, --strip 1 allows us to remove the first directory.

        $output = $return_var = null;

        exec("tar -xf ".$tarGzFile." -C ".escapeshellarg($targetDir)." --strip 1", $output, $return_var);

        if ($return_var !== 0) {
            throw new NodeJsInstallerException("An error occurred while untaring NodeJS ($tarGzFile) to $targetDir");
        }
    }

    public function createBinScripts($targetDir, $isLocal, $yarnInstalled = false)
    {
        if (!file_exists($this->binDir)) {
            $result = mkdir($this->binDir, 0775, true);
            if ($result === false) {
                throw new NodeJsInstallerException("Unable to create directory ".$this->binDir);
            }
        }

        if (!Environment::isWindows()) {
            $this->createBinScript($targetDir, 'node', 'node', $isLocal);
            $this->createBinScript($targetDir, 'npm', 'npm', $isLocal);
            if ($yarnInstalled) {
                $this->createBinScript($targetDir.'/yarn', 'yarnpkg', 'yarnpkg', $isLocal);
            }
        } else {
            $this->createBinScript($targetDir, 'node.bat', 'node', $isLocal);
            $this->createBinScript($targetDir, 'npm.bat', 'npm', $isLocal);
            if ($yarnInstalled) {
                $this->createBinScript($targetDir.'/yarn', 'yarnpkg.bat', 'yarnpkg', $isLocal);
            }
        }
    }

    /**
     * Copy script into $binDir, replacing PATH with $fullTargetDir
     *
     * @param string $targetDir
     * @param string $scriptName
     * @param string $target
     * @param bool   $isLocal
     */
    private function createBinScript($targetDir, $scriptName, $target, $isLocal)
    {
        $content = file_get_contents(__DIR__.'/../bin/'.($isLocal ? "local/" : "global/").$scriptName);
        if ($isLocal) {
            $path = $this->makePathRelative($targetDir, $this->binDir);
        } else {
            if ($scriptName == "node") {
                $path = $this->getNodeJsGlobalInstallPath();
            } else {
                $path = $this->getGlobalInstallPath($target);
            }

            if (strpos($path, $this->binDir) === 0) { // we found the local installation that already exists.
                return;
            }
        }


        file_put_contents($this->binDir.'/'.$scriptName, sprintf($content, $path));
        chmod($this->binDir.'/'.$scriptName, 0755);
    }

    /**
     * Shamelessly stolen from Symfony's FileSystem. Thanks guys!
     * Given an existing path, convert it to a path relative to a given starting path.
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    private function makePathRelative($endPath, $startPath)
    {
        // Normalize separators on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $endPath = strtr($endPath, '\\', '/');
            $startPath = strtr($startPath, '\\', '/');
        }
        // Split the paths into arrays
        $startPathArr = explode('/', trim($startPath, '/'));
        $endPathArr = explode('/', trim($endPath, '/'));
        // Find for which directory the common path stops
        $index = 0;
        while (isset($startPathArr[$index]) && isset($endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            $index++;
        }
        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        $depth = count($startPathArr) - $index;
        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);
        $endPathRemainder = implode('/', array_slice($endPathArr, $index));
        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser.(strlen($endPathRemainder) > 0 ? $endPathRemainder : '');

        return (strlen($relativePath) === 0) ? './' : $relativePath;
    }

    private function unzip($zipFileName, $targetDir)
    {
        $zip = new \ZipArchive();
        $res = $zip->open($zipFileName);
        if ($res === true) {
            // extract it to the path we determined above
            $zip->extractTo($targetDir);
            $zip->close();
        } else {
            throw new NodeJsInstallerException("Unable to extract file $zipFileName");
        }
    }
}
