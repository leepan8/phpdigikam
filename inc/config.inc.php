<?php
/*
Copyright 2006-2017
Author: Thorben Kröger <thorbenk@gmx.net>
        Laurent Bovet <laurent.bovet@windmaster.ch>
        Matt Martin

This file is part of phpdigikam

phpdigikam is free software; you can redistribute it
and/or modify it under the terms of the GNU General
Public License as published by the Free Software Foundation;
either version 2, or (at your option)
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/
// C O N F I G / / / / / / / / / / / / 

//Language
require_once('lang/en.lang.php');

//Title for Web Page
$_config['AlbumTitle'] = "Martin Photo Album";

//Albums to hide
$_config['restrictedAlbums'] = "Albums.id NOT IN (1,1853)";

// tags to hide
$_config['restrictedTags'] = "tagid=154";

//Paths

// The database file
$_config['digikamDb'] = "mysql:host=localhost;port=3306;dbname=digikam";
$_config['dbuser'] = '';
$_config['dbpass'] = '';

// Where the photos are
$_config['photosPath'] = "/var/www/phpdigikam";

// Where the thumbnails are (if you copy them from ~/.thumbnails/large)
// or where they will be created
$_config['thumbnails'] = "thumbnails/";

// Utilities
$_config['convertBin'] = "/usr/bin/convert";
$_config['exifBin'] = "/usr/bin/exif";

// Leading path of the actual photo directory to compute the correct thumb hash
$_config['thumbHashPath'] = "/data/gallery/";

//Image and thumbnail sizes
$_config['thumbSize'] = "240";
$_config['imageSize'] = "720";

//Layout
$_config['numCols'] = "4";
$_config['photosPerPage'] = "40";
// / / / / / / / / / / / / / / / / / / 

//These should be automatically correct
$_config['selfDir']=substr($_SERVER['SCRIPT_FILENAME'], 0,
		                          strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
$_config['selfUrl']=substr($_SERVER['SCRIPT_NAME'], 0,
		                          strrpos($_SERVER['SCRIPT_NAME'], '/'));
$_config['scriptname']=substr(strrchr($_SERVER['SCRIPT_NAME'], '/'), 1);
?>
