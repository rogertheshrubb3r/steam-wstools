<?php
$verbose=1;
// get steam workshop files/collections
// work in progress [2016-04-12]

/*
TODO:collections within collections,  autodetect coll or single item, cleanup the mess, ...
merge with old version: print dl size, ...

fix:
 - reported collection size  (see old version - $items_size+=$details['file_size']; )
 - authors_array report (in coll description)
- check if filenames exist, option to overwrite/skip (currently skips!?)
- create folders for collections [done]
- cache author id/names in an array if verbose [done]
'filetype' in coll. info: 0 = regular (downloadable) workshop item; 2=collection

changelog:
2016-04-12:
  accept file as param; moved file dl part to own function
  some fixes, possibly other (hopefully minor) bugs introduced
*/

$authors_array=array();
$WSItemInfo_calls=0;
$steamUserInfo_calls=0;

class cURL {
  var $headers;
  var $user_agent;
  var $compression;
  var $cookie_file;
  var $proxy;
  function cURL($cookies=TRUE,$cookie='',$compression='gzip',$proxy='') {
    $this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg, image/png';
    $this->headers[] = 'Connection: Keep-Alive';
    $this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
    $this->user_agent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';
    $this->compression=$compression;
    $this->proxy=$proxy;
    $this->cookies=$cookies;
//if ($this->cookies == TRUE) $this->cookie($cookie);
  }
/* // we don't need no cookies
function cookie($cookie_file) {
if (file_exists($cookie_file)) {
$this->cookie_file=$cookie_file;
} else {
fopen($cookie_file,'w') or $this->error('The cookie file could not be opened. Make sure this directory has the correct permissions');
$this->cookie_file=$cookie_file;
if (file_exists($cookie_file)) fclose($this->cookie_file);
}
}
*/
function get($url) {
  $process = curl_init($url);
  curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
  curl_setopt($process, CURLOPT_HEADER, 0);
  curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
//if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
//if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
  curl_setopt($process,CURLOPT_ENCODING , $this->compression);
  curl_setopt($process, CURLOPT_TIMEOUT, 30);
  if ($this->proxy) curl_setopt($process, CURLOPT_PROXY, $this->proxy);
  curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
  $return = curl_exec($process);
  curl_close($process);
  return $return;
}
function post($url,$data) {
  $process = curl_init($url);
  curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
  curl_setopt($process, CURLOPT_HEADER, 0);
  curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
  //if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
  //if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
  curl_setopt($process, CURLOPT_ENCODING , $this->compression);
  curl_setopt($process, CURLOPT_TIMEOUT, 30);
  if ($this->proxy) curl_setopt($process, CURLOPT_PROXY, $this->proxy);
  curl_setopt($process, CURLOPT_POSTFIELDS, $data);
  curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($process, CURLOPT_POST, 1);
  $return = curl_exec($process);
  curl_close($process);
  return $return;
}
function error($error) {
         echo "cURL Error:\n$error\n";
//         echo "<center><div style='width:500px;border: 3px solid #FFEEFF; padding: 3px; background-color: #FFDDFF;font-family: verdana; font-size: 10px'><b>cURL Error</b><br>$error</div></center>";
die;
}
}


/*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*/
define ('CDurl',"http://api.steampowered.com/ISteamRemoteStorage/GetCollectionDetails/v1/");
// collectioncount uint32      Number of collections being requested; publishedfileids[0] uint64      collection ids to get the details for
define ('FDurl',"http://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/");
// itemcount   uint32      Number of items being requested ; publishedfileids[0] uint64      published file id to look up
/*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*/

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    // Uncomment one of the following alternatives
    $bytes /= pow(1024, $pow);
    // $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}


function sanitize_fname($s) {
    $illegal_chars=array("<",">",":","\"","/","\\","|","?","*");
    return str_replace($illegal_chars, "-", $s);
}

