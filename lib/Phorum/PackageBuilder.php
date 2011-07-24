<?php
/**
 * This script implements the Phorum_PackageBuilder class.
 *
 * @author Maurice Makaay
 * @copyright Phorum
 * @package Phorum
 * @category DevTools
 */

define('PHORUM_ZIP_ARCHIVE',   1);
define('PHORUM_TARGZ_ARCHIVE', 2);

/**
 * The Phorum_PackageBuilder class.
 *
 * This class provides base functionality for packaging an application
 * into formats that can be used for distribution. This is an abstract
 * class. Dedicated build classes are derived from this class.
 *
 * @package Phorum
 */
abstract class Phorum_PackageBuilder
{
    /**
     * The name of the package to build.
     *
     * @var string
     */
    protected $_package_name;

    /**
     * The directory where the sources for the package to build are located.
     *
     * @var string
     */
    protected $_package_dir;

    /**
     * The working directory for building the package.
     *
     * @var string
     */
    protected $_build_dir;

    /**
     * The version of the package to build.
     * This property is filled by the getPackageVersion() method.
     *
     * @var string
     */
    protected $_version = NULL;

    /**
     * A callback that can be used for logging packaging operations.
     *
     * @var callback
     */
    protected $_log_callback = NULL;

    /**
     * A list of files to exclude from the package.
     * Files to exclude can be added to this list using the
     * registerExclude() method.
     *
     * @var array
     */
    protected $_exclude_files = array();

    /**
     * A list of directories to exclude from the package.
     * Directories to exclude can be added to this list using the
     * registerExclude() method.
     *
     * @var array
     */
    protected $_exclude_dirs = array();

    /**
     * Create a new Phorum PackageBuilder object.
     *
     * @param string $package_name
     *   The name for the package. This name is used in the resulting
     *   package file (<package_name>-<version>.tar.gz).
     *
     * @param string $package_dir
     *   The directory where the sources for the package to build are located.
     *
     * @param string $build_dir
     *   The working directory for building the package (default is "/tmp").
     *
     * @throws Exception
     *   when illegal parameters are used.
     */
    public function __construct(
        $package_name,
        $package_dir,
        $build_dir = "/tmp")
    {
        $this->_package_name = $package_name;
        $this->_package_dir  = realpath($package_dir);
        $this->_build_dir    = realpath($build_dir);

        // The build dir cannot be below the package dir. It would
        // result in an endless file copying loop.
        $check = substr($this->_build_dir, 0, strlen($this->_package_dir));
        if ($check == $this->_package_dir) {
            throw new Exception(
                "The module build dir cannot be at or below the " .
                "package source dir."
            );
        }

        if (!preg_match('/^[\w\-]+$/', $this->_package_name)) {
            throw new Exception(
                "Illegal format used for package_name '$this->_package_name' " .
                "(expected only letters, numbers, underscores and minus signs)"
            );
        }

        if (!is_dir($this->_package_dir)) throw new Exception(
           "Illegal package source dir '$package_dir' provided: not a directory"
        );

        if (!is_dir($this->_build_dir)) throw new Exception(
           "Illegal build dir '$this->_build_dir' provided: not a directory"
        );

        $this->_getPackageVersion();

        // Register some globally skipped files and directories.
        $this->registerExclude('.svn');
        $this->registerExclude('.git');

        ini_set('track_errors', TRUE);
    }

    /**
     * Setup the built-in screen logger as logging output.
     */
    public function enableScreenLogger()
    {
        $this->setLogger(array('Phorum_PackageBuilder', 'screenLogger'));
    }

    /**
     * Set a callback for logging purposes.
     * The callback will be called with information about the
     * packaging process.
     *
     * @param callable $callback
     */
    public function setLogger($callback)
    {
        if (!is_callable($callback)) throw new Exception(
            "The callback must be a callable object."
        );
        $this->_log_callback = $callback;
    }

    /**
     * A logger that can be used for the setLogger() method, which outputs
     * the log messages to the screen.
     *
     * @param string $message
     * @param integer $level
     */
    static public function screenLogger($message, $level)
    {
        if ($level) {
            print str_repeat(" ", 2 * ($level - 1));
            print "> ";
        }

        print "$message\n";
    }

    /**
     * Register one or more files to exclude from the package.
     *
     * It is possible to feed this method multiple files at once by
     * using multiple arguments or an array argument.
     *
     * @param string $file
     *   The relative path (below the package source directory) of the
     *   file to exclude.
     */ 
    public function registerExclude()
    {
        $args = func_get_args();
        foreach ($args as $arg)
        {
            if (is_array($arg)) {
                foreach ($arg as $subarg) {
                    $this->_registerExclude($subarg);
                }
            } else {
                $this->_registerExclude($arg);
            }
        }
    }

