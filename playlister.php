<?php

/**
 * This class reads a directory of media files and generates a
 * XML/XSPF formatted playlist
 * 
 * @author     lacymorrow
 * @website    www.lacymorrow.com
 * @copyright  Copyright (c) 2012 Lacy Morrow. All rights reserved.
 * @license    Proprietary/Closed Source
*/

########################
# playlister.php
# Lacy Morrow 2012
# www.lacymorrow.com
# getID3 - HTML5BoilerPlate - MediaElement.js
########################

####################
###   SETTINGS   ###
####################

# USE ID3 TAGS TO AUTOMATICALLY FILL TRACK INFORMATION
# (as opposed to specifying in the directory structure, e.g. 'media/artist/album/track.mp3')
$id3 = true;

# CACHE PLAYLIST - boolean
$cache = true;

# CACHE PLAYLIST FILE - path/url - relative
$playlist = 'xplay_generated_playlist.xml';

# RETRIEVE ARTWORK - boolean
$artwork = true;

# MEDIA DIRECTORY - path/url - relative
$media = 'media';

#####################################
###  DO NOT EDIT BELOW THIS LINE  ###
#####################################


#####################################
###   BEGIN PLAYLIST GENERATION   ###
#####################################
// include id3 - path to id3
include_once('./getid3/getid3.php');

/*
 * Create playlist array
 */
// Create variables
global $playArr, $imgArr, $gloArr;
$playArr = array();
$imgArr = array();
$gloArr = array();
// use cached playlist if exists and valid
date_default_timezone_set('UTC');
if ($cache === true && file_exists ( $playlist ) && (date("z")-date("z", filemtime($playlist)) >= 7)){
	$playFile = file_get_contents( $playlist );
}
// no valid playlist - begin playlist generation
else {
	// initiate directory scan
	$trackArr = scanMedia($media,$id3);
	$playFile = generateXML($trackArr);
	if($cache === true){
		// Save playlist
		$fh = fopen($playlist, 'w') or die("can't write playlist file");
		fwrite($fh, $playFile);
		fclose($fh);
	}
}

// Output Playlist
echo $playFile;

#####################################
###    END PLAYLIST GENERATION    ###
#####################################

#####################################
/* 
 * generateXML
 * generates an XSPF/XML document to be processed
 */
#####################################

function generateXML($trackArr){
	$out = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$out .= '<playlist version="1" xmlns="http://xspf.org/ns/0/">'.PHP_EOL;
	$out .= '  <trackList>'.PHP_EOL;
	foreach($trackArr as $l){
		$out .= '    <track>'.PHP_EOL;
		foreach($l['location'] as $i){
			$out .= '      <location>'.$l['path'].'/'.htmlentities($i).'</location>'.PHP_EOL;
		}
		$out .= '      <creator>'.$l['creator'].'</creator>'.PHP_EOL;
		$out .= '      <album>'.$l['album'].'</album>'.PHP_EOL;
		$out .= '      <title>'.$l['title'].'</title>'.PHP_EOL;
		$out .= '      <annotation>'.$l['annotation'].'</annotation>'.PHP_EOL;
		$out .= '      <duration>'.$l['duration'].'</duration>'.PHP_EOL;
		$out .= '      <image>'.$l['image'].'</image>'.PHP_EOL;
		$out .= '      <info>'.$l['info'].'</info>'.PHP_EOL;
		$out .= '      <type>'.$l['info'].'</type>'.PHP_EOL;
		$out .= '    </track>'.PHP_EOL;
	}
	$out .= '  </trackList>'.PHP_EOL;
	$out .= '</playlist>';
	return $out;
}


#####################################
/* 
 * scanMedia
 * scans directory structure for media files 
 * and generates a playlist array
 */
#####################################

