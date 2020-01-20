<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 2019-12-24 TC moOde 6.3.0
 * Contrib: Lee Jordan
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
//$dbh = cfgdb_connect();




        

$_select['radio_tags'] = empty($_SESSION['radio_tags']) ? '80s,ambient,chillout' : $_SESSION['radio_tags'];
$_select['radio_stations'] = empty($_SESSION['radio_stations']) ? 'net:bbc,net:npr,tag:dashradio' : $_SESSION['radio_stations'];
$_select['radio_range'] = empty($_SESSION['radio_range']) ? '25-600' : $_SESSION['radio_range'];











$_taglist   = $_select['radio_tags'];
$_stations  = $_select['radio_stations'];
$_range     = $_select['radio_range'];


// Load and update JSON configuration file
$jsonString = file_get_contents('/var/www/radio/sources/config.json');
$data = json_decode($jsonString, true);







// Load Community Radio Tags JSON
$t_json     = file_get_contents('/var/www/radio/sources/rb/tags.json');
$t_data     = json_decode($t_json, true);
$t_range    = explode("-", $data['radiobrowser'][0]['range']);


$_tags = "";
foreach ($t_data as $key => $value) {
    $t_name     = str_replace("'","",$t_data[$key]["name"]);
    $t_count    = $t_data[$key]["stationcount"];
    
    $t_hash     = substr_count($t_name[0],'#');
    if ((ctype_print($t_name) && in_array($t_count, range($t_range[0], $t_range[1]))) && ($t_hash == 0)) {    
        if (preg_match('/[^a-z0-9]/i', $t_name)) {
            
        } else {
            $_tags = $_tags . '<li><a href="#" class="btn btn-default tag-add tag-icon" data-name="'.$t_name.'"><i class="fas fa-plus-circle"></i></a> <a href="javascript:window.open(\'./radio/?type=tag&play='.$t_name.'\', \'iframe_a\');" class="btn btn-default preview-stations">' . $t_name . ' (' . $t_count . ')</a></li>';
        }
    }
   
}




// SAVE BUTTON - THE MAIN ACTION
if (isset($_POST['save']) && $_POST['save'] == '1') {
    
    
    $_usrmsg = "";
    foreach ($_POST['config'] as $key => $value) {
		//cfgdb_update('cfg_system', $dbh, $key, $value);
		$_SESSION[$key] = $value;
	    $_usrmsg = $_usrmsg . $key . "-" . $value . ",";
    }
    $_usrmsg = "<strong>Success: Playlists regenerated in RADIO/_Stations</strong>";
	$_SESSION['notify']['title'] = 'Changes Saved';
    
    $data['radiobrowser'][0]['tags']        = $_SESSION['radio_tags'];
    $data['radiobrowser'][0]['stations']    = $_SESSION['radio_stations'];
    $data['radiobrowser'][0]['range']       = $_SESSION['radio_range'];
    $data['radiobrowser'][0]['singles']     = $_SESSION['station_split'];
    $_taglist   = $_SESSION['radio_tags'];
    $_stations  = $_SESSION['radio_stations'];
    $_range     = $_SESSION['radio_range'];
    $_singles   = $_SESSION['station_split'];
    

    
    // SAVE THE JSON, TRIGGER PYTHON, UPDATE MPD
    $newJsonString = json_encode($data);
    file_put_contents('/var/www/radio/sources/config.json', $newJsonString);
    
    
    $cmd            = "sudo python /var/www/radio/sources/rb/rb-populate.py";
    $_output        = $_usrmsg . "<br><br><br>" . shell_exec($cmd);
    $_consolevis    = "block";
    
    
    
    
    shell_exec("mpc update");
    
    
} else if(isset($_POST['refresh']) && $_POST['refresh'] == '1') {
    $_SESSION['notify']['title'] = 'Tags Refreshed!';
    $_usrmsg = "<strong>Information: Tags Refreshed</strong><br>";
    shell_exec("sudo python /var/www/radio/sources/rb/rb-tags.py");
    $_consolevis    = "none";
} else {
    //$_usrmsg = "<strong>Information: Select Tags</strong><br>Tap a tag name to preview the stations in Moode ...";
    $_consolevis    = "none";
    
    $i = 0;
    
    // PUSH READABLE NETWORK NAMES FROM USER INPUT TO CREATE CURRENT NETWORK LOGOS 
    $networks   = explode(",",$_stations);
    foreach ($networks as $value) {
        $network = explode(":",$value);
        $networks[$i] = $network[1];
        $i++;
    }
    
    $_networklogos = '<ul class="network-logos">';
    // MAKE NETWORK LOGO HTML
    $i = 0;
    $logopath = "/images/radio-logos/thumbs/";
    
    foreach ($networks as $value) {
        $logosrc = $logopath . $value .".jpg";
        
        // Lay down the logo browser ...
        $_networklogos .= '<li><p style="text-align:center;"><small>'.$value.'</small><img id="fauxbtn'.$i.'" class="network-logo" src="'.$logosrc.'" onclick="document.getElementById(\'logoupload'.$i.'\').click();" style="cursor:pointer;"><input type="file" name="logoupload'.$i.'" id="logoupload'.$i.'"></p></li>';
        $i++;
    }
    $_networklogos .= '</ul><p style="margin-left:50px;"><button class="btn btn-medium btn-primary btn-submit" type="submit" name="savelogos" value="1" >Save Logos</button></p>';
}



