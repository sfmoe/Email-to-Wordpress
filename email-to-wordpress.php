<?php
/**********************************
Title: Email to Wordpress Post

-retrieves the first email in the inbox
-creates a post from it and its attachments
 and sets the first attachment as the featured image
-moves message to trash

i run this from a cron job with wget since 
wordpress cron has given me nothing but trouble
it might just be me re-inventing the wheel but it works.


***********************************/


$wploc = "../"; //wordpress location
require_once( $wploc . 'wp-load.php' );
require_once( $wploc . 'wp-blog-header.php');  
require_once("phpmail/lib/MimeMailParser.class.php");

$Parser = new MimeMailParser();
$e_upl = wp_upload_dir();
$tmail = $e_upl['basedir']."/tempemail";


$empreg = "~<([^>]+)>~";
$emtags = '/tags: ?(.*)$/im';

$allowed =  Array("email1@any email dot com", "email2@any email dot com"); //email senders that are allowed to post

function emailpr(){
	global $Parser, $tmail, $empreg, $emtags, $allowed;
	
	$Parser->setPath($tmail); 
	$Parser->setStream(fopen($tmail, "r"));
	$Parser->setText(file_get_contents($tmail));
	$from = preg_match($empreg, $Parser->getHeader('from'),$matches);
	$from =  $matches[1];
	$title = $Parser->getHeader('subject');
	if (!in_array($from, $allowed)){
	    unlink($tmail);
	}else{

	//this strips the tag lines out and converts the plain text message to html and adds a custom gallery tag for the post
	$text = nl2br(preg_replace($emtags, "", $Parser->getMessageBody('text')))."<br/>[gallery link='file' columns='4' orderby='title' order='ASC']";

	if (preg_match($emtags, $Parser->getMessageBody('text'), $matches)) {
	        if (!empty($matches[1])) {
	            $post_tags = preg_split("/,\s*/", trim($matches[1]));
	          }
	    }
	 $new_post = array(
	        'post_title' => $title,
	        'post_content' => $text,
	        'post_status' => 'publish',
	        'post_author' => 2,
	        'post_type' => 'post',
	        'post_category' => array(0),
	        'tags_input' =>implode(", ", $post_tags)

	    );

	$em_p_id = wp_insert_post($new_post);

	$lf = ($Parser->getAttachments());
	$rotation = 1;
	foreach ($lf as $v)
	{
		$cur_file = NULL;
		 while($bytes = $v->read()) {
	        $cur_file .= $bytes;
	        }

		
		$wp_upload_dir = wp_upload_dir();


	$newupload = wp_upload_bits($v->filename, null, $cur_file, date(Y)."/".date(m));

$wp_filetype = wp_check_filetype(basename($v->filename), null );


	 $attachment = array(
	    'guid' => $wp_upload_dir['url'] ."/". basename( $v->filename ), 
	    'post_mime_type' => $wp_filetype['type'],
	    'post_title' => preg_replace('/\.[^.]+$/', '', basename($v->filename)),
	    'post_content' => '',
	    'post_status' => 'inherit'
	 );
	require_once('../wp-admin/includes/image.php');
$em_attach_id = wp_insert_attachment( $attachment, $newupload['file'], $em_p_id );
$amd = wp_generate_attachment_metadata($em_attach_id, $newupload['file']);



  wp_update_attachment_metadata($em_attach_id, $amd);
	

	if($rotation == 1){
		set_post_thumbnail( $em_p_id, $em_attach_id );
	}

	echo "<br/><hr/>";
	 echo wp_get_attachment_link($em_attach_id);
	echo "<br/>";
	$rotation++;
	}

	}
	
	  unlink($tmail);
	
}//end function emailpr


if(file_exists($tmail)){
emailpr();
}else{
//im using a secrete gmail account here
$connection = imap_open("{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX", 'secret email@gmail.com', 'password') or die('Cannot connect to mail: ' . imap_last_error()); 
$count = imap_num_msg($connection);


    if($count >= 1){
    $raw_body = imap_fetchheader($connection, 1) . PHP_EOL . imap_body($connection, 1);
    file_put_contents($tmail, $raw_body);
	chmod($tmail, 0777);
    imap_mail_move($connection, 1, "[Gmail]/Trash");
    	emailpr();
	}
    imap_expunge($connection); 
    imap_close($connection); 




}

?>