    protected function _registerExclude($path)
    {
        $full = "$this->_package_dir/$path";

        // If the exclude file does not exist, then we won't have to do
        // any further handling. The file will not be in the resulting
        // package anyway.
        if (!file_exists($full)) return;

        // Check if the excluded path is below the package source dir.
        $full = realpath($full);
        $a = substr($full, 0, strlen($this->_package_dir));
        $b = $this->_package_dir;
        if ($a !== $b) {
            throw new Exception(
                "Exclude path '$path' is not below the package source dir."
            );
        }

        $exclude = '.' . substr($full, strlen($this->_package_dir));

        if (is_dir($full)) {
            $this->_exclude_dirs[$exclude] = TRUE;
        } else {
            $this->_exclude_files[$exclude] = TRUE;
        }
    }

    /**
     * Build the distribution package in the build directory.
     *
     * @return array
     *   An array of paths to the built packages (zip and tar.gz).
     *
     * @throws Exception
     *   in case building fails for some reason.
     */
    public function build()
    {
        $package_id = $this->_getPackageId(); 
        $this->_log("");
        $this->_log("Building package $package_id");

        $this->_initializeBuildPackageDir();

        $this->_copyPackageFiles();

        $this->_updateDocumentationFiles();

        $output = array();
        $output[] = $this->_createPackageArchive(PHORUM_ZIP_ARCHIVE);
        $output[] = $this->_createPackageArchive(PHORUM_TARGZ_ARCHIVE);

        $this->_cleanupBuildPackageDir();

        $this->_log("");
        $this->_log("Package archives created:");
        foreach ($output as $file) {
             $this->_log($file, 1);
        }
        $this->_log("");

        return $output;
    }

    /**
     * Write a log message.
     *
     * @param string $message
     *   The message to log.
     *
     * @param integer $level
     *   The level for the message. This can be used to indicate
     *   sub, sub-sub, etc. messages. A logger can use this to
     *   display the log information hierarchically.
     *   Level 0 (the default) indicates a top-level message.
     */
    protected function _log($message, $level = 0)
    {
        if ($this->_log_callback !== NULL) {
          $cb = $this->_log_callback;
          call_user_func($cb, $message, $level);
        }
    }

    /**
     * Retrieve the version of the package.
     *
     * @return string
     */
    abstract protected function _getPackageVersion();

    /**
     * Retrieve the directory where the package is built.
     *
     * @return string
     */
    protected function _getBuildPackageDir()
    {
        $package_id = $this->_getPackageId();
        return $this->_build_dir . '/' . $package_id;
    }

    /**
     * Retrieve the package ID, which is a combination of the package
     * name and the package version.
     *
     * @return string
     */
    protected function _getPackageId()
    {
        return $this->_package_name . '-' . $this->_getPackageVersion();
    }

    /**
     * Retrieve the file name of the package archive to create.
     *
     * @param integer $type
     *   One of PHORUM_ZIP_ARCHIVE or PHORUM_TARGZ_ARCHIVE 
     * @return string
     */
    protected function _getOutputArchive($type)
    {
        $package_id = $this->_getPackageId();
        $output     = "$this->_build_dir/{$package_id}";

        switch ($type) {
            case PHORUM_ZIP_ARCHIVE   : $output .= '.zip'; break;
            case PHORUM_TARGZ_ARCHIVE : $output .= '.tar.gz'; break;
            default: throw new Exception("Unknown archive type: $type");
        }

        return $output;
    }

    protected function _initializeBuildPackageDir()
    {
        $build_package_dir = $this->_getBuildPackageDir();
        $this->_log("Initializing build dir: $build_package_dir ...", 1);

        $this->_rmtree($build_package_dir);

        if (!@mkdir($build_package_dir, 0750)) throw new Exception(
            "Unable to create build directory: $php_errormsg"
        );

        // Match the file permissions for the target directory with those
        // of the source directory.
        $perms = fileperms($this->_package_dir) & 511;
        if (!@chmod($build_package_dir, $perms)) {
           throw new Exception($php_errormsg);
        }
    }