/**
  * convert xml string to php array - useful to get a serializable value
  *
  * @param string $xmlstr
  * @return array
  *
  * @author Adrien aka Gaarf & contributors
  * @see http://gaarf.info/2009/08/13/xml-string-to-php-array/
*/
function xmlstr_to_array($xmlstr) {
  $doc = new DOMDocument();
  $doc->loadXML($xmlstr);
  $root = $doc->documentElement;
  $output = domnode_to_array($root);
  $output['@root'] = $root->tagName;
  return $output;
}

function domnode_to_array($node) {
  $output = array();
  switch ($node->nodeType) {
    case XML_CDATA_SECTION_NODE:
    case XML_TEXT_NODE:
      $output = trim($node->textContent);
    break;
    case XML_ELEMENT_NODE:
      for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
        $child = $node->childNodes->item($i);
        $v = domnode_to_array($child);
        if(isset($child->tagName)) {
          $t = $child->tagName;
          if(!isset($output[$t])) $output[$t] = array();
// kidd fix?
          if (!is_string($output[$t]) ) $output[$t][] = $v;
//          $output[$t][] = $v;
        }
        elseif($v || $v === '0') {
          $output = (string) $v;
        }
      }
      if($node->attributes->length && !is_array($output)) { //Has attributes but isn't an array
        $output = array('@content'=>$output); //Change output into an array.
      }
      if(is_array($output)) {
        if($node->attributes->length) {
          $a = array();
          foreach($node->attributes as $attrName => $attrNode) {
            $a[$attrName] = (string) $attrNode->value;
          }
          $output['@attributes'] = $a;
        }
        foreach ($output as $t => $v) {
          if(is_array($v) && count($v)==1 && $t!='@attributes') {
            $output[$t] = $v[0];
          }
        }
      }
    break;
  }
  return $output;
}
/*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*/

function steamWSItemInfo($id) { // Steam Workshop Item Info
    global $authors_array;
    global $WSItemInfo_calls; $WSItemInfo_calls++;
    $cc = new cURL();
    $params="format=json&itemcount=1&publishedfileids[0]=$id";
    $out=json_decode($cc->post(FDurl,$params),TRUE);
    return $out['response']['publishedfiledetails'][0];
}

function steamWSCollectionFiles($id) {
    $cc = new cURL();
    $params="format=json&collectioncount=1&publishedfileids[0]=$id";
    $out=json_decode($cc->post(CDurl,$params),TRUE);
    return $out['response']['collectiondetails'][0]['children'];
}

function steamUserInfo($id) {
    global $authors_array;
    global $steamUserInfo_calls; $steamUserInfo_calls++;
    $cc = new cURL();
    $params="xml=1";
    $result=xmlstr_to_array($cc->get("http://steamcommunity.com/profiles/$id/?xml=1",$params));
    $authors_array[$id]=$result;
    return $result;
}

function steamWSPrintItemInfo($item,$verbose=FALSE) {
// $item is array as returned by steamWSCollectionFiles()
    global $authors_array;
        $out .="Title             : ".$item['title']."\n";
        $out .="Author            : ";
        if ( $verbose ) {
           if ( isset($authors_array[$item['creator']]) ) {
//           echo "(author cached: ".$item['creator']."=".$authors_array[$item['creator']]['steamID'].")\n";
             $author=$authors_array[$item['creator']];
           } else {
             $author=steamUserInfo($item['creator']);
             $authors_array[$item['creator']]=$author;

//             echo $authors_array[$item['creator']];
           }

//           $author= (isset($authors_array[$item['creator']]) ? $authors_array[$item['creator']] : steamUserInfo($item['creator']) );
           $out .=$author['steamID']." (https://steamcommunity.com/profiles/".$item['creator'].")\n";
        }
           else $out .=$item['creator']."\n";
        $out .="URL               : https://steamcommunity.com/workshop/filedetails/?id=".$item['publishedfileid']."\n";
        $out .="Last update       : ".gmdate("Y-m-d H:i:s", $item['time_updated'])." GMT, created: ".gmdate("Y-m-d H:i:s", $item['time_created'])." GMT\n";
        $out .="Subscriptions     : ".$item['subscriptions']." (lifetime: ".$item['lifetime_subscriptions']."); favorited: ".$item['favorited']." (lifetime: ".$item['lifetime_favorited']."); views: ".$item['views']."\n";
    // tags
        if ( isset($item['tags']) ) {
           $out .="Tags              : ";
           unset ($putcomma);
           foreach ($item['tags'] as $kk=>$vv) {
                   if ( $putcomma ) { $out .=", "; } else $putcomma=TRUE;
                   $out .=$vv['tag'];
           } // end foreach: $item['tags']
           unset ($putcomma);
           $out .="\n";
        }
        $out.="\n";
//        $out .="----- description -----\n";
        $out .=$item['description']."\n";
//        echo "--- end description ---\n";
        $out .="\n";
        return $out;

} // --- end function: steamWSPrintItemInfo