// SECOND SAVE BUTTON - CHANGING RADIO NETWORK LOGOS
if (isset($_POST['savelogos']) && $_POST['savelogos'] == '1') {
    $i              = 0;
    $logopath       = "/images/radio-logos/thumbs/";
    $targetpath     = "/var/www" . $logopath;
    $networks       = explode(",",$_stations);
    
    // LOOP USERS LOGO CHOICES
    foreach ($networks as $value) {
        if($_FILES['logoupload'.$i]['size'] > 0){
            $network        = explode(":",$value);
            $tempfile       = "/tmp/radio-logos/".$network[1];

            // KEEP HOLD OF THE TEMP FILE
            copy($_FILES['logoupload'.$i]['tmp_name'], $tempfile);

            // MOVE THE TEMP FILE INTO LOGO THUMBS
            $runcmd = "sudo mv -f " . $tempfile . " " . $targetpath . $network[1] . ".jpg";
            shell_exec($runcmd); 
            
        }
        
        $i++;

    }
    
    $_usrmsg = "<strong>Success: Network logos regenerated in RADIO/_Stations/networks</strong>";
	$_SESSION['notify']['title'] = 'Logos Saved';
    
}



// UPDATE STATION SPLITTER
if (isset($_POST['update_station_split'])) {
	if (isset($_POST['station_split'])) {
		$_SESSION['notify']['title'] = $_POST['station_split'] == '1' ? 'Station spillter on' : 'Station splitter off';
		$_SESSION['notify']['duration'] = 3;
        $_station_split   = $_POST['station_split'];
        $_SESSION['station_split'] = $_station_split;
        
        $data['radiobrowser'][0]['tags']        = $_SESSION['radio_tags'];
        $data['radiobrowser'][0]['stations']    = $_SESSION['radio_stations'];
        $data['radiobrowser'][0]['range']       = $_SESSION['radio_range'];
        $data['radiobrowser'][0]['singles']     = $_POST['station_split'];
        $newJsonString = json_encode($data);
        file_put_contents('/var/www/radio/sources/config.json', $newJsonString);
        
        $_select['toggle_station_split1'] = "<input type=\"radio\" name=\"station_split\" id=\"toggle_station_split0\" value=\"1\" " . (($_POST['station_split'] == '1') ? "checked=\"checked\"" : "") . ">\n";
        $_select['toggle_station_split0'] = "<input type=\"radio\" name=\"station_split\" id=\"toggle_station_split1\" value=\"0\" " . (($_POST['station_split'] == '0') ? "checked=\"checked\"" : "") . ">\n";

	}
} else {
    $_select['toggle_station_split1'] = "<input type=\"radio\" name=\"station_split\" id=\"toggle_station_split0\" value=\"1\" " . (($_SESSION['station_split'] == '1') ? "checked=\"checked\"" : "") . ">\n";             
    $_select['toggle_station_split0'] = "<input type=\"radio\" name=\"station_split\" id=\"toggle_station_split1\" value=\"0\" " . (($_SESSION['station_split'] == '0') ? "checked=\"checked\"" : "") . ">\n";
}











// TOGGLE MOODE DEFAULT STATIONS
if (isset($_POST['update_station_hide'])) {
	if (isset($_POST['station_hide'])) {
		$_SESSION['notify']['title'] = $_POST['station_split'] == '1' ? 'Moode stations hidden' : 'Moode stations restored';
		$_SESSION['notify']['duration'] = 3;
        $_station_hide   = $_POST['station_hide'];
        $_SESSION['station_hide'] = $_station_hide;
        
        $_select['toggle_station_hide1'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide0\" value=\"1\" " . (($_POST['station_hide'] == '1') ? "checked=\"checked\"" : "") . ">\n";           
        $_select['toggle_station_hide0'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide1\" value=\"0\" " . (($_POST['station_hide'] == '0') ? "checked=\"checked\"" : "") . ">\n";
        
        
        
        // LOOK UP MOODE STATIONS IN DB ONLY ACTION THESE, LEAVE USER STATIONS ALONE
        $db = new SQLite3('/var/local/www/db/moode-sqlite3.db');
        $results = $db->query('SELECT * FROM cfg_radio WHERE type = "s"');
        
        
        if ($_POST['station_hide'] == '1') {
            while ($row = $results->fetchArray()) {
                
                if($row['logo'] == "local"){
                    
                    // with quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/'".$row['name'].".pls' /var/www/radio/sources/moode");

                    // with double quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/\"".$row['name'].".pls\" /var/www/radio/sources/moode");

                    // without
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/".$row['name'].".pls /var/www/radio/sources/moode");
                } else {
                    // BBC 320kb STATIONS ...
                    $name           = $row['logo'];
                    $name           = str_replace("images/radio-logos/", "", $name);
                    $name           = str_replace(".jpg", "", $name);
                    
                    // with quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/'".$name.".pls' /var/www/radio/sources/moode");

                    // with double quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/\"".$name.".pls\" /var/www/radio/sources/moode");

                    // without
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/".$name.".pls /var/www/radio/sources/moode");    
                }
                
                
                
            }
        } else {
            while ($row = $results->fetchArray()) {
                // Restoring much faster ...
                shell_exec("sudo sudo mv /var/www/radio/sources/moode/*.pls /var/lib/mpd/music/RADIO");
                
                
            }
        }
        shell_exec("mpc update");
        
	}
} else {
    $_select['toggle_station_hide1'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide0\" value=\"1\" " . (($_SESSION['station_hide'] == '1') ? "checked=\"checked\"" : "") . ">\n";          
    $_select['toggle_station_hide0'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide1\" value=\"0\" " . (($_SESSION['station_hide'] == '0') ? "checked=\"checked\"" : "") . ">\n";
}











session_write_close();



waitWorker(1, 'rdo-config');

$tpl = "rdo-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
