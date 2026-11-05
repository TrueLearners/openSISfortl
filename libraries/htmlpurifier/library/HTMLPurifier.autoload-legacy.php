<?php

/**
 * Legacy autoloader compatibility shim.
 *
 * PHP 8 removed the global __autoload() hook. Register the same HTML Purifier
 * loader through SPL, which is available on all supported PHP versions here.
 */
spl_autoload_register(array('HTMLPurifier_Bootstrap', 'autoload'));

// vim: et sw=4 sts=4
