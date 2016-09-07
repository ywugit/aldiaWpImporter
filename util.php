<?php
require_once("IXR_Library.php.inc");


function postImage($fileDir, $fileName, $fileNoExt,$usernameWP, $passwordWP, $serverWP)
{
   $client->debug = false; //Set it to false in Production Environment


   $file = "$fileDir/$fileName";
   //echo "file in Util.postImage: $file";
   $fh = fopen($file, 'r');
   $fs = filesize($file);
   $theData = fread($fh, $fs);
   fclose($fh);
 
   $client = new IXR_Client("$serverWP/xmlrpc.php");
 
   $params = array('imageTitle' => 'mytest', 'name' => $fileName, 'type' => 'image/jpg', 'bits' => new IXR_Base64($theData), 'overwrite' => false);
   //upload image to WP
   $res = $client->query('wp.uploadFile',0, $usernameWP, $passwordWP, $params);
   $clientResponse = $client->getResponse();
 
   //echo URL & image id 
   $image_url =  $clientResponse['url'];  
   $image_id = $clientResponse['id'];
   echo "util:postImage: Image ID: $image_id\r\n";

   //get image meta info
   $imageTitle = "$fileName";
   $imageCaption = "";
   /*****************code below will show image title from image meta like 'this is title'
   $size = getimagesize ($file, $info);
   if(is_array($info)) 
   {
     if (isset($info["APP13"]))
     {
       if($iptc = iptcparse( $info["APP13"] ) ) 
       { 
         //$iptc = iptcparse($info["APP13"]);
         foreach (array_keys($iptc) as $s) 
         {             
           $c = count ($iptc[$s]);
            
           for ($i=0; $i <$c; $i++)
           {
            echo "+++++++++++++++++++++++++++++++";
            echo $s.' = '.$iptc[$s][$i].'\r\n';
            echo "+++++++++++++++++++++++++++++++";
           }
         }  
          if(!empty($iptc['2#005']['0']))
            $imageTitle = $iptc['2#005']['0'];
          //if(!empty($iptc['2#120']['0'])) 
            //$imageDesc = $iptc['2#120']['0'];
       }
     }
   }*/
   /*code blew will get title from org file and title will be like 'this_is title_2334'*/
   $imageTitle = shell_exec("echo \"cat /CCIObjects/object/children/object[@kind='Image']/attributes/attribute[@name='Name']/text()\" |  xmllint --shell $fileDir/$fileNoExt.org   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");
   $imageDesc = shell_exec("echo \"cat /CCIObjects/object/children/object[@kind='Image']/attributes/attribute[@name='IIM_Caption']/text()\" |  xmllint --shell $fileDir/$fileNoExt.org   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");

    $imageCaption = shell_exec("echo \"cat /io/article/reference/@caption\" |  xmllint --shell $fileDir/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/caption=//g'"); 
   $imageCredit = shell_exec("echo \"cat /io/article/reference/@photo_credit\" |  xmllint --shell $fileDir/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/photo_credit=//g'");
   $imageGrapher = shell_exec("echo \"cat /io/article/reference/@photographer\" |  xmllint --shell $fileDir/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/photographer=//g'");
   
   $imageCaption = removeNewline($imageCaption);
   $imageGrapher = removeNewline( $imageGrapher);
   $imageCredit = removeNewline($imageCredit);
   //$imageCredit = trim(preg_replace("/[\r\n]/", ' ', $imageCredit));
   //$imageGrapher = trim(preg_replace("/[\r\n]/", ' ', $imageGrapher));

   if( empty($imageGrapher) == false and empty($imageCredit) == false)
   {
     $imageCaption = "$imageCaption" . " (" . "$imageCredit" . "/" . "$imageGrapher" . ")"; 
   }
   else if ( empty($imageGrapher) == true and empty($imageCredit) == true)
   {
   }
   else 
   {
     $imageCaption = "$imageCaption" . "(" . "$imageCredit" . "$imageGrapher" . ")";

   }

   $imageName = shell_exec("echo \"cat /io/multimediaGroup/@name\" |  xmllint --shell $fileDir/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");


   $img_attach_content2 = array(
                'post_type'  => 'attachment',   
                'post_status' => 'inherit', 
                'post_title' => $imageTitle, 
                'post_name' => $imageName, 
                'post_parent'  => '',
                'post_excerpt'   => $imageCaption,
                'post_content'   => $imageDesc,
                'post_mime_type' => 'image/jpg'
                 );

     //update image meta info to WP
     $res2 = $client -> query('wp.editPost', 0, $usernameWP, $passwordWP,      $image_id, $img_attach_content2);

    // $postIDimg =  $client->getResponse();    
     //echo "utii:postImage response: $postIDimg\r\n";
     return "$image_id";
}


