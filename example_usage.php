<?php
/*
Setup Variables needed for the class
*/
include("./email-to-worpdress.php");

/*
this example uses gmail imap and asks for the inbox if you want to look in a particular label then replace INBOX with LABELNAME or LABELNAME/SUBLABEL
*/
$imapserver_string = "{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX"; 

//email and password
$email_user = "secretmeailtopost@emailserver.com";
$email_pass = "mysupersecretpasswordwithmillionpointsofentropy";

/*
an array of email addresses allowed to post to the blog, anything else gets moved to the location designated in $this->$read_mail_location which by default is TRASH
*/
$allowed = Array("dontignore@emailserver.com", "eatmyemail@emailserver.com", "yourmomcanpostto@emailserver.com");

//where is wordpress located
$wploc = './';

//the status of the post draft or publish
$post_status = "draft";


//start class and include variables
$etw = new ETW($imapserver_string, $email_user, $email_pass, $allowed, $wploc, $post_status);
//change gallery_shortcode from default [gallery]
$etw->gallery_shortcode = "[gallery link='file' columns='1' orderby='title' order='ASC']";
//change where the read mail is moved to here we are moving it to the Gmail Trash Label
$etw->read_mail_location = "[Gmail]/Trash";

//load messages and post them to worpdress
$etw->load_message();

?>