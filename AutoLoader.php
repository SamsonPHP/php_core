<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>
 * on 24.04.14 at 18:09
 */
 namespace samson\core;

/**
 * Generic SamsonPHP class auto loader
 * @author Vitaly Egorov <egorov@samsonos.com>
 * @copyright 2014 SamsonOS
 * @version 0.1.0
 */
class AutoLoader 
{
    /** Namespace separator character marker */
    const NS_SEPARATOR = '\\';

    /** Module files cache for optimizing */
    protected static $fileCache = array();

    /** Loaded namespaces cache */
    protected static $nameSpaces = array();

    /**
     * Generate "correct" class name dependently on the current PHP version,
     * if PHP version is lower 5.3.0 it does not supports namespaces and function
     * will convert class name with namespace to class name
     *
     * @param string $className Full class name with namespace
     *
     * @return string Supported class name
     */
    public static function className($className)
    {
        // If this is an old PHP version - remove namespaces
        if (__SAMSON_PHP_OLD === true) {

            // If class name has first symbol as NS_SEPARATOR - remove it to avoid ugly classes _samson_core_...
            if ($className{0} == self::NS_SEPARATOR) {
                $className = substr($className, 1);
            }

            // Convert NS_SEPARATOR to namespace
            $className = strtolower(str_replace(self::NS_SEPARATOR, '_', $className));
        }

        return $className;
    }

    /**
     * Return only class name without namespace
     *
     * @param string $className Full class name with namespace
     *
     * @return string Class name without namespace
     */
    public static function getOnlyClass( $className )
    {
        // Try to find namespace separator
        if (($p = strrpos( $className, self::NS_SEPARATOR )) !== false ) {
            $className = substr( $className, $p + 1 );
        }

        return $className;
    }

    /**
     * Return only namespace name from class name
     *
     * @param string $className Full class name with namespace
     *
     * @return string Namespace without class
     */
    public static function getOnlyNameSpace( $className )
    {
        // Try to find namespace separator
        if (($p = strrpos( $className, self::NS_SEPARATOR )) !== false ) {
            $className = substr( $className, 0, $p);
        }

        return $className;
    }

    /**
     * All our modules do not follow PSR-0 and classes located as they wish to, so we will have
     * to scan all files in module location and build all classes tree.
     *
     * @param string $className Class name without namespace
     * @param string $nameSpace Namespace name without class name
     * @param string $file Variable tot return path to class file location on success
     *
     * @return bool True if class file is found
     */
    protected static function oldModule($className, $nameSpace, & $file = null)
    {
        // Convert to linux path, windows will convert it automatically if necessary
        $ns = str_replace(self::NS_SEPARATOR, '/', $nameSpace);

        // Iterate all possible file structures
        $path = '';
        foreach (array('php', 'js', 'css', 'social') as $type) {
            // Build all possible module location for backward compatibility
            $path1 = __SAMSON_VENDOR_PATH.str_replace('samson/', 'samsonos/', $ns);
            $path2 = __SAMSON_VENDOR_PATH.str_replace('samson/', 'samsonos/'.$type.'/', $ns);
            $path3 = __SAMSON_VENDOR_PATH.str_replace('samson/', 'samsonos/'.$type.'_', $ns);

            if (file_exists($path1)) {
                $path = $path1;
                break;
            } else if (file_exists($path2)) {
                $path = $path2;
                break;
            } else if (file_exists($path3)) {
                $path = $path3;
                break;
            } else {
                return e('Class location ## not found by namespace ##', E_SAMSON_CORE_ERROR, array($className, $ns));
            }
        }

        $path .= '/';

        // Build files tree once for each namespace
        if (!isset(self::$fileCache[$nameSpace])) {
            self::$fileCache[$nameSpace] = File::dir($path, 'php');
        }

        // Trying to find class by class name in folder files collection
        if (sizeof($files = preg_grep( '/\/'.$className.'\.php/i', self::$fileCache[$nameSpace]))) {

            // If we have found several files matching this class
            if (sizeof($files) > 1) {
                return e('Cannot autoload class(##), too many files matched ##', E_SAMSON_CORE_ERROR, array($className,$files) );
            }

            // Return last array element as file path
            $file = end($files);

            // Everything is OK
            return true;

        } else { // Signal error
            return e('Cannot autoload class(##), class file not found in ##', E_SAMSON_CORE_ERROR, array($className,$path));
        }
    }

    /**
     * Auto loader main logic
     *
     * @param string $class Full class name with namespace
     *
     * @return bool False if something went wrong
     */
    public static function load($class)
    {
        // Get just class name without ns
        $className = self::getOnlyClass($class);
        // Get just ns without class name
        $nameSpace = self::getOnlyNameSpace($class );

        // If this is not core class
        if ($nameSpace != __NAMESPACE__) {
            // Convert namespace to base directory add class
            $path = __SAMSON_VENDOR_PATH.str_replace('\\', DIRECTORY_SEPARATOR, $nameSpace).DIRECTORY_SEPARATOR.$className.'.php';

            // Load class by file name
            if (file_exists($path)) {
                require($path);
            // old school compatibility will be removed when old modules will be updated
            } else if(self::oldModule($className, $nameSpace, $path)) {
                require($path);
                // Handle old module loading
            } else { // Signal error
                return e('Class name ## not found', E_SAMSON_CORE_ERROR, $class);
            }

        } else { // Load core classes separately using this class location
            require(__DIR__.'/'.$className.'.php');
        }

        return true;
    }
}

// Set this loader
spl_autoload_register(array('\samson\core\AutoLoader','load'));
 