function getAuthorList($serverDB, $usernameDB, $passwordDB, $nameDB)
{
  // Create connection
  $conn = new mysqli($serverDB, $usernameDB, $passwordDB, $nameDB);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "select id, user_login from wp_users";
  $result = $conn->query($sql);
  
  if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
       // echo "id: " . $row["id"]. " - userLogin: " . $row["user_login"] . "\r\n";
        $userLogin = $row["user_login"];
        $nameList[trim(strtolower($userLogin))] = $row["id"];
      }
   } else {
    echo "0 results\r\n";
   }
  $conn->close();
  return $nameList;
}
function getDBconnect($usernameDB, $passwordDB, $serverDB, $nameDB)
{
  // Create connection
  $conn = new mysqli($serverDB, $usernameDB, $passwordDB, $nameDB);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }
  return $conn;
}
function resetOwner($contentID, $authorID, $usernameDB, $passwordDB, $serverDB,$nameDB)
{
  $conn = getDBConnect($usernameDB, $passwordDB, $serverDB, $nameDB);
  $sql = "select id, user_login from wp_users";
  $sql = "update wp_posts set post_author='$authorID" . "' where id='" . $contentID . "'";
  echo "updateSQL: $sql\r\n";
  $result = $conn->query($sql);
  $conn->close();
}

function updateBylineInfo($update, $postID, $ngByline, $packageId, $usernameDB, $passwordDB, $serverDB,$nameDB)
{
  $conn = getDBConnect($usernameDB, $passwordDB, $serverDB, $nameDB);
  $sql = "set names 'utf8'";
  $result = $conn->query($sql);
  $sql = "insert into wp_postmeta( post_id, meta_key, meta_value) values('" . $postID . "','authorByline','" . $ngByline . "')";
//  $sql = "update wp_posts set post_author='$authorID" . "' where id='" . $contentID . "'";
  echo "updateSQL: $sql\r\n";
  $result = $conn->query($sql);

  if ($update == "false")
  {
     $sql = "insert into wp_postmeta( post_id, meta_key, meta_value) values('" . $postID . "','packageId','" . trim($packageId) . "')";

      echo "updateSQL-----again: $sql\r\n";

      $result = $conn->query($sql);
  }

  $conn->close();
 
}


function getPostId($packageId, $usernameDB, $passwordDB, $serverDB,$nameDB)
{
  
  $conn = getDBConnect($usernameDB, $passwordDB, $serverDB, $nameDB);
  $sql = "set names 'utf8'";
  $result = $conn->query($sql);
  $sql = "select post_id from wp_postmeta where meta_key = 'packageId' and meta_value  = '" . $packageId . "'";
  echo "selectMetaKeySQL: $sql\r\n";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
        $postid = $row["post_id"];
      }
   } else {
    echo "0 results\r\n";
   }

  $conn->close();
  return $postid;

}



function getAutherID($authorLast, $authorList)
{
   foreach ($authorList as $key => $value) {
      if ( empty($authorLast)) continue;
      if (strpos("$key", "$authorLast") !== false)
      {
         return $value;
      }
   }
   return "";
}

