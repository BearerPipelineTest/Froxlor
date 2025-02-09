#!/usr/bin/php
<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2018 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright (c) the authors
 * @author Froxlor team <team@froxlor.org> (2018-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package Cron
 *         
 */

// Check if we're in the CLI
if (@php_sapi_name() !== 'cli') {
	die('This script will only work in the shell.');
}

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

// give control to command line handler
try {
	\Froxlor\Cli\ConfigServicesCmd::processParameters($argc, $argv);
} catch (Exception $e) {
	\Froxlor\Cli\ConfigServicesCmd::printerr($e->getMessage());
	exit(1);
}