function steamWSItemDownload($fid, $path=".", $verbose=TRUE) {
// wip!! - to replace in steamWSCollectionDownload
    global $authors_array;
    $append_id=TRUE;

    $p=$path;

    $dl_size=0;

// download stuff
        $details=steamWSItemInfo($fid);
        if ( !strlen($details['file_url']) ) { echo " item $fid has no download url (maybe deleted or hidden?)\n"; return false; }
        echo "\n";
        $desc=steamWSPrintItemInfo($details,TRUE);
        echo $desc;
        echo "downloading file ({$details['file_size']} bytes)...";
        $items_size+=$details['file_size'];
        $fname=$p."/".sanitize_fname($details['title']);
        if ($append_id) $fname.='-'.$fid;
        // workshop file
        $fn=$fname.".zip";
        if (!file_exists($fn)) { file_put_contents($fn, fopen($details['file_url'], 'r')); $dl_size=$details['file_size'];} else echo "file $fn exists, skipping!\n";
        // description
        $fn=$fname.".txt";
        if (!file_exists($fn)) file_put_contents($fn, $desc); else echo "file $fn exists, skipping!\n";
        // preview image
        $fn=$fname.".jpg";
        if (!file_exists($fn))  file_put_contents($fn, fopen($details['preview_url'], 'r')); else echo "file $fn exists, skipping!\n";
// end download stuff
    return $dl_size;
}