function postStory($secCatArray, $pathTemp,  $fileNoExt, $usernameWP, $passwordWP, $serverWP, $imageList,  $usernameDB, $passwordDB, $serverDB,$nameDB)
{
    $gallery = "[gallery ids=\"$imageList\"]";
    $fileTemp = $fileNoExt . ".xml";
    $fileOrg = $fileNoExt . ".org";

    echo "postStory: process $pathTemp/$fileTemp\r\n";
    echo "imageList::$imageList:"; 

    $imageListArr = array();    
    
    if (empty($imageList) == false)
    {
       $imageListArr = explode(' ', $imageList);
    }
    
    $thumbnail_id = "";
    if (empty($imageListArr) == false)
    {
        $thumbnail_id = $imageListArr[0];
        echo "nail id : $thumbnail_id\r\n";
    }
    //get post content from xml
    $title = shell_exec("echo \"cat /io/article/field[@name='TITLE']/text()\" |  xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g'");
    $body = shell_exec("echo \"cat /io/article/field[@name='BODY']\" |  xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/<\/field>//g' | sed 's/<field name=BODY>//g'");
    if(count($imageListArr)>1)
    {
      $body = $gallery . "<br/>" .  $body;
    }

    $leadText = shell_exec("echo \"cat /io/article/field[@name='LEADTEXT']/text()\" |  xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g'");


    $ngStatus = shell_exec("echo \"cat /io/article/@state\" | xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/state=//g'");    
    //$wpStatus = 'published';
    //echo "wp status: ********** $wpStatus\r\n";    
    $catsFromNG = shell_exec("echo \"cat /io/article/section\" |  xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/<section name=OnFrontPage rank=1\/>//g' | sed 's/ *homeSection.*priority.*\?\/>//g' | sed 's/ rank=.*\?\/>//g' | sed 's/<section name=//g' | sed 's/-*//g'  | sed '/^\s*\$/d' | tr '\n' ',' | sed 's/,$//'");
   //catsFromNG looks like "Tu Sabor,NFL/Dallas Cowboys,Lo+Caliente" 

    $categoriesu = getStoryCats($secCatArray,$catsFromNG);
   

    $ngByline = shell_exec("echo \"cat /io/article/field[@name='BYLINE']/text()\" |  xmllint --shell $pathTemp/$fileTemp |sed '/^\/ >/d' | sed 's/\"//g'"); 
    //echo "byline********$ngByline";

    $encoding = ini_get("default_charset");

    //$title = htmlentities($title,ENT_NOQUOTES,$encoding);
    //$keywords = htmlentities($keywords,ENT_NOQUOTES,$encoding);

   // $contentStateNum  = shell_exec("echo \"cat /CCIObjects/object/children/object[@kind='Text']/attributes/attribute[@name='ContentState']/text()\" |  xmllint --shell $pathTemp/$fileOrg   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");
    //$contentState = getContentState(intval($contentStateNum));
   // $contentState = getContentState("$pathTemp/$fileOrg");
    $contentState = getStatus($ngStatus);
    echo "contentState: $contentState";

    $lastModDate = shell_exec("echo \"cat /CCIObjects/object/attributes/attribute[@name='LastPublication']/text()\" |  xmllint --shell $pathTemp/$fileOrg   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");


    echo "modDate********$lastModDate\r\n";
    date_default_timezone_set('America/Chicago'); 
    $lastModDateSql = new IXR_Date(strtotime($lastModDate));

    //echo  "modDateSql********$lastModDateSql\r\n";
    //$creationDate = shell_exec("echo \"cat /CCIObjects/@exporttime\" | xmllint --shell $pathTemp/$fileOrg |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'  | sed 's/exporttime=//g' | sed 's/T/ /g'");
    $creationDate = shell_exec("echo \"cat /CCIObjects/@exporttime\" | xmllint --shell $pathTemp/$fileOrg |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'  | sed 's/exporttime=//g' ");

    $creationDate = trim(preg_replace("/[\r\n]/", ' ', $creationDate)). '-10:00';

    $creationDateSql = new IXR_Date(strtotime($creationDate));
    echo  "creationDate********$creationDate\r\n";

    $packageId = shell_exec("echo \"cat /CCIObjects/object[@kind='Budget']/@id\" | xmllint --shell $pathTemp/$fileOrg |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'  | sed 's/id=//g' ");
    $packageId = trim($packageId);
    echo "packageId: $packageId\r\n";

    $textId = shell_exec("echo \"cat /CCIObjects/object/children/object[@kind='Text']/@id\" | xmllint --shell $pathTemp/$fileOrg |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'  | sed 's/id=//g' ");

    $textId = preg_replace('{.*\.}' , "", $textId);
    $textId = trim($textId);
    echo "textId ================== $textId\r\n";
    $postId = getPostId($packageId, $usernameDB, $passwordDB, $serverDB,$nameDB);
//    if(intval(trim($textId)) <= 1)
  //  {
      if( empty($imageList) == false)
      {
         $content = array(
                 'title'=>$title,
                 'description'=>$body,
                 'mt_excerpt'=>$leadText,
                 'date_created_gmt'=>$creationDateSql,
                  'mt_allow_comments'=>0, // 1 to allow comments 
                 'mt_allow_pings'=>0, // 1 to allow trackbacks 
                 'post_type'=>'post',
                 'post_status' => "$contentState",
                  'wp_post_thumbnail' => $thumbnail_id,
                  'categories'=>$categoriesu
              );
      }
      else 
      {
         $content = array(
                 'title'=>$title,
                 'description'=>$body,
                 'mt_excerpt'=>$leadText,
                 'date_created_gmt'=>$creationDateSql,
                  'mt_allow_comments'=>0, // 1 to allow comments 
                 'mt_allow_pings'=>0, // 1 to allow trackbacks 
                 'post_type'=>'post',
                 'post_status' => "$contentState",
                  'categories'=>$categoriesu
              );

      }
    if(intval($textId) <= 1 or empty($postId) == true)
    {
      $client = new IXR_Client("$serverWP/xmlrpc.php");
      $client->debug = true; //Set it to false in Production Environment;

      $params = array(0,$usernameWP,$passwordWP,$content,true); // Last parameter is 'true' which means post immediately, to save as draft set it as 'false' 
// Run a query for PHP 
      if (!$client->query('metaWeblog.newPost', $params)) 
      {
        die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());
      }
      else
      {
      //echo "Article Posted Successfully"; 
      $ID =  $client->getResponse();
      if ($ID)
       echo 'Article Posted Successfully. ID = '.$ID;
       updateBylineInfo("false", $ID, $ngByline, $packageId,  $usernameDB, $passwordDB, $serverDB,$nameDB);
       return $ID;
      }//end of if (!$client->query('metaWeblog.newPost', $params))  
    }//end of if textId <= 1
    else //existing post, update it
    {
       echo "nothing happen\r\n";
      // $postId = getPostId($packageId, $usernameDB, $passwordDB, $serverDB,$nameDB);
       echo "postId ======$postId===============\r\n";


      $client = new IXR_Client("$serverWP/xmlrpc.php");
      $client->debug = true; //Set it to false in Production Environment;

      $params = array($postId,$usernameWP,$passwordWP,$content,true); // Last parameter is 'true' which means post immediately, to save as draft set it as 'false' 
// Run a query for PHP 
      if (!$client->query('metaWeblog.editPost', $params))
      {
        die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());
      }
      else
      {
      //echo "Article updated Successfully"; 
      $ID =  $client->getResponse();
      if ($ID)
       echo 'Article updated Successfully. ID = '.$ID;
       //if true, not not insert  meta_key and meta value.
       updateBylineInfo("true", $postId, $ngByline, $packageId,  $usernameDB, $passwordDB, $serverDB,$nameDB);
       return $postId;
      }//end of if (!$client->query('metaWeblog.newPost', $params)) 

    }


}//end of function postStory

