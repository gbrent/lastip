<?php
$record_limit = 50; // How many records do you want to keep?
$storage_file = $_SERVER['DOCUMENT_ROOT']."lastip.json"; // Where do you want to keep your json file that will store all the records?
date_default_timezone_set('America/Chicago'); // For timezones see https://www.php.net/manual/en/timezones.america.php 
$maxmindb ='/var/www/shared_files/maxmind/GeoLite2-City.mmdb'; // Location of the GeoLite2-City.mmdb file. See more here: https://dev.maxmind.com/geoip/geoip2/geolite2/
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
session_start();
require 'vendor/autoload.php';
use GeoIp2\Database\Reader;

function read_recent_records(){
    global $storage_file;
    $storage_file_handle = fopen($storage_file, 'r+') or die("can not open file for reading.");
    return json_decode(fread($storage_file_handle,filesize($storage_file)),true);
}

function write_recent_records($payload){
    global $storage_file;
    $storage_file_handle = fopen($storage_file, 'w') or die("can not open file for writing.");
    try{
        json_encode(fwrite($storage_file_handle,$payload));
    }catch(Exception $e){
        print 'Error while opening file for writing: ' .$e->getMessage();
    }
}

function agent_info() {
    $u_agent = $_SERVER['HTTP_USER_AGENT']; 
    $browser_name = 'Unknown';
    $platform = 'Unknown';

    // First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $os = 'linux';
    }elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $os = 'mac';
    }elseif (preg_match('/windows|win32/i', $u_agent)) {
        $os = 'windows';
    }elseif (preg_match('/iphone/i', $u_agent)) {
        $os = 'iphone';
    }elseif (preg_match('/ipad/i', $u_agent)) {
        $os = 'ipad';
    }elseif (preg_match('/android/i', $u_agent)) {
        $os = 'android';
    }
    
    // Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) { 
        $browser_name = 'Internet Explorer'; 
    } elseif(preg_match('/Firefox/i',$u_agent)) { 
        $browser_name = 'Mozilla Firefox'; 
    } elseif(preg_match('/Chrome/i',$u_agent)) { 
        $browser_name = 'Google Chrome'; 
    } elseif(preg_match('/Safari/i',$u_agent)) { 
        $browser_name = 'Apple Safari'; 
    } elseif(preg_match('/Opera/i',$u_agent)) { 
        $browser_name = 'Opera'; 
    } elseif(preg_match('/Netscape/i',$u_agent)) { 
        $browser_name = 'Netscape'; 
    }

    return array('browser' => $browser_name,'os' => $os);
} 

if(preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/",$_SERVER['REMOTE_ADDR']) && !$_GET['lastip'] && !$_SESSION['last_ip']){
    $_SESSION['last_ip']=1;
    $ip_records = read_recent_records();
    $agent_info = agent_info();
    $new_record = array(
        "ip" => $_SERVER['REMOTE_ADDR'],
        "os" => $agent_info['os'],
        "browser" => $agent_info['browser'],
        "date" => date("Y-m-d H:i:s")
    );
    array_unshift($ip_records['visitors'],$new_record);
    $ip_records['visitors'] = array_slice($ip_records['visitors'], 0, $record_limit); // to account for key 0 and the new one we are adding now
    write_recent_records(json_encode($ip_records));

}elseif($_GET['lastip']){
    // Show last IPs
    $ip_records = read_recent_records();
    ?>
    <!DOCTYPE html>
    <html lang="en" class="no-js">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge"> 
        <title>LastIP by Brent Russell</title>
        <meta name="author" content="Brent Russell" />
        
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    </head>
    <body>
	<!-- Main Content -->
	<div id="last-ip-wrapper">
        <section class="pl-3 pr-3 pb-5">
            <h2>LastIP</h2>
            <div class=""> 
                <?php
                $reader = new Reader($maxmindb);
                print("<table class='table table-striped table-bordered'>");
                print("<thead class='thead-light'><tr><th>Location</th><th>Date</th><th>Platform</th><th>Browser</th></tr></thead>");
                foreach ($ip_records['visitors'] as $key=>$record) {
                    if($record['ip'] != '127.0.0.1' && $record['ip']){
                        $ip_data = $reader->city($record['ip']);
                        $city = $ip_data->city->name;
                        $isoCode = $ip_data->mostSpecificSubdivision->isoCode;
                        $country = $ip_data->country->name;

                        if($city && $isoCode && $country){
                            $location = $city.", ".$isoCode." ".$country;
                        }elseif($country && (!$city && !$isoCode)){
                            $location = $ip_data->country->name;
                        }elseif(!$iso_abrv && $city && $country){
                            $location = $city.", ".$country;
                        }elseif($city && !$country){
                            $location = $city;
                        }elseif($country){
                            $location = $country;
                        }else{
                            $location = $record['ip'];
                        }

                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $record['date'])->format('l M jS, Y g:ia s\s');
                        ?>
                        <tr>
                            <td><?=$location;?></td>
                            <td><?=$date;?></td>
                            <td><?=$record['os'];?></td>
                            <td><?=$record['browser'];?></td>
                        </tr>
                    <?php
                    }
                }
                ?>
                </table>    
            </div>
            <a href='https://www.brentrussell.com/lastip.php?lastip=1#portfolio'>LastIP</a> by Brent Russell
        </section>
    </div>
    </body>
</html>
    <?php
}
?>
