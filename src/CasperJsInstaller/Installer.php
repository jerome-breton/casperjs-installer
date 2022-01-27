<?php

/*
 * This file is part of the "jerome-breton/casperjs-installer" package.
 *
 * The content is released under the MIT License. Please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CasperJsInstaller;

use Composer\Composer;
use Composer\IO\BaseIO as IO;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Script\Event;

class Installer
{
    const CASPERJS_NAME = 'CasperJS';

    const CASPERJS_TARGETDIR = '/jerome-breton/casperjs';

    const PACKAGE_NAME = 'jerome-breton/casperjs-installer';

    /** @var Composer */
    protected $composer;

    /** @var IO */
    protected $io;

    /**
     * @return Composer
     */
    public function getComposer()
    {
        return $this->composer;
    }

    /**
     * @param Composer $composer
     *
     * @return self
     */
    public function setComposer(Composer $composer)
    {
        $this->composer = $composer;
        return $this;
    }

    /**
     * @return IO
     */
    public function getIO()
    {
        return $this->io;
    }

    /**
     * @param IO $io
     * @return self
     */
    public function setIO(IO $io)
    {
        $this->io = $io;
        return $this;
    }

    public function __construct(Composer $composer, IO $io)
    {
        $this->setComposer($composer);
        $this->setIO($io);
    }

    /**
     * Operating system dependend installation of CasperJS
     */
    public static function install(Event $event)
    {
        //Install PhantomJs before CasperJs
        \PhantomInstaller\Installer::installPhantomJS($event);

        $installer = new static($event->getComposer(), $event->getIO());

        $config = $installer->getComposer()->getConfig();

        $version = $installer->getVersion();

        $binDir = $config->get('bin-dir');

        // the installation folder depends on the vendor-dir (default prefix is './vendor')
        $targetDir = $config->get('vendor-dir') . self::CASPERJS_TARGETDIR;

        $io = $installer->getIO();

        // do not install a lower or equal version
        $casperJsBinary = $installer->getCasperJsBinary($targetDir . '/bin/casperjs');
        if ($casperJsBinary) {
            $installedVersion = $installer->getCasperJsVersionFromBinary($casperJsBinary);
            if (version_compare($version, $installedVersion) !== 1) {
                $io->write('   - CasperJS v' . $installedVersion . ' is already installed. Skipping the installation.');
                return;
            }
        }

        // Download the Archive
        if ($installer->download($targetDir, $version)) {
            // Copy only the CasperJS binary from the installation "target dir" to the "bin" folder
            $installer->createCasperJsBinaryToBinFolder($targetDir, $binDir);
        }
    }

    /**
     * Returns a Composer Package, which was created in memory.
     *
     * @param string $targetDir
     * @param string $version
     * @return Package
     */
    public function createComposerInMemoryPackage($targetDir, $version)
    {
        $url = $this->getURL($version);

        // Create Composer In-Memory Package
        $versionParser = new VersionParser();
        $normVersion = $versionParser->normalize($version);

        $package = new Package(self::CASPERJS_NAME, $normVersion, $version);
        $package->setTargetDir($targetDir);
        $package->setInstallationSource('dist');
        $package->setDistType('zip');
        $package->setDistUrl($url);

        return $package;
    }

    /**
     * Returns the PhantomJS version number.
     *
     * Firstly, we search for a version number in the local repository,
     * secondly, in the root package.
     * A version specification of "dev-master#<commit-reference>" is disallowed.
     *
     * @return string $version Version
     */
    public function getVersion()
    {
        $version = null;
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

        foreach($packages as $package) {
            if($package->getName() === self::PACKAGE_NAME) {
                $version = $package->getPrettyVersion();
            }
        }

        // version was not found in the local repository, let's take a look at the root package
        if($version == null) {
            $version = $this->getRequiredVersion($this->composer->getPackage(), self::PACKAGE_NAME);
        }

        if($version == 'dev-master'){
            return 'master';
        }

        return $version;
    }

    /**
     * Returns the version for the given package either from the "require" or "require-dev" packages array.
     *
     * @param RootPackageInterface $package
     * @param string $packageName
     * @throws \RuntimeException
     * @return mixed
     */
    public function getRequiredVersion(RootPackageInterface $package, $packageName)
    {
        foreach (array($package->getRequires(), $package->getDevRequires()) as $requiredPackages) {
            if (isset($requiredPackages[$packageName])) {
                return $requiredPackages[$packageName]->getPrettyConstraint();
            }
        }
        throw new \RuntimeException('Can not determine required version of ' . $packageName);
    }

    /**
     * Create CasperJS launcher in the "bin" folder
     * Takes different "folder structure" of the archives and different "binary file names" into account.
     */
    public function createCasperJsBinaryToBinFolder($targetDir, $binDir)
    {
        if (!is_dir($binDir)) {
            mkdir($binDir);
        }
        $os = $this->getOS();
        $sourcePath = $targetDir.'/bin/casperjs';
        $phantomPath = $binDir . '/phantomjs';
        $targetPath = $binDir . '/casperjs';
        if ($os === 'windows') {
            // the suffix for binaries on windows is ".exe"
            $sourcePath .= '.exe';
            $phantomPath .= '.exe';
            $targetPath .= '.bat';
            file_put_contents($targetPath, "SET PHANTOMJS_EXECUTABLE=$phantomPath\n$sourcePath %*");
        }
        if ($os == 'linux' || $os == 'macosx') {
            file_put_contents($targetPath, "#!/bin/bash\nPHANTOMJS_EXECUTABLE=$phantomPath $sourcePath $*");
            chmod($targetPath, 0755);
        }
    }

    /**
     * Returns the URL of the CasperJs distribution
     *
     * @param string $version
     * @return string Download URL
     */
    public function getURL($version)
    {
        return 'https://github.com/n1k0/casperjs/zipball/'.$version;
    }

    /**
     * Returns the Operating System.
     *
     * @return string OS, e.g. macosx, windows, linux.
     */
    public function getOS()
    {
        $uname = strtolower(php_uname());

        if (strpos($uname, "darwin") !== false) {
            return 'macosx';
        } elseif (strpos($uname, "win") !== false) {
            return 'windows';
        } elseif (strpos($uname, "linux") !== false) {
            return 'linux';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get path to CasperJS binary.
     *
     * @param string $binDir
     * @return string|bool Returns false, if file not found, else filepath.
     */
    public function getCasperJsBinary($binDir)
    {
        $os = $this->getOS();

        $binary = $binDir . '/casperjs';

        if ($os === 'windows') {
            // the suffix for binaries on windows is ".exe"
            $binary .= '.exe';
        }

        return realpath($binary);
    }

    /**
     * Get CasperJS application version. Equals running "casperjs --version" on the CLI.
     *
     * @param string $pathToBinary
     * @return string CasperJS Version
     */
    public function getCasperJsVersionFromBinary($pathToBinary)
    {
        $io = $this->getIO();

        try {
            $cmd = escapeshellarg($pathToBinary) . ' --version';
            exec($cmd, $stdout);
            $version = $stdout[0];
            return $version;
        } catch (\Exception $e) {
            $io->warning("Caught exception while checking CasperJS version:\n" . $e->getMessage());
            $io->notice('Re-downloading CasperJS');
            return false;
        }
    }

    /**
     * The main download function.
     *
     * The package to download is created on the fly.
     * For downloading Composer\DownloadManager is used.
     *
     * @param string $targetDir
     * @param string $version
     * @return boolean
     */
    public function download($targetDir, $version)
    {
        if (defined('Composer\Composer::RUNTIME_API_VERSION') && version_compare(Composer::RUNTIME_API_VERSION, '2.0', '>=')) {
            // Composer v2 behavior
            return $this->downloadUsingComposerVersion2($targetDir, $version);
        } else {
            // Composer v1 behavior
            return $this->downloadUsingComposerVersion1($targetDir, $version);
        }
    }

    /**
     * @param string $targetDir
     * @param string $version
     *
     * @return bool
     */
    public function downloadUsingComposerVersion1($targetDir, $version)
    {
        $io = $this->getIO();
        $downloadManager = $this->getComposer()->getDownloadManager();

        $package = $this->createComposerInMemoryPackage($targetDir, $version);

        try {
            $downloadManager->download($package, $targetDir, null);
            return true;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $io->error(PHP_EOL . '<error>While downloading version ' . $version . ' the following error occurred: ' . $message . '</error>');
            return false;
        }
    }

    /**
     * @param string $targetDir
     * @param string $version
     *
     * @return bool
     */
    public function downloadUsingComposerVersion2($targetDir, $version)
    {
        $io = $this->getIO();
        $composer = $this->getComposer();
        $downloadManager = $composer->getDownloadManager();

        $package = $this->createComposerInMemoryPackage($targetDir, $version);

        try {
            $loop = $composer->getLoop();
            $promise = $downloadManager->download($package, $targetDir);
            if ($promise) {
                $loop->wait(array($promise));
            }
            $promise = $downloadManager->prepare('install', $package, $targetDir);
            if ($promise) {
                $loop->wait(array($promise));
            }
            $promise = $downloadManager->install($package, $targetDir);
            if ($promise) {
                $loop->wait(array($promise));
            }
            $promise = $downloadManager->cleanup('install', $package, $targetDir);
            if ($promise) {
                $loop->wait(array($promise));
            }
            return true;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $io->error(PHP_EOL . '<error>While downloading version ' . $version . ' the following error occurred: ' . $message . '</error>');
            return false;
        }
    }
}