function attachImgToStory($storyID, $imageList, $usernameWP, $passwordWP, $serverWP)
{

    $client = new IXR_Client("$serverWP/xmlrpc.php");
    $client->debug = false; //Set it to false in Production Environment;
    
    echo "attachImgToStory:imageList: $imageList";
    $imgIDArr = explode(" ", $imageList);
    $imgIDArrLen =  count($imgIDArr);

    for($x = 0; $x <  $imgIDArrLen; $x++)
    {
       $img_attach_content = array(
                'post_parent'  => "$storyID",
                 );

       $res = $client -> query('wp.editPost', 0, $usernameWP, $passwordWP, $imgIDArr[$x], $img_attach_content);

       //$postResponse =  $client->getResponse();
       echo "utii:AttachImgToStory attach $imgIDArr[$x] to $storyID \r\n";
     }//end of for($x = 0; $x < count($imgIDArr); $x++)

}//end of funtion attachImgToStory

function getSectionCatsArray($filePath)
{
  //open section-cat.csv to array
  $handle = fopen("$filePath/section-cat.csv", 'r') or die("Could not open file: $filePath/section-cat.csv" );
  $section_cat_array = array();

  // Better use a specified length (second parameter) instead of 0
  // It slows down the whole process of reading the data!
   while (($line = fgetcsv($handle,0 , ',')) !== FALSE) 
   {
     // echo "line0--------$line[0]\r\n";
     // echo "line1--------$line[1]\r\n";
      $section_cat_array[$line[0]] = $line[1];
     // $line[0] = null;
      //$line[1] = null;
    }
    /*
    foreach ($section_cat_array as $key => $value) 
    {
      echo "section-----: $key :::::: cat------: $value\r\n";
    }
    */
    return $section_cat_array;
}//end of function getCatsForWP($catsFromNG)

