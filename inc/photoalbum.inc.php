<?php
/*
Copyright 2006-2011
Author: Thorben Kröger <thorbenk@gmx.net>
        Laurent Bovet <laurent.bovet@windmaster.ch>

Updated 2017 Matt Martin

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

require_once('stopwatch.inc.php');

/**
 * Main class.
 */
class Photoalbum {
	function __construct() {
		global $_config;
		global $i18n;

		$this->_stopwatch = new Stopwatch();

		//Parse this page's URL
		$this->parseUrl();

		//If we have to display the setup page return and do not
		//connect to the database yet
		if (isset($_GET['setup'])) {
			require_once('lang/en.lang.php');
			require_once('inc/setup_forms.inc.php');

			include('inc/header.inc.html');
			return;
		}

		if (!isset($_GET['partialpage'])) include('inc/header.inc.html');

		require_once('config.inc.php');
		require_once('informativepdo.inc.php');
		require_once('imagespagedata.inc.php');
		require_once('tagtree.inc.php');

		//Connect to database and load tag data
                $this->_db = new InformativePDO($_config['digikamDb'],$_config['dbuser'],$_config['dbpass']);
		$this->_tagTree = new TagTree($this->_db);

		//Link to homepage only if not viewing image
		if (!isset($_GET['image']) && !isset($_GET['partialpage'])) {
			$lnkstr=$_config["selfUrl"]."/".$_config["scriptname"].'?minRating=';
			printf("<table width=100%% bgcolor=#303030 style='margin-bottom:-2em;border:0px collapse'><tr style='margin-bottom:-1em'><td width=80%%><b>%s</b></td><td><p style=\"float:right;margin:0\">\n",$_config['AlbumTitle']);
	
			printf("\t<a href=\"%s\">", $lnkstr.$_GET['minRating']);
			printf("<img src=\"%s/icons/home.gif\" alt=\"Home\" border=\"0\" /></a>",$_config["selfUrl"]);
                        # Make links for image raring filter
			printf("\tMin Rating:");
			foreach( array('-1','1','2','3','4','5') as $rtg) {
				if ($rtg=='-1') $vrtg='All';
				else $vrtg=$rtg;
				if ($rtg==$_GET['minRating']) $vrtg="<font color=red>".$vrtg."</font>";
				printf("&nbsp<a href=\"".$_SERVER['PHP_SELF'].'?minRating='.$rtg."\">".$vrtg."</a>");
			};
			printf("\n</p></td></tr></table>\n\n");
		}

		//Call the different album functions
		if (isset($_GET['album'])) {
			$this->htmlAlbumPage($_GET['album']);
		} elseif (isset($_GET['tag'])) {
			$this->htmlTagPage($_GET['tag']);
		} elseif (isset($_GET['image'])) {
			$this->htmlFullsizeImagePage($_GET['image']);
                } elseif (isset($_GET['randomthumb'])) {
                        $this->htmlRandomThumbPage();
		} elseif (isset($_GET['update'])) {
			require_once('inc/shellscript.inc.php');
			new UpdateScript($this->_db);
		} else { // Otherwise assume top level index page
			echo '<table width="100%"><tr><td width=60% align="left" valign=top>';
			$this->htmlAlbumList();
			echo '</td><td width="4%">&nbsp;</td><td width=35% align="left" valign="top">';
			$this->htmlTagTree();
			echo '</td></tr></table>';
		}
	}

	function __destruct() {
		global $i18n;

		if (!isset($_GET['partialpage'])) {
			echo '<br /><br /><p align="right" class="tiny">';

			if (isset($_GET['profile'])) {
				printf($i18n['debugFooter'], $this->_db->queryCount(),
							round($this->_stopwatch->stop(), 2));
			}

			echo "</p>\n";

			include('footer.inc.html');
		}
	}

