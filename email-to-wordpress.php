<?php

/**********************************
Title: Email to Wordpress

-retrieves the first email in the inbox
-creates a post from it and its attachments
 and sets the first attachment as the featured image
-moves message to trash

i run this from a cron job with wget since 
wordpress cron has given me nothing but trouble
it might just be me re-inventing the wheel but it works.
***********************************/

Class ETW{

public $wploc = "./";
public $wpdb = NULL;
public $email_user = NULL;
public $email_pass = NULL;
public $post_status = NULL;
public $empreg = "~<([^>]+)>~";
public $emtags = '/tags: ?(.*)$/im';
public $allowed =  Array(); //email senders that are allowed to post
public $imapserver_string = NULL;
public $connection = NULL;
public $url = NULL;
public $message = NULL;
public $gallery_shortcode = "[gallery]";
public $check_one = TRUE;
public $read_mail_location = "TRASH";
private $last_error  = NULL;



public function __construct($imapserver_string, $email_user, $email_pass, $allowed, $wploc=NULL, $post_status="draft"){

//setup variables

$this->url = (($_SERVER["SERVER_PORT"] == 443) ? "https://" : "http://").$_SERVER["SERVER_NAME"]."".$_SERVER['REQUEST_URI'];
$this->imapserver_string = $imapserver_string;
$this->email_user = $email_user;
$this->email_pass = $email_pass;
$this->allowed = $allowed;
$this->wploc = $wploc;
$this->post_status = $post_status;


$this->message = new stdClass;
    //load wordpress
    require_once( $this->wploc . 'wp-load.php' );
    global $wpdb;
    $this->wpdb =$wpdb;
    //open connection to imap server
    $this->connection = imap_open($this->imapserver_string, $email_user, $email_pass) 
or die('Cannot connect to mail: ' . imap_last_error()); 

}


public function load_message(){


$count = imap_num_msg($this->connection);
    

    if($count >= 1){
    $ct = 1;
    while ($ct <= $count) {

    $header = imap_header($this->connection, $ct);
        preg_match($this->empreg, $header->fromaddress,$raw_email); 
    $from  =  $raw_email[1];


    


   if(in_array($from, $this->allowed)){
    $structure = imap_fetchstructure($this->connection, $ct);

    $this->message->title = $header->subject;



    if((int)$structure->parts[0]->type == 1) 
    {
     $this->message->raw_body = imap_qprint(imap_fetchbody($this->connection,$ct,"1.2"));
    }
    else
    {
     $this->message->raw_body = nl2br(imap_qprint(imap_fetchbody($this->connection,$ct,"1")));
    }
 
    
    $this->message->attachments = $this->images($structure, $ct);


   $this->write_post($this->message);



    }//end if allowed



    imap_mail_move($this->connection, $ct, "$this->read_mail_location");
    imap_expunge($this->connection); 
   
     if($this->check_one){
        imap_close($this->connection);
        header("Location: {$this->url}");
        return true;
    }
    $ct++;
    }}else{
        imap_close($this->connection);
        exit("no messages to process.");
    }

    imap_close($this->connection);
    

}


function write_post($message){

//define post info 
$text = nl2br(preg_replace($this->emtags, "", $message->raw_body));
if(count($message->attachments) >= 1){
$text .="<br/>{$this->gallery_shortcode}";
}

$tags = Array();
if (preg_match($this->emtags,  $message->raw_body, $matches)) {
            if (!empty($matches[1])) {
                $tags = preg_split("/,\s*/", trim($matches[1]));
              }
        }

    $new_post = array(
            'post_title' => $message->title,
            'post_content' => $text,
            'post_status' => $this->post_status,
            'post_author' => 2,
            'post_type' => 'post',
            'post_category' => array(0),
            'tags_input' =>implode(", ", $tags)

        );

 

$em_p_id = wp_insert_post($new_post);

if($em_p_id){
if(count($message->attachments) >= 1){

  $image_count = 1;
  foreach($message->attachments as $att){


    $newupload = wp_upload_bits($att['name'], null, $att['data'], date(Y)."/".date(m));

    $wp_filetype = wp_check_filetype(basename($att['name']), null );

     $attachment = array(
        'guid' => $wp_upload_dir['url'] ."/". basename( $att['name'] ), 
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($att['name'])),
        'post_content' => '',
        'post_status' => 'inherit'
     );  


require_once($this->wploc .'wp-admin/includes/image.php');
     $em_attach_id = wp_insert_attachment( $attachment, $newupload['file'], $em_p_id );
     $amd = wp_generate_attachment_metadata($em_attach_id, $newupload['file']);



     wp_update_attachment_metadata($em_attach_id, $amd);
    

    if($image_count == 1){
        set_post_thumbnail( $em_p_id, $em_attach_id );
    }

    $image_count++;


  }
return true;
}
}else{
    return false;
}



}//end write_post

function images($structure, $mno){
$attachments = array();
$i = 0;
foreach($structure->parts as $prts){
//check subparts for inline attachments
if($prts->parts){
$sprt = 0;
foreach($prts->parts as $spr){
if($spr->type == 5){
$pnn = ($i+1).".".($sprt+1);
//push data to atachment array
$at = Array("name"=>$spr->parameters[0]->value, "data"=>base64_decode(imap_fetchbody($this->connection, $mno, $pnn)));
array_push($attachments, $at);
}
$sprt++;
}
}//end subparts inline attachments
//check for image attachments
if($prts->type == 5){
$pnn = $i+1;
//push data to atachment array
$at = Array("name"=>$prts->parameters[0]->value, "data"=>base64_decode(imap_fetchbody($this->connection, $mno, $pnn)));
array_push($attachments, $at);
}

$i++;
}//end $structure->parts
return $attachments;
}//end images()


}//end ETW class

?>