function getStoryCats($secCatArray,$catsFromNG)
{
  $storyCats = array();
  echo "catsFromN: ---$catsFromNG\r\n";
  $catsFromNGArr = explode(",", $catsFromNG);
  echo  "catsFramNGArr[0]-------$catsFromNGArr[0]\r\n";
  for($x = 0; $x < count($catsFromNGArr); $x++)
  {
      $storyCats[$x] = $secCatArray[$catsFromNGArr[$x]];
  }
   
   foreach ($storyCats as $key => $value)
   {
      echo "storyCats-----: $key :::::: catValue------: $value\r\n";
   }
   

  return $storyCats;
}

function getImgUploadList($filePath,  $fileNoExt)
{
    $imageTitlePre = "";
    $imageUploadList = array();
    $imageTitlePost = "";

    if ($handleTemp = opendir($filePath))//for post images 
    {
       //loop the file in the zip directory and find the image and push to wp server
       $imageTitlePre = "";
       while (false !== ($fileTemp = readdir($handleTemp)))
       {
        //$imageTitlePre = "";
        //$imageTitlePre = $imageTitlePost;
        //$imageTitlePost = "";
        if ('.' === $fileTemp) continue;
        if ('..' === $fileTemp) continue;
        if ("$fileNoExt.xml" === $fileTemp) continue;
        if ("$fileNoExt.org" === $fileTemp) continue;
        if ("$fileNoExt.zip" === $fileTemp) continue;

        $imageSize = getimagesize("$filePath/$fileTemp");
        $imageSizeL = (int)$imageSize[0];
        $imageSizeH = (int)$imageSize[1];
        $imageTitlePost = getImgTitle("$filePath/$fileTemp");
        
        if  ((665 <= $imageSizeL and  $imageSizeL <=675) && (345 <= $imageSizeH and  $imageSizeH<= 355) )
        {
          echo "filename: $fileTemp : titlePre: $imageTitlePre, titlePost: $imageTitlePost\r\n";
          if ($imageTitlePre !== $imageTitlePost)
          {
             echo "pushed to list: $fileTemp\r\n";
             //array_push($imageUploadList, $fileTemp);
             $imageUploadList[] = $fileTemp;
          }
        }
       else
       {
          if($imageTitlePre == $imageTitlePost)
          {
            array_pop ($imageUploadList);
          }
          echo "utii:postedImageSize: $fileTemp, $imageSizeL x $imageSizeH\r\n"; 
          array_push($imageUploadList, "$fileTemp");
          //$imageTitlePre = $imageTitlePost;
       }

        $imageTitlePre = $imageTitlePost;
       }
     }

     return $imageUploadList;
}

function getImgTitle($file)
{
   $imageTitle = "";
   $size = getimagesize ($file, $info);
   if(is_array($info))
   {
     if (isset($info["APP13"]))
     {
       if($iptc = iptcparse( $info["APP13"] ) )
       {
         if(!empty($iptc['2#005']['0']))
            $imageTitle = $iptc['2#005']['0'];
          if(!empty($iptc['2#120']['0']))
            $imageDesc = $iptc['2#120']['0'];
       }
     }
   }
   echo "getImageTitle----------------$imageTitle\r\n";
   return $imageTitle;
}