function steamWSCollectionDownload($cid, $verbose=TRUE, $path=".", $createdir=TRUE, $limit=FALSE, $limitdl=FALSE) {
// $cid = workshop collection id
// returns: 0 for fail (todo); 1=collection processed/downloaded; 2=item processed/downloaded
    global $authors_array;
    $append_id=TRUE;
    $p=$path;
    $items_processed=0;
    $files_processed=0;
    $files_downloaded=0;
    $items_size=0;
    $dl_size=0;

    $collection_details=steamWSItemInfo($cid);
    $collection_desc="";
    $descfname=sanitize_fname($collection_details['title'].".txt");

    if ( $createdir ) { // make path
      $p.="/".sanitize_fname($collection_details['title']);
      if ( $append_id ) $p.="-".$collection_details['publishedfileid'];
      if (!file_exists($p)) mkdir($p);
    }
/*
$collection_desc.="Title           : ".$collection_details['title']."\n";
$collection_desc.="Last update     : ".gmdate("Y-m-d H:i:s", $collection_details['time_updated']).", created: ".gmdate("Y-m-d H:i:s", $collection_details['time_created'])."\n";
$collection_desc.="\n".$collection_details['description']."\n";
*/
//$collection_desc.="desc filename   : ".$descfname."\n";
    $collection_desc=steamWSPrintItemInfo($collection_details,TRUE);
    $items=steamWSCollectionFiles($cid);
    if ( !count($items) ) {
       echo "no items - empty collection or single file?\n";
       echo "attempting download...\n";
       steamWSItemDownload($cid);
       return 2;
    }

    $collection_desc.="Items             : ".count($items)."\n";
    echo $collection_desc;
    $fname=$p."/".sanitize_fname($collection_details['title'])."-collection-".$collection_details['publishedfileid'];

    // description
    $descfname=$fname.".txt";
    if (!file_exists($fn)) file_put_contents($descfname, $collection_desc); else echo "file $descfname exists, skipping!\n";
    // preview image
    $fn=$fname.".jpg";
    if (!file_exists($fn))  file_put_contents($fn, fopen($collection_details['preview_url'], 'r')); else echo "file $fn exists, skipping!\n";

//get collection items
    $params="format=json&collectioncount=1&publishedfileids[0]=$id";

// get collection files
    foreach ($items as $k=>$v) {
        echo "k:$k v:".$v['publishedfileid']."; type: ".$v['filetype']."";
        if ( $items_processed===$limit ) { echo "items limit reached: $items_processed\n"; break; }
        $items_processed++;
        if ( $v['filetype'] !== 0 ) { echo " item ".$v['publishedfileid']." is not regular file (0) but ".$details['filetype']; /* print_r($v['filetype']); */ continue; }

        $itemdl_size=steamWSItemDownload($v['publishedfileid'],$p,TRUE);
        if ($itemdl_size) { $files_downloaded++; $dl_size+=$itemdl_size; }

        $files_processed++;
        if ( $files_downloaded===$limitdl ) { echo "dl limit reached: $files_downloaded\n"; break; }
        echo " done.\n";

    } // end foreach: $items


// !!here!!
    $desc="Authors           : ";
    unset ($putcomma);
    foreach ($authors_array as $k=>$v) {
        if ( $v['count'] ) {
           if ( $putcomma ) $desc.=", "; else $putcomma=TRUE;
           $desc.="{$v['steamID']} ({$v['count']})";
        }
    } // end foreach: $authors_array
    unset ($putcomma);

    $summary="$items_processed items = ".formatBytes($items_size)." / $items_size bytes, $files_processed files processed, $files_downloaded downloaded = ".formatBytes($dl_size)." / $dl_size bytes";
    $desc.="\n\n$summary\n";

    if (file_exists($descfname)) { file_put_contents($descfname, $desc,FILE_APPEND | LOCK_EX); $dl_size+=$details['file_size'];} else echo "collection description $descfname does not exist, skipping!\n";
    echo "\nFinished downloading collection: ".$collection_details['title']." ({$collection_details['publishedfileid']})\n$summary\n";
//} // --- end function: steamWSCollectionDownload


//    $desc="Items      : $items_processed ($items_size), $files_processed files processed, $files_downloaded downloaded ($dl_size bytes)\n";
//    if (file_exists($fn)) { file_put_contents($fn, $desc,FILE_APPEND | LOCK_EX); $dl_size+=$details['file_size'];} else echo "file $fn exists, skipping!\n";
//    echo "Finished downloading collection: ".$collection_details['title']."(".$collection_details['publishedfileid'].")"."; $items_processed items processed ($items_size bytes), $files_processed files processed, $files_downloaded downloaded ($dl_size bytes)\n";


    return 1;
} // --- end function: steamWSCollectionDownload

/*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*//*--------------------------------------------------------------------------*/

// get collection info
$cid = ($argv[1] ? $argv[1] : 198983255);
echo "cid: $cid\n";
/*
$params['format']='json';
$params['publishedfileids']=array($id);
$params['collectioncount']=1;
*/
//print_r($params);
//file_get_contents();
// get collection info


//echo "\nout=";
//print_r( $out );


//echo "\ndetails=";
//print_r( $details );

steamWSCollectionDownload($cid);

echo "debug info: WSItemInfo_calls=$WSItemInfo_calls, steamUserInfo_calls=$steamUserInfo_calls\n";
echo "\nwe done here.\n";
?>