	/**
	 * Here we take the script's URL and examine it.
	 * Depending on what is found, the appropriate index is set in the
	 * $_GET array just as if we'd passed ?arg=value
	 * This eliminates the use of "?" and "&" which makes it more
	 * wget friendly
	 */
	private function parseUrl() {
		//Probably mod_rewrite could be used instead

		$matches = array();

		if (preg_match('@/tag/([0-9]+)@',
			$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['tag'] = $matches[1];
		} elseif (preg_match("@/image/(.*)/(.*).html@U",
						$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['image'] = $matches[1]."/".$matches[2];
		} elseif (preg_match("@/album/([0-9]+)@",
						$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['album'] = $matches[1];
		} elseif (preg_match('@/update@U',
						$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['update'] = true;
		} elseif (preg_match('@/setup@U',
						$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['setup'] = true;
		} elseif (preg_match('@/randomthumb@U',$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['randomthumb'] = true;
			$_GET['partialpage'] = true;
		}
		if (!isset($_GET['minRating'])) $_GET['minRating'] = "-1";
		//Check filename for page_number.html
		if (preg_match('@/page_([0-9]+).html@',
			$_SERVER['REQUEST_URI'], $matches) > 0) {
			$_GET['page'] = $matches[1];
		}
	}

/* Show image rating */
        private function htmlImageRating($imageId) {
                $imageRt = $this->_db->query('SELECT rating from imageinformation where imageid='.$imageId);
		$rows=$imageRt->fetchAll();
		foreach ($rows as $row) {
			if ($row[0]>0) 
				echo "<p style='margin-top:-0.2em;margin-bottom:-1em'><font size=+2>".str_repeat("*",$row[0])."</font></p>\n";
		};
	}
 
	/**
	 * Print all the image tags belonging to the image with $imageId
	 * for the thumbnail view
	 */
	private function htmlTagsForImage($imageId) {
		$imageTags = $this->_db->query(
			'SELECT Tags.name, Tags.id FROM Tags INNER JOIN ImageTags'.
			' ON (ImageTags.tagid = Tags.id)'.
			' WHERE ImageTags.imageid = '.$imageId
		);
		$rows = $imageTags->fetchAll();

		echo "\n<!--ImageTags //-->\n<table style='margin-top:0'>\n\t<tr>\n\t\t<td align=\"left\">\n";
                $pplstr="";
		$otherstr="";
		foreach ($rows as $row) {
			$tagstr=$this->_tagTree->htmlPathToTag($row['id']);
			if (strpos($tagstr,"People")!==false) {
				$person=substr($tagstr,strpos($tagstr,"&gt")+10,-4);
				if ((strlen($pplstr)-strrpos($pplstr,"<br>"))>190) $pplstr=$pplstr."<br>";
				$pplstr=$pplstr.$person.",";
			}	
			else {
				$tagstr=str_replace("_Digikam_Internal_Tags_","Digikam",$tagstr);
				$otherstr=$otherstr."\t\t\t".$tagstr."<br />\n";
				}
		}
		if (strlen($pplstr)>0) echo "<p class=tiny><b>".$pplstr."</b></p><br>";
		echo $otherstr;
		echo "\t\t</td>\n\t</tr>\n</table>\n\n";
	}

	private function thumbnailCell(&$img, $num, $param="", $quiet=0 ) {
		global $_config;
		$path = $this->stripLeadingSlash($img['path']);
		$thumb = $this->getThumbnailFileName($path);
		$thumb_path = $_config['thumbnails'].'/'.$thumb;
		if (!file_exists($thumb_path)) {
			if (!$quiet) 
				echo '<div id="wait'.$thumb.'" style="display:block;font-style: italic;">Generating thumbnail<span style="text-decoration: blink">...</span></div>';
			$this->createthumb($_config['photosPath'].'/images/'.$path, $thumb_path, 256, 256);
			if (!$quiet) 
				echo "<script>document.getElementById('wait".$thumb."').style.display='none'</script>";
		}
		$page = 1;
		if (isset($_GET["page"])) {
			$page = $_GET["page"];
		}

		$n = (($page - 1) * $_config['photosPerPage']) + $num;
		//echo "<a name='".$n."'/>";

		$the_date = explode('-', substr($img['modificationDate'], 0, 10));
		//<br />		
		echo '<p class="tiny" style="margin-bottom: .6em">'.$the_date[2].'.'.$the_date[1].'.'.$the_date[0].', '.substr($img['modificationDate'], 11, 20)."</p>\n";

		echo "\t\t\t".$this->mkLink('image', $path,"<img alt=\"{$path}\" src=\"{$_config['selfUrl']}/thumbnails/{$thumb}\" />", "{$param}&n={$n}&f=1")."\n";
		$this->htmlImageRating($img['id']);

		$this->htmlTagsForImage($img['id']);
	}

	/**
	 * Render the $pageData (of class ImagesPageData) as a page of photo
	 * thumbnails.
	 * Manage the pages.
	 */
	private function thumbnailPage(&$pageData, $param="") {
		global $_config;
		global $i18n;

		if (count($pageData->imagesArray()) == 0) {
			printf("<h2>%s</h2><p>%s</p>\n", $i18n['noImagesOnPage'],
			       $i18n['noSuchTag']);
			return;
		}

		$numCols = $_config["numCols"];

		$page = isset($_GET["page"]) ? $_GET["page"] : 1;

		$this->pageNavigation($pageData);

		if (count($pageData->imagesArray()) > 1) {
				$arr = $pageData->imagesArray();
				echo "<p style='margin-bottom: -40px'>";
				echo $this->mkLink('image', $this->stripLeadingSlash($arr[0]['path']),
					"<img id='slideshow_button' src='".$_config["selfUrl"]."/icons/slideshow.png' />",
					$param."&n=".(($page - 1)*$_config['photosPerPage'])."&s=3");
				echo "</p>";
		}

		echo '<table cellpadding="5" width="100%">';
		echo "\t<tr>\n";

		$i = 0;
		foreach ($pageData->imagesArray() as $img) {
			if ($i > 0 && $i % $numCols == 0) {
				print "\t</tr>\n\t<tr>\n";
			}

			$col_width = round(100 / $numCols);
			print "\t\t<td valign=\"top\" width=\"".$col_width."%\" align=\"center\">\n";

			$this->thumbnailCell($img,$i,$param);

			print "\t\t</td>\n";

			$i++;
		}
		if ($i % $numCols != 0) {
			for ($j = $numCols - $i % $numCols; $j > 0; $j--) {
				print "\t\t<td>&nbsp;</td>\n";
			}
		}
		if ($i != 0) {
			print "\t</tr>\n";
		}
		print "</table>\n";
		echo "<script>document.getElementById('wait').style.display='none'</script>";

		$this->pageNavigation($pageData);
	}
	
	/**
	 * Examine the $pageData (of type ImagesPageData) and generate the
	 * html code for the page navigation bar
	 */
	private function pageNavigation(&$pageData) {
		global $_config;
		global $_db;

		$numPages = floor($pageData->count() / $_config['photosPerPage']) + 1;
		if ($numPages == 1)
			return;

		$page = (isset($_GET['page'])) ? $_GET['page'] : 1;

		echo "\n<!--Page Navigation //-->\n";
		if ($page > 1)
			echo "<a href=\"{$this->hrefWithPage($page - 1)}\">&lt; &nbsp;</a>\n";
		for ($i = 1; $i <= $numPages; $i++) {
			if ($i != $page)
				echo "<a href=\"{$this->hrefWithPage($i)}\">$i</a>&nbsp;\n";
			else
				echo "$i &nbsp;\n";
		}
		if ($page < $numPages)
			echo "<a href=\"{$this->hrefWithPage($page + 1)}\">&nbsp; &gt;</a>\n";
		echo "\n";
	}


	/**
	 * Generate the html code to display the full-sized image with
	 * path $url
	 */
	private function htmlFullsizeImagePage($url) {
		global $_config;
		global $_GET;

		$n = 0;
		if (isset($_GET['n'])) {
			$n = $_GET['n'];
		}

		if (isset($_GET['f']) || !isset($_GET['n'])) {
			$up = "javascript: history.go(-1)";
		} else {
			$page = 1 + floor($n / $_config['photosPerPage']);

			if (isset($_GET['a'])) {
				$up = $_config["selfUrl"]."/".$_config["scriptname"]."/album/".$_GET['a']."/page_".$page.".html#".$n;
			}

			if (isset($_GET['t'])) {
				$up = $_config["selfUrl"]."/".$_config["scriptname"]."/tag/".$_GET['t']."/page_".$page.".html#".$n;
			}
		}

		echo "<div align='right'><a href='".$up."'><img src='".$_config["selfUrl"]."/icons/up.png' alt='Up' /></a><a href='".$_config["selfUrl"]."/".$_config["scriptname"]."'><img src='".$_config["selfUrl"]."/icons/home.gif' alt='Home' /></a></div>";

		if (preg_match("/AVI\$|avi\$/", $url)) {
			printf('<div align="center" style="height:680px;"><object data="%s/images/%s" type="video/x-msvideo" height="680" width="860">'.
			       '<param name="src" value="%s/images/%s" />'.
			       '<param name="autoplay" value="true" />'.
			       '<param name="autoStart" value="1" />'.
			       '<param name="controller" value="false" />'.
			       'alt : <a href="%s/images/%s">%s</a>'.
			       '</object></div><br />',
			       $_config["selfUrl"], $url, $_config["selfUrl"], $url, $_config["selfUrl"], $url, $url);

			$type = 'video';
		} else {
 			print('<div align="center" style="height:640px;"><a href="'.$up.'">'."\n");
			printf("<img id='image' style='height: 100%%;image-orientation: from-image;' src=\"%s/images/%s\" /></a>".
			       "</div><br />\n", $_config["selfUrl"], $url);
			$type = 'image';
		}

		flush();
		ob_flush();

		if (isset($_GET['a'])) {
			$albumId = $_GET['a'];
			$albumPageRows = $this->_db->query(
				'SELECT CONCAT(Albums.relativePath,\'/\',Images.name) AS path, Albums.relativePath,'.
				' Images.id, Images.name, Images.modificationDate, ii.Rating'.
				' FROM Images, Albums, ImageInformation as ii'.
				' WHERE Albums.id='.$albumId.' AND Albums.id=Images.album AND Images.Id=ii.imageid'.
				' AND Rating > 2'.
				' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
				' WHERE '.$_config['restrictedTags'].')'.
				' ORDER BY Images.modificationDate LIMIT '.($n - 1).', 3'
			)->fetchAll();

			$context = "a={$albumId}";
		}

		if (isset($_GET['t'])) {
			$tagId = $_GET['t'];
			$whereClause = $this->whereClause($tagId);

			$albumPageRows = $this->_db->query(
				'SELECT Albums.relativePath||\'/\'||Images.name AS path, Images.id,'.
				' Images.name, Images.modificationDate FROM Images, Albums, ImageTags'.
				' WHERE Images.id = ImageTags.imageid '.
				' AND '.$whereClause.
				' AND '.$_config['restrictedAlbums'].
				' AND Albums.id=Images.album'.
				' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
				' WHERE '.$_config['restrictedTags'].')'.
				' ORDER BY Images.modificationDate LIMIT '.($n - 1).', 3'
			)->fetchAll();

			$context = "t={$tagId}";
		}

		if (isset($albumPageRows)) {
			if ($n == "0") { // beginning
				if (count($albumPageRows) > 1) { // more than one photo in album
					$next = $this->stripLeadingSlash($albumPageRows[1]['path']);
				}
				$current = $albumPageRows[0];
			} elseif (count($albumPageRows) == 2) { // end
				$prev = $this->stripLeadingSlash($albumPageRows[0]['path']);
				$current = $albumPageRows[1];
			} else {
				$next = $this->stripLeadingSlash($albumPageRows[2]['path']);
				$prev = $this->stripLeadingSlash($albumPageRows[0]['path']);
				$current = $albumPageRows[1];
			}

			echo '<table width="90%" style="margin-top: -2em; margin-bottom: -2em;"><tr><td width="30%">';
			if ($prev) {
				echo "\t\t\t".$this->mkLink('image', $prev,
					"&lt;&nbsp;", "{$context}&n=".($n - 1), "id='previmg''")."\n";# onclick='prev()

				// preload prev image (very likely to have abeen already loaded, though)
				if (!preg_match("/AVI\$|avi\$/", $prev)) {
					printf("<img width='0' src=\"%s/images/%s\" />", $_config["selfUrl"], $prev);
				}
			}

			echo '</td><td width="30%">';
			$the_date = explode('-', substr($current['modificationDate'], 0, 10));
			echo '<p class="tiny" style="text-align: center; margin-top: .3em; margin-bottom: .6em">'.$the_date[2].'.'.$the_date[1].'.'.$the_date[0].', '.substr($current['modificationDate'], 11, 20).'</p>';
			echo '</td><td width="30%">';
			if (isset($next)) {
				echo "\t\t\t".$this->mkLink('image', $next,
					"&nbsp;&gt;", "{$context}&n=".($n + 1), "id='nextimg' ")."\n";#onclick='next()'

				// preload next image
				if (!preg_match("/AVI\$|avi\$/", $next)) {
					printf("<img width='0' src=\"%s/images/%s\" />", $_config["selfUrl"], $next);
				}
			}
			echo '</td></tr></table>';

			printf("<script>function next() { document.getElementById('image').src='%s/images/%s'; }</script>", $_config["selfUrl"], $next);
			printf("<script>function prev() { document.getElementById('image').src='%s/images/%s'; }</script>", $_config["selfUrl"], $prev);
			echo "<script>function slideshow(url, s) { window.location=url; }</script>";
			echo "<script>";
			echo "function gotkey(k){ if (k.keycode = 39) document.getElementById('nextimg').click();";
			echo "if (k.keycode = 37) document.getElementById('previmg').click();";
			echo "};";
			echo "document.addEventListener('keyup', gotkey, false);</script>";
		}

		echo "<div align='right'><a href='".$_config["selfUrl"]."/images/".$url."'><img src='".$_config["selfUrl"]."/icons/lookingglass.png' /></a>&nbsp;";
		if (isset($albumPageRows) && isset($next)) {
			$slideshow = '';
			if (!isset($_GET['s']) || $type == 'video') {
				$slideshow = "&s=3";
				echo $this->mkLink('image', $next, "<img id='slideshow_button' src='".$_config["selfUrl"]."/icons/slideshow.png' />",
					$context."&n=".($n + 1).$slideshow);
			} else {
				echo $this->mkLink('image', $url, "<img id='slideshow_button' src='".$_config["selfUrl"]."/icons/slideshow.png' />",
					$context."&n=".($n).$slideshow);
			}
			if (isset($_GET['s']) && isset($next) && $type == 'image') {
				$s = $_GET['s'];
				$this->slideshow($next, $context, $n, $s);
			}
		}
		echo "</div>";
	}

	private function slideshow($path, $context, $n, $s) {
		global $_config;
		echo "<script>setTimeout('slideshow(\"".$_config['selfUrl']."/".$_config["scriptname"]."/image/".$path.".html?".$context."&n=".($n + 1)."&s=".$s."\")', ".$s."*1000); b=document.getElementById('slideshow_button'); b.style.backgroundColor='#303030';b.style.border='1px inset #555555';</script>";
	}

	/**
	 * Generate html code for a list of all available photo albums
	 */
	private function htmlAlbumList() {
		global $_db;
		global $_config;
		global $i18n;

		$rows = $this->_db->query(
			'SELECT Albums.id, Albums.relativePath, Albums.date,'.
			' Albums.caption, Albums.collection, I.name,'.
			' CONCAT(Albums.relativePath,\'/\',I.name) AS path'.
                        ' , (SELECT COUNT(*) FROM Images AS IM WHERE IM.album=Albums.id) as albcnt'.
                        ' , (SELECT COUNT(*) FROM Images AS IM, ImageInformation as ii WHERE IM.album=Albums.id AND Im.id=ii.imageid AND ii.Rating>='.$_GET['minRating'].') as albfiltcnt'.
			' FROM Albums LEFT OUTER JOIN Images AS I'.
			' ON Albums.icon=I.id WHERE '.$_config['restrictedAlbums'].
			' ORDER BY Albums.relativePath DESC'
		)->fetchAll();

		printf("<h2>%s</h2>\n\n", $i18n['photoAlbums']);
                $curfold="";
		echo "<ul>\n";
		foreach (array_reverse($rows) as $row) {
			if ($row['relativePath'] && $row['albfiltcnt']>0) {
				$fold=explode("/",$row['relativePath'])[1];
				if (strcmp($fold,$curfold) !== 0) { 
                                   	if (strlen($curfold)>0) echo "</ul></li>\n";
			  		echo "<li><span class=collapse>".$fold."</span><ul>";
					$curfold=$fold;
				}
				if ($row["albcnt"]>0) {
				echo "<li ><span class=collapse ><div width=200px>";// div is debug element
				$path = $this->stripLeadingSlash($row["path"]);
				if (strpos(substr($path,-5),'.')!==false) {
					$thumb = $this->getThumbnailFileName($path);
					$thumb_incl = "<img style='vertical-align:middle;' src=\"".$_config['selfUrl']."/thumbnails/".$thumb."\" height=50px/>";
				} else $thumb_incl = "";
				echo "</div>";//debug
				if (strlen($row['relativePath'])==1) 
					$dispname='Root';
				else 
					$dispname=$this->stripLeadingSlash(str_replace($curfold.'/','',$row['relativePath']));
				echo $this->mkLink('album', $row['id'],
					$thumb_incl." <span>".$dispname."&nbsp(".$row['albfiltcnt'].'/'.$row['albcnt'].")</span>","minRating=".$_GET['minRating']);
				echo "</span></li>\n";
				}//albcnt
			}
			echo "\n\n";
		}
		echo "</li></ul>\n";
	echo "<script>$(document).ready(function(){";
	echo "  $('.xcollapse').toggle();";
	echo "});</script>";
	}

	/**
	 * Display all images in album with id $albumId as a paged thumbnail
	 * page
	 */
	private function htmlAlbumPage($albumId) {
		global $_db;
		global $i18n;
		global $_config;

		//Get data of images on this page
		$albumPageRows = $this->_db->query(
			'SELECT CONCAT(Albums.relativePath,\'/\',Images.name) AS path, Albums.relativePath,'.
			' Images.id, Images.name, Images.modificationDate, ii.Rating '.
			' FROM Images, Albums, ImageInformation as ii'.
			' WHERE Albums.id='.$albumId.' AND Albums.id=Images.album AND Images.id=ii.imageid'.
			' AND Rating>='.$_GET['minRating'].
			' AND Images.id NOT IN (SELECT imageid FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'.
			' ORDER BY Images.modificationDate '.$this->limitClause()
		)->fetchAll();

		//Get total number of images in this album
		$numResults = $this->_db->query(
			'SELECT COUNT(*) FROM Images, Albums'.
			' WHERE Albums.id='.$albumId.' AND Albums.id=Images.album'.
			' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'
		)->fetchColumn();
		$numfiltResults = $this->_db->query(
			'SELECT COUNT(*) FROM Images, Albums, ImageInformation as ii'.
			' WHERE Albums.id='.$albumId.' AND Albums.id=Images.album AND Images.id=ii.imageid'.
			' AND ii.Rating>='.$_GET['minRating'].
			' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'
		)->fetchColumn();
		if ($numResults > 0) {
			if ($_GET['minRating']<>"-1") $filtcntstr="<font size=-1>( %d filt)</font>";
			else $filtcntstr="";
			printf('<h2>%d %s '.$i18n['lq'].'%s'.$i18n['rq'].$filtcntstr."</h2>\n",
				$numResults, $i18n['imagesInAlbum'],
				$this->stripLeadingSlash($albumPageRows[0]['relativePath']),$numfiltResults);
		}
		$thumbpgdat = new ImagesPageData($albumPageRows, $numfiltResults);
		$this->thumbnailPage( $thumbpgdat, "a={$albumId}" );
	}

	/**
	 * Display all images in album with id $albumId as a paged thumbnail
	 * page
	 */
	private function htmlRandomThumbPage() {
		global $_db;
		global $i18n;
		global $_config;

		//Get data of images on this page

		$albumPageRows = $this->_db->query(
			'SELECT CONCAT(Albums.relativePath,\'/\',Images.name) AS path, Albums.relativePath,'.
			' Images.id, Images.name, Images.modificationDate, ii.rating, Albums.id as albumid'.
			' FROM Images, Albums, imageinformation as ii'.
			' WHERE Albums.id=Images.album AND Images.id=ii.imageid AND ii.rating>1'.
			' AND Images.id NOT IN (SELECT imageid FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'.
			' ORDER BY RAND() LIMIT 1'
		)->fetchAll();
		printf("%s<br>\n",$this->mkLink('album',$albumPageRows[0]['albumid'],$this->stripLeadingSlash($albumPageRows[0]['relativePath'])));

		$this->thumbnailCell( $albumPageRows[0],1,"",1);
	}

	/**
	 * Display all images with tag (id: $tagId) as a paged thumbnail page
	 */
	private function htmlTagPage($tagId) {
		global $i18n;

		printf('<h2>%s '.$i18n['lq'].'%s'.$i18n['rq']."</h2>\n",
			$i18n['imagesWithTag'],
			$this->_tagTree->tagPropertyById($tagId, 'name'));

		$t = $this->imagesWithTag($tagId);
		$this->thumbnailPage($t, "t={$tagId}");
	}

	/**
	 * The WHERE part of a SQL-Query to get all images associated with
	 * $tagId. This is necessary if the tag has children and we want to
	 * Show the images which have these child tags set too.
	 */
	private function whereClause($tagId) {
		global $_tagTree;

		$nodesBelow = array();

		$find = $this->_tagTree->findNode($tagId);

		$this->_tagTree->nodesBelow($find, $nodesBelow);

		$whereClause = "";
		if (count($nodesBelow) == 0) {
			$whereClause = 'tagid='.$tagId.' ';
		} else {
			$whereClause = 'tagid IN (';
			$i = 0;
			foreach ($nodesBelow as $node) {
				if ($i != 0) {
					$whereClause .= ', ';
				}
				$whereClause .= $node->key();
				$i++;
			}
			$whereClause .= ')';
		}
		return $whereClause;
	}

	/**
	 * Return whether there are any images with tag (id: $tagId)
	 */
	private function hasImagesWithTag($tagId) {
		global $_config;

		return $this->_db->query(
			'SELECT COUNT(*) FROM Images, Albums'.
			' WHERE Images.id IN'.
			' (SELECT imageid FROM ImageTags'.
			' WHERE '.$this->whereClause($tagId).
			' AND '.$_config['restrictedAlbums'].
			')'.
			' AND Albums.id=Images.album LIMIT 0, 1'
		)->fetchColumn();
	}

	/**
	 * Return an ImagesPageData for the query: "All images which have tag
	 * (id: $tagId)"
	 */
	private function imagesWithTag($tagId) {
		global $_config;

		$rows = array();

		$whereClause = $this->whereClause($tagId);

		//Get data of images on this page
		$albumPageRows = $this->_db->query(
                        'SELECT CONCAT(Albums.relativePath,\'/\',Images.name) AS path, Images.id,'.
			' Images.name, Images.modificationDate FROM Images, Albums, ImageTags, ImageInformation as ii'.
			' WHERE Images.id = ImageTags.imageid'.
			' AND '.$whereClause.
			' AND '.$_config['restrictedAlbums'].
			' AND Albums.id = Images.album AND Images.id=ii.imageid'.
			' AND Rating>='.$_GET['minRating'].
			' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'.
			' ORDER BY Images.modificationDate '.$this->limitClause()
		)->fetchAll();

		//Number of images total in this "album"
		$numResults = $this->_db->query(
			'SELECT COUNT(*) FROM Images, Albums, ImageTags, ImageInformation as ii'.
			' WHERE Images.id = ImageTags.imageid'.
			' AND '.$whereClause.
			' AND '.$_config['restrictedAlbums'].
			' AND Albums.id = Images.album AND Images.id=ii.imageid'.
			' AND Rating>='.$_GET['minRating'].
			' AND Images.id NOT IN (SELECT imageId FROM ImageTags'.
			' WHERE '.$_config['restrictedTags'].')'
		)->fetchColumn();

		return new ImagesPageData($albumPageRows, $numResults);
	}

	/**
	 * html code for a link to a tag, album or image query
	 * This has to consider the url's syntax described in parseUrl()
	 */
	static public function mkLink($var, $val, $caption, $params="", $attr="") {
		global $_config;

		$ret = "<a href=\"{$_config['selfUrl']}/{$_config["scriptname"]}/";

		switch ($var) {
			case 'tag':   $ret .= "tag/$val?${params}"; break;
			case 'album': $ret .= "album/$val?${params}"; break;
			case 'image': $ret .= "image/{$val}.html?${params}"; break;
			default: die('This should not happen');
		}

		return $ret."\" $attr>$caption</a>";
	}

	/**
	 * Chop of the first character of a string
	 */
	public function stripLeadingSlash($string) {
		return substr($string, 1, strlen($string) - 1);
	}

	/**
	 * Generate the LIMIT part of a SQL-query to make the paged thumbnail
	 * view possible
	 */
	private function limitClause() {
		global $_config;

		$page = 1;
		if (isset($_GET["page"])) {
			$page = $_GET["page"];
		}
		return 'LIMIT '.(($page - 1)*$_config['photosPerPage']).','.
			($_config['photosPerPage']);
	}

	/**
	 * Modify the URL so that the page $page will be shown
	 * See parseUrl() for the URL's syntax
	 */
	private function hrefWithPage($page) {
		$urlpcs=explode("?",$_SERVER['REQUEST_URI'],2);
		return (strstr( $_SERVER['REQUEST_URI'], 'page_')) ?
			preg_replace('@page_([0-9]+)@', 'page_'.$page, $_SERVER['REQUEST_URI'])
			: "{$urlpcs[0]}/page_$page.html?{$urlpcs[1]}";
	}

	/**
	 * Encode to Rfc2396 as used by http://jens.triq.net/thumbnail-spec/thumbsave.html
	 */
	private function toRfc2396($path) {
		$ret = "";
		for ($i = 0; $i < strlen($path); $i++) {
			$o = ord($path[$i]);
			if ($o <= 0x20 or $o >= 127)
				$ret .= "%" . strtoupper(dechex($o));
			else
				$ret .= $path[$i];
		}
		return $ret;
	}

	/**
	 * Returns the thumb filename for the given image pathname
	 */
	private function getThumbnailFileName($path) {
		global $_config;

		$can_url = $_config['thumbHashPath'].$path;
		$can_url = $this->toRfc2396($can_url);
		$can_url = "file://".$can_url;
		return md5($can_url).'.png';
	}

	/**
	 * Generate html code for a tree of all available tags
	 * We have to consider that tags should not be shown if no images are
	 * associated with it. This makes it slow.
	 */
	private function htmlTagTreeRecursive($node, $level) {
		global $_config;
		if ($node->isLeaf())
			return;

		//Go through all ids
		echo str_repeat("\t", $level)."<ul>\n";
		foreach ($node->children() as $child) {
			$tagcnt=$this->hasImagesWithTag($child->key());
			if ($tagcnt>0) {
				if ($child->path()) {
					$path = $this->stripLeadingSlash($child->path());
					$thumb = $this->getThumbnailFileName($path);
					$thumb_incl = "<div id='t".$child->key()."' style='display:none'><br /><img style='vertical-align:middle;position:relative;' src=\"".$_config['selfUrl']."/thumbnails/".$thumb."\" /><br />".$this->mkLink('tag', $child->key(), $child->data())."</div>";
				} else {
					$thumb_incl = "";
				}

				echo str_repeat("\t", $level + 1);
				echo '<li onmouseover="show(\'t'.$child->key().'\');" onmouseout="hide(\'t'.$child->key().'\');"><span class=collapse>'.$this->mkLink('tag', $child->key(), $child->data()." ".$thumb_incl)."<font size=-1>({$tagcnt})</font></span></li>\n";#
				$this->htmlTagTreeRecursive($child, $level + 1);
			}
		}
		echo str_repeat("\t", $level)."</ul>\n";
	}

	private function htmlTagTree() {
		global $i18n;

		echo "<h2>{$i18n['Tags']}</h2>\n";
		$this->htmlTagTreeRecursive($this->_tagTree->root(), 0);
	}

	private $_db;
	private $_tagTree;
	private $_stopwatch;

	private function createthumb($name, $filename, $new_w, $new_h)
	{
		flush();
		ob_flush();
		$system = $name;
		if (preg_match("/jpg\$|jpeg\$|JPG\$|JPEG\$/", $system)) {
			$src_img = imagecreatefromjpeg($name);
        		$exif = exif_read_data($name);
        		if ($src_img && $exif && isset($exif['Orientation']))
       			{
            			$ort = $exif['Orientation'];

            			if ($ort == 6 || $ort == 5)
                			$src_img = imagerotate($src_img, 270, null);
            			if ($ort == 3 || $ort == 4)
                			$src_img = imagerotate($src_img, 180, null);
            			if ($ort == 8 || $ort == 7)
                			$src_img = imagerotate($src_img, 90, null);

            			if ($ort == 5 || $ort == 4 || $ort == 7)
                			imageflip($src_img, IMG_FLIP_HORIZONTAL);
        		}
		} elseif (preg_match("/png\$/", $system)) {
			$src_img = imagecreatefrompng($name);
		} else {
			return;
		}

		$old_x = imageSX($src_img);
		$old_y = imageSY($src_img);

		if ($old_x > $old_y) {
			$thumb_w = $new_w;
			$thumb_h = $old_y * ($new_h / $old_x);
		}

		if ($old_x < $old_y) {
			$thumb_w = $old_x * ($new_w / $old_y);
			$thumb_h = $new_h;
		}

		if ($old_x == $old_y)
		{
			$thumb_w = $new_w;
			$thumb_h = $new_h;
		}

		$dst_img = ImageCreateTrueColor($thumb_w, $thumb_h);
		imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);
		imagepng($dst_img, $filename);
		imagedestroy($dst_img);
		imagedestroy($src_img);
	}
};
?>