function getStatus($ngStatus)
{
    $statusMap = array(
                 'published'=>'publish',
                 'submitted'=>'pending',
                 'draft'=>'draft',  
                 'approved'=>'pending',
                 'deleted' => 'trash'
              );
     
     return $statusMap[trim($ngStatus)];

}

function removeNewline($string)
{
    $strTemp =  str_replace('&#10;',null, $string);
    return trim(preg_replace("/[\r\n]/", ' ', $strTemp));
}


function strToDate ($string)
{
        date_default_timezone_set('America/Chicago');
      /* 
        $datetime = new DateTime('2008-08-03 14:52:10');
          $datetimeStr = $datetime->format(DateTime::DATE_ISO8601);
          
        //echo "Formatted ISO: " . $datetime->format(DATE_ISO8601) . "<br />\n";
         return $datetime = new DateTime($datetimeStr);
       */
$dateTime = DateTime::createFromFormat(
    DateTime::ISO8601,
    '2009-04-16T12:07:23.596Z'
);

return $dateTime;
 
       //$xmlrpc_date = new IXR_Date($string);
        
       //return string         return date(DATE_ISO8601, strtotime('2010-12-30 23:21:46'));

/*
  	$theTimeDate = $string; //variable of date and time from script / database 
	$pubdate       = new DateTime($theTimeDate );
	$pubdate       = $pubdate->format(DateTime::ISO8601); //format date into the ISO8601 standard which WordPress likes...
	$pubdate       = str_replace("-", "", $pubdate); // remove the dashes 
	$removeTimeOffset = explode("+", $pubdate); // remove the time offset (split at the '+' in the date / time)
	$pubdate       = $removeTimeOffset[0] . "Z"; // Append a Z to the string - we've now formatted the date and time.
        //echo "pubdate: --------$pubdate";

        //xmlrpc_set_type($pubdate, 'datetime');
        return $pubdate;
*/

     /*
   date_default_timezone_set('America/Chicago');
  $timestamp = strtotime($string);
  return date("Y-m-d H:i:s", $timestamp);  
  */
}

function getContentState($fileOrg)
{
    $contentStateStr  = shell_exec("echo \"cat /CCIObjects/object/children/object[@kind='Text']/attributes/attribute[@name='ContentState']/text()\" |  xmllint --shell $fileOrg   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");
    $autoExport  = shell_exec("echo \"cat /CCIObjects/object[@kind = 'Budget']/attributes/attribute[@name = 'AutoExport']/text()\" |  xmllint --shell $fileOrg   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");
    $groupState  = shell_exec("echo \"cat /CCIObjects/object[@kind = 'Budget']/attributes/attribute[@name = 'State' and @group = 'ExtraInfo']/text()\" |  xmllint --shell $fileOrg   |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/name=//g'");
   $contentStateNum = intval($contentStateStr);
   $contentState = "";


   echo "autoExport: -------------$autoExport\r\n";
   echo "contentStateNum : ----------$contentStateNum\r\n"; 
  if ($groupState == "Deleted")
  {
     //NG contentState: deleted;
     $contentState = "trash";
  }
  else if ($contentStateNum < 300)
   {
     //NG contentStat: draft
     $contentState = "draft";
   }
  else if ($contentStateNum < 1500)
  {
     //NG contentStat: submitted
     //$contentState = "future";
     $contentState = "pending";
  }
  else if ($contentStateNum >= 1500 and $autoExport == true)
  {
     //NG contentStat: published
     $contentState = "publish";
     echo "in publish.............\r\n";
  }
  else if ($contentStateNum >=1500)
  {
     //NG contentStat: approved
     $contentState = "pending";
     echo "in pending......\r\n";
  }
  else
  {
    //NG contentStat: draft
    $contentState = "draft";
  }
  echo "contentState: ==================$contentState\r\n";
  return $contentState;
}

?>