    protected function _copyPackageFiles()
    {
        $build_package_dir = $this->_getBuildPackageDir();
        $this->_log("Copying package files to the build dir ...", 1);

        $cur_dir = getcwd();
        if ($cur_dir === FALSE) throw new Exception(
            "Unable to determine the current working directory."
        );

        try
        {
            if (!@chdir($this->_package_dir)) throw new Exception(
                "Unable to access the package sources directory: " .
                $php_errormsg
            );

            $files = new RecursiveIteratorIterator(
                         new RecursiveDirectoryIterator(
                             '.', FilesystemIterator::SKIP_DOTS
                         ),
                         RecursiveIteratorIterator::SELF_FIRST
                     );

            foreach ($files as $file)
            {
                $file = $file->getPathname();

                // Skip files that are related to subversion.
                if (preg_match('!/\.svn[$/]?!', $file)) {
                    continue;
                }

                if (isset($this->_exclude_files[$file])) {
                    $this->_log("Skipped excluded file: $file", 2);
                    continue;
                }

                foreach ($this->_exclude_dirs as $dir => $dummy)
                {
                    // Skip directory.
                    if ($file == $dir) {
                        $this->_log("Skipped excluded directory: $file", 2);
                        continue 2;
                    }

                    // Skip files below the skipped directory.
                    if (substr($file, 0, strlen($dir) + 1) == "$dir/") {
                        continue 2;
                    }
                }

                // Copy the file to the target build directory.
                if (is_dir($file)) {
                    if (!@mkdir("$build_package_dir/$file")) {
                        throw new Exception($php_errormsg);
                    }
                } else {
                    if (!@copy($file, "$build_package_dir/$file")) {
                        throw new Exception($php_errormsg);
                    }
                }

                // Match the file permissions for the copied file with those
                // of the source file.
                $perms = fileperms($file) & 511;
                if (!@chmod("$build_package_dir/$file", $perms)) {
                   throw new Exception($php_errormsg);
                }
            }
        }
        catch (Exception $e)
        {
            chdir($cur_dir);
            throw $e;
        }
    }

    protected function _updateDocumentationFiles()
    {
        $build_package_dir = $this->_getBuildPackageDir();

        foreach (array(
            'README',
            'INSTALL',
            'UPGRADE',
            'COPYING',
            'AUTHORS',
            'info.txt',
            'ChangeLog',
            'Changelog',
            'NEWS') as $doc_file)
        {
            $file = "$build_package_dir/$doc_file";
            if (file_exists($file))
            {
                $data = $orig_data = @file_get_contents($file);
                if ($data === FALSE) throw new Exception(
                    "Unable to read $doc_file: $php_errormsg"
                );

                $data = $this->_substituteTags($data);

                if ($data !== $orig_data) {
                    if (!@file_put_contents($file, $data)) {
                        throw new Exception(
                            "Unable to write $doc_file: $php_errormsg"
                        );
                    }
                    $this->_log("Updated $doc_file", 2);
                }
            }
        }
    }

    protected function _substituteTags($data)
    {
        $data = str_replace('@VERSION@', $this->_getPackageVersion(), $data);
        $data = str_replace('@PACKAGE@', $this->_getPackageId(),      $data);

        return $data;
    }

    /**
     * Create a packaged archive.
     *
     * @param integer $type
     *   One of PHORUM_ZIP_ARCHIVE or PHORUM_TARGZ_ARCHIVE 
     *
     * @return string
     *   The filename of the created archive.
     */
    protected function _createPackageArchive($type)
    {
        $output        = $this->_getOutputArchive($type);
        $build_pkg_dir = $this->_getBuildPackageDir();
        $build_dir     = dirname($build_pkg_dir);
        $pkg_dir       = basename($build_pkg_dir);

        $this->_log("Creating the package archive ...", 1);

        if (file_exists($output)) {
            if (!@unlink($output)) throw new Exception($php_errormsg);
        }

        $path = getenv("PATH");
        putenv("PATH=/bin:/usr/bin");
        switch ($type)
        {
            case PHORUM_TARGZ_ARCHIVE:
                system("tar -C $build_dir -zcf $output $pkg_dir", $exit);
                if ($exit != 0) throw new Exception(
                    "The tar program exited with a non-zero exit code."
                );
                break;

            case PHORUM_ZIP_ARCHIVE:
                system("cd $build_dir; zip -qr $output $pkg_dir", $exit);
                if ($exit != 0) throw new Exception(
                    "The zip program exited with a non-zero exit code."
                );
                break;

            default:
                throw new Exception("Unknown archive type: $type");
                break;
        }
        putenv("PATH=$path");

        return $output;
    }

    protected function _cleanupBuildPackageDir()
    {
        $build_package_dir = $this->_getBuildPackageDir();
        $this->_log("Cleaning up build dir ...", 1);

        $this->_rmtree($build_package_dir);
    }

    /**
     * Delete a directory tree recursively.
     *
     * @param string $path
     *   The path for the directory to delete.
     */
    protected function _rmtree($path)
    {
        if (!is_dir($path)) return;

        $files = new RecursiveIteratorIterator(
                     new RecursiveDirectoryIterator(
                         $path,
                         FilesystemIterator::SKIP_DOTS
                     ),
                     RecursiveIteratorIterator::CHILD_FIRST
                 );

        foreach ($files as $file)
        {
            if (is_dir($file)) {
                if (!@rmdir($file)) throw new Exception(
                    "Unable to delete directory: " .
                    $php_errormsg
                );
            }
            else {
                if (!@unlink($file)) throw new Exception(
                    "Unable to delete file: " .
                    $php_errormsg
                );
            }
        }

        if (!@rmdir($path)) throw new Exception(
            "Unable to delete directory: " .
            $php_errormsg
        );
    }
}