function scanMedia( $path = '.', $id3, $level = 1, $dir = ''){ 
	global $playArr, $imgArr, $gloArr, $creator, $album;
    $ignore = array( 'cgi-bin', '.', '..' ); 
    // Directories to ignore
    $dh = @opendir( $path ); 
    // Open the directory to the handle $dh 
    while( false !== ( $file = readdir( $dh ) ) ){ 
    // Loop through the directory 
     
        if( !in_array( $file, $ignore ) ){ 
        // Check that this file is not to be ignored 
            if( is_dir( "$path/$file" ) ){ 
            	echo $level;
            	if( $level == 1 ){ 
            		$creator = $file; $album = '' ;
            	} else if( $level == 2 ){ 
            		$album = $file; 
            	} else { $creator = ''; $album = ''; }
				// Store images for Creator/Album
	            scanMedia( "$path/$file",$id3, ($level+1), (($dir == '')?$path:$dir)); 
                // Re-call this same function but on a new directory. 
                // this is what makes function recursive. 
             
            } else { 
            	$ext = pathinfo($file, PATHINFO_EXTENSION);
				// generate file id - COULD BE MORE INTUITIVE (removes extension)
				$filename = pathinfo($file, PATHINFO_FILENAME);
				if(strtolower($filename) == 'artwork' && checkType($ext)){
					if($level == 0){ 
						$globalImg = "$path/$file"; 
					} else { 
						$gloArr[] = array('path' => $path, 'file' => $file);
					}
				}
				
				// if image file
				$ct = checkType($ext);
				if($ct == 'image'){
					// have not logged associated track, save for later
					$imgArr[] = array('image' => "$path/$file", 'filename' => $filename);
				// if track file
				} else if($ct == 'audio') {
					// create track object
					// Initialize getID3 engine //
					$getID3 = new getID3;
					// Analyze file and store returned data in $id3Info
					$id3Info = $getID3->analyze("$path/$filename.$ext"); //!!! Could need absolute path					
					/*
					 Optional: copies data from all subarrays of [tags] into [comments] so
					 metadata is all available in one location for all tag formats
					 metainformation is always available under [tags] even if this is not called
					*/
					getid3_lib::CopyTagsToComments($id3Info);
					$iDuration = (isset($id3Info['playtime_string'])) ? $id3Info['playtime_string'] : '';
					if($id3 == true){
						$iCreator = (isset($id3Info['comments_html']['artist'][0]) ? $id3Info['comments_html']['artist'][0] : $creator);
						$iAlbum = (isset($id3Info['comments_html']['artist'][0]) ? $id3Info['comments_html']['album'][0] : $album);
						$iTitle = (isset($id3Info['comments_html']['title'][0]) ? $id3Info['comments_html']['title'][0] : $filename);
						$iAnnotation = (isset($id3Info['comments_html']['comment'][0]) ? $id3Info['comments_html']['comment'][0] : '');
						$playArr[$filename] = array('filename' => $filename, 'type' => checkType($ext), 'creator' => $iCreator, 'album' => $iAlbum, 'title' => $iTitle, 'annotation' => $iAnnotation, 'duration' => $iDuration, 'location' => array($file), 'image' => '', 'info' => '', 'path' => $path);
					} else {
						$playArr[$filename] = array('filename' => $filename, 'type' => checkType($ext), 'creator' => $creator, 'album' => $album, 'title' => $filename, 'annotation' => '', 'duration' => '', 'location' => array($file), 'image' => '', 'info' => '', 'path' => $path);
					}
				} else if($ct == 'video') {
					//IF VIDEO
					if(!isset($playArr[$filename]['location']) || $playArr[$filename]['location'] === NULL){
						$l = array($file);
					} else {
						$l = $playArr[$filename]['location'];
						array_push($l,$file);
					}
					$playArr[$filename] = array('filename' => $filename, 'type' => checkType($ext), 'creator' => $creator, 'album' => $album, 'title' => $filename, 'annotation' => '', 'duration' => '', 'location' => $l, 'image' => '', 'info' => '', 'path' => $path);
				}
             
            }
         
        } 
     
    } 
    closedir( $dh ); 
    // Close the directory handle 

    // Merge loose image array with associated tracks
    foreach($playArr as &$playVal){
	    for ($i=0;$i<sizeOf($imgArr);$i++){
	    	//Apply track image
	    	if ($imgArr[$i]['filename'] == $playVal['filename']){
	    		$playVal['image'] = $imgArr[$i]['image'];
	    	} else {
	    	// Apply album/creator image
    		    for ($k=0;$k<sizeOf($gloArr);$k++){
	    		    if ($gloArr[$k]['path'] == $playVal['path']){
		    			$playVal['image'] = ''.$gloArr[$k]['path'].'/'.$gloArr[$k]['file'];
		    		}
    		    }
	    	}
    	}
    	//echo $globalImg;
    	// Apply global image
    	if($playVal['image'] == '' && $globalImg != ''){ $playVal['image'] = $globalImg; }
    	
    }
    
    // Directory Scan Complete
	if($dir == ''){
		return $playArr;
	}
} 


#####################################
/* 
 * checkType
 * checks for valid filetype
 */
#####################################

function checkType($ext){
	$musTypes = array( 'mp3','wav','ogg' );
	$vidTypes = array( 'mp4','webm','ogv' );
	$pixTypes = array( 'jpg', 'jpeg', 'gif', 'png' );
	if( in_array($ext, $musTypes) ){
		return 'audio';
	} else if( in_array($ext, $pixTypes) ){
		return 'image';
	} else if( in_array($ext, $vidTypes) ){
		return 'video';
	} else {
		return 'invalid';
	}
}


?>