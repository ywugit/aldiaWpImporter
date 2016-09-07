<?php
error_reporting(E_ALL);
ini_set("display_errors", "On");

include '/ngiohome/ngio/ccs/custIoScripts/util.php';

/////Stage2: Wordpress Aldia stage2 DB host
//$serverDB = "tstgcwadb1.test.ahc.belotechnologies.com";

////Production: Wordpress Aldia DB host
$serverDB = "kwebdb.ahc.belotechnologies.com";

////Production: Wordpress Aldia DB login info, stage2 DB shares the same login info
$usernameDB = "aldiaDallas_wp";
$passwordDB= "igTI4BQ3";
$nameDB = "aldiaDallas_wp";

////Stage2: Wordpress Aldia site host server
//$serverWP="http://stage.aldiadallas.com";

////Production: Wordpress Aldia site host server
$serverWP="http://www.aldiadallas.com";

//User used to post content to WP.
$usernameWP = "aldiastaff";
#$passwordWP = "staffaldia";
#$passwordWP = "@HB3l0$p@n!";
$passwordWP = "@HB3l0Sp@n!";

//directory that application is deployed on NG server
$pathApp = "/ngiohome/ngio/ccs/custIoScripts";
$pathInput = "/ngiohome/ngio/Baskets/Out/DMN/aldiatxOnline/SafeDir";
$pathProcessed = "/ngiohome/ngio/Baskets/Out/DMN/aldiatxOnline/processed";

/*
$pathApp = "/home/ywu";
$pathInput = "/home/ywu/input";
$pathProcessed = "$pathApp/processed";
*/

$pathTemp = "/ngiohome/ngio/Baskets/Out/DMN/aldiatxOnline/temp";
$fileNoExt = "";


//key is user lastname, value is wp_users.ID
$authorIDMap = "";

echo "pathApp: $pathApp\r\n";
echo "pathInput: $pathInput\r\n";
echo "pathTemp:$pathTemp\r\n";

/*get author id usered later for owner change.
 key is wp_users.user_login, value is wp_users.ID
*/
$authorList = getAuthorList($serverDB, $usernameDB, $passwordDB, $nameDB);
//load section-cat.csv file to array. Key is section, value is cat.
$secCatArray = getSectionCatsArray($pathApp);
if ($handle = opendir($pathInput)) 
{
    //loop all zip files in the /home/ywu/input directory
    while (false !== ($file = readdir($handle))) 
    {
        $fileNoExt = str_replace(".zip", "", $file);
        //echo "fileNoExt: $fileNoExt\r\n";
        if ('.' === $file) continue;
        if ('._.DS_Store' === $file) continue;
        if ('.DS_Store' === $file) continue;
        if ('SafeDir' === $file) continue;
        if ('Rejected' === $file) continue;
        if ('..' === $file) continue;
        if ('temp' === $file) continue;
        if ('processed' === $file) continue;

        echo "Processing Zip file: $file \r\n";
        //create temp dir and move file to temp dir and unzip the file
        shell_exec("[ -d $pathTemp ] || mkdir -p $pathTemp  && mv -f $pathInput/$file $pathTemp/ && cd $pathTemp/ && unzip $file");
            //loop unziped directory to get post authorID
            if ($handleTemp = opendir("$pathTemp")) //for author ID
            {
                   //loop the file in the zip directory and find the xml file and find author
                    while (false !== ($fileTemp = readdir($handleTemp)))
                    {
                      if ("$fileNoExt.xml" === $fileTemp)
                      {
                           echo "Process $pathTemp/$fileNoExt.xml to get Author\r\n";
                           $authorFirst = shell_exec("echo \"cat /io/article/author/@firstname\" |  xmllint --shell $pathTemp/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/firstname=//g'");
                           echo "authorFirst: $authorFirst\r\n";
                           $authorLast = shell_exec("echo \"cat /io/article/author/@surname\" |  xmllint --shell $pathTemp/$fileNoExt.xml |sed '/^\/ >/d' | sed 's/\"//g' | sed 's/surname=//g'");
                           $authorLast = preg_replace('{\/.*}', "", $authorLast);
                           echo "authorLast: $authorLast\r\n";
                           $authorLast = trim(strtolower($authorLast));
                           if(empty($authorIDMap[$authorLast]))
                           {
                              echo "authorID NOT in LOCAL\r\n";
                              $authorID = getAutherID($authorLast, $authorList);
                              echo "authorID: $authorID\r\n";
                              $authorIDMap[$authorLast] = $authorID;                
                           }
                           else
                           {
                              echo "authorID  in Local\r\n";
                              $authorID = $authorIDMap[$authorLast];
                           }
                      }//end of  while (false !== ($fileTemp = readdir($handleTemp)))

                    }//end of while (false !== ($fileTemp = readdir($handleTemp)))
   
            }//end of if ($handleTemp = opendir("$pathApp/$pathTemp")) for authorID
            closedir($handleTemp);
            /* loop directory to get images we need to post
             * if image is around 670x350 and image is not the only one, 
             * filter it out.
            */
            $imageUploadList = getImgUploadList("$pathTemp",$fileNoExt);
            /*loop unzipped directory to post images to WP 
            reset owner and get images list for post content
            */
            $imageList = "";
   
                    //loop the file in the zip directory and find the image and push to wp server
                    for ($x = 0; $x < count($imageUploadList); $x++) 
                    {

                      echo "Image file pushed to WP: $imageUploadList[$x]\r\n";
                      //util();
                      $imageID = postImage("$pathTemp", "$imageUploadList[$x]", $fileNoExt, $usernameWP, $passwordWP, "$serverWP");
                      //this imageList will be used by post story body to create galery
                      if (empty($imageList))
                      {
                         $imageList = "$imageID";
                      }
                      else
                      {
                         $imageList = $imageList .  " $imageID";
                      }
                      echo "imageList: $imageList\r\n";
                      //echo "imageId is $imageID\r\n";
                      if (empty($authorID) == false )
                      {
                        resetOwner($imageID, $authorID, $usernameDB, $passwordDB, $serverDB, $nameDB);
                      } 
                    }//end of  for ($x = 0; $x < count($imageUploadList); $x++)           
            //closedir($handleTemp);
    
            if ($handleTemp = opendir("$pathTemp"))//for post content
            {
                    while (false !== ($fileTemp = readdir($handleTemp))) //for post content
                    {
                      if ("$fileNoExt.xml" === $fileTemp)
                      {
                         echo "Content posted to WP: $fileNoExt.xml\r\n";
                         $storyID = postStory($secCatArray, $pathTemp, $fileNoExt,  $usernameWP, $passwordWP, $serverWP, $imageList, $usernameDB, $passwordDB, $serverDB,$nameDB);
                         echo "storyID: $storyID";                      
                         if (empty($authorID) == false )
                         {
                           resetOwner($storyID, $authorID, $usernameDB, $passwordDB, $serverDB, $nameDB);
                         }
                      }//end of if ("$fileNoExt.xml" === $fileTemp)

                    }//end of while (false !== ($fileTemp = readdir($handleTemp))) for post content
            }//end of if ($handleTemp = opendir("$pathApp/$pathTemp"))//for post content
            if (empty($imageList) == false)
            {
              attachImgToStory($storyID, $imageList, $usernameWP, $passwordWP, $serverWP);
            }
            $storyID = null;
            $imageList = null;
            closedir($handleTemp);
            shell_exec("mv  $pathTemp/*.zip $pathProcessed/.");
            shell_exec("rm  $pathTemp/*");
    }//end of while (false !== ($file = readdir($handle))) 
    closedir($handle);
} //end of if ($handle = opendir($path))
?>
