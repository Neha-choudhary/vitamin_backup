<?php
date_default_timezone_set("Asia/Bangkok");
set_time_limit(0);
ini_set('memory_limit', '512M');

// require_once("config.php");
$db_server = "";
$db_name = "";
$db_user = "";
$db_pass = "";

// remote FTP host
$upload_ftp = true;
$ftp_server = "ip";
$ftp_user_name = "user";
$ftp_user_pass = "pass";

// path on current host
$path =  dirname(__FILE__).'/bakzdb/';
$file_name = 'db-backup.sql';

// remote host folder save backup
$remote_path = '/public_html/backup-db/';


// if you want use email, run "composer install" and uncomment below line
// require_once 'vendor/autoload.php';

$send_to_email = false;
// sendgrid.net api key
$apiKey_sendgrid = '';
$from_email = "abc@gmail.com";
$from_name = "ABC Name";

backup_tables($db_server,$db_user,$db_pass,$db_name);

function backup_tables($host,$user,$pass,$name,$tables = '*')
{ 
    global $apiKey_sendgrid, $send_to_email, $ftp_server, $ftp_user_name, $ftp_user_pass, $path, $file_name,
    $from_email, $from_name, $upload_ftp;

    $file = $path.$file_name;
    $remote_file = $remote_path.$file_name;
     
    $link = mysql_connect($host,$user,$pass);
    mysql_select_db($name,$link);
     
    //get all of the tables
    if($tables == '*')
    {
        $tables = array();
        $result = mysql_query('SHOW TABLES');
        mysql_query("SET NAMES utf8");
        while($row = mysql_fetch_row($result))
        {
            $tables[] = $row[0];
        }
    }
    else
    {
        $tables = is_array($tables) ? $tables : explode(',',$tables);
    }
     
    //cycle through
    $return = "";
    foreach($tables as $table)
    {
        $result = mysql_query('SELECT * FROM '.$table);
        $num_fields = mysql_num_fields($result);
         
        //$return.= 'DROP TABLE '.$table.';';
        $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
        $return.= "\n\n".$row2[1].";\n\n";
         
        for ($i = 0; $i < $num_fields; $i++) 
        {
            while($row = mysql_fetch_row($result))
            {
                $return.= 'INSERT INTO '.$table.' VALUES(';
                for($j=0; $j<$num_fields; $j++) 
                {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = preg_replace("/\n/i", "\\n", $row[$j]);
                    if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
                    if ($j<($num_fields-1)) { $return.= ','; }
                }
                $return.= ");\n";
            }
        }
        $return.="\n\n\n";
    }


    $handle = fopen($file,'w+');
    fwrite($handle,$return);
    fclose($handle);
    
    clearstatcache();
    if(file_exists($file)){

        if($upload_ftp){
              // set up basic connection
            $conn_id = ftp_connect($ftp_server);

            // login with username and password
            $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
            if($login_result){
                 // upload a file
                ftp_pasv($conn_id, true);
                if (@ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) {
                 echo "Successfully backup & uploaded $file to $ftp_server\n";

                } else {
                 echo "There was a problem while uploading $file\n";
                }
            }else {
                echo 'Login FTP incorrect!';
            }

            // close the connection
            ftp_close($conn_id);
            // unlink($file);

        }
          
    }else {
        die("Error while created DB backup file");
    }
    

    if($send_to_email){

        $from = new SendGrid\Email($from_name, $from_email);

        $message =  'Backup Database at '.date("H:i:s d-m-Y");
        $subject = $message;

        $to = new SendGrid\Email( $from_name ,  $from_email);
        $content = new SendGrid\Content("text/html", $message);

        $mail = new SendGrid\Mail($from, $subject, $to, $content);

        // Attachment
        $file_encoded = base64_encode(file_get_contents($file));
        $attachment = new SendGrid\Attachment();
        $attachment->setType("application/text");
        $attachment->setContent($file_encoded);
        $attachment->setDisposition("attachment");
        $attachment->setFilename($file_name);
        $mail->addAttachment($attachment);
        
        $sg = new \SendGrid($apiKey_sendgrid);
        $response = $sg->client->mail()->send()->post($mail);
        $statusCode = trim($response->statusCode());

        print_r($response->headers());
        echo $response->body();

        if($statusCode=="202" || $statusCode=="200"){
            echo "Backup was sent via Email $to complete successfully!<br>";
        }else {
            echo "Error was sent via Email $to!<br>";
        }
    }

}
?>