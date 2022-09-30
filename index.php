#!/usr/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 300);
class addPbooks{


  function __construct(){
    include("config.php");
    $this->apiKey=$apiKey;
    $this->setId=$setId;
    $this->webhook=$webhook;
    $this->postToSlack=true; #set to false if not posting to slack
    #more info here: https://api.slack.com/messaging/webhooks

  }

  function controller(){

    #get initial count to determine whether any action is needed,
    #and if so, how many times to query the Sets API to get all
    # (increments of 100 per response)
    $count=$this->getSetMembers(100,0, true);
    //echo $count;
    if($count==0){
      if($this->postToSlack==true){
        $this->message="No local Summit bibs to update. :pouting_cat:";
        $this->postMessage();
      }
    }

    # array to contain all set member mmsIds from one or more API calls
    $allMembers=array();

    $cycles= ceil($count/100);
    $limit=100;
    $offset=0;
    for($i=1;$i<=$cycles;$i++){
      $offset=($i-1)*100;

      $members=$this->getSetMembers(100,$offset,false);
      $allMembers=array_merge($allMembers, $members);
    }

    $updated=0;
    #loop through all mmsIds, get Bib record, add 972 field, repost to Alma
    foreach($allMembers as $mmsId){
        $bib=$this->getBibRecord($mmsId);
        $updatedXml=$this->add972($bib);
        if($this->updateBib($mmsId, $updatedXml)){
          $updated++;
        }
    }

    #post results to Slack, because why not
    if($this->postToSlack==true){
      $emojis=array(":peach:", ":kiwifruit:",":melon:",":grapes:",":lemon:",":banana:",":pineapple:",":tangerine:",":pear:",":cherries:",":avocado:");
      $key=array_rand($emojis, 1);
      //echo $key;
      $emoji=$emojis[$key];
      $this->message="$updated Summit brief bib records updated with 972 field set to 'pbooks'. $emoji";
      $this->postMessage();
    }

  }


  function getSetMembers($limit, $offset, $justCount=false){
    # calls the Get Set Members API:
    $apiKey=$this->apiKey;
    $setId=$this->setId;


    $ch = curl_init();
    $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/conf/sets/{set_id}/members';
    $templateParamNames = array('{set_id}');
    $templateParamValues = array(urlencode($setId));
    $url = str_replace($templateParamNames, $templateParamValues, $url);
    $queryParams = '?' . urlencode('limit') . '=' . urlencode($limit) . '&' . urlencode('offset') . '=' . urlencode($offset) . '&' . urlencode('apikey') . '=' . urlencode($this->apiKey).'&format=json';
    curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    curl_close($ch);

    $r=json_decode($response);
    //var_dump($r);

    if($justCount==true){
      return $r->total_record_count;
    }

    else{
      #loop through set members, add to $members array, return
      $members=array();
      $member=$r->member;
      foreach($member as $m){
        $mmsId=$m->id;
        $members[]=$mmsId;
      }

      return $members;
    }

  }

  function getBibRecord($mmsId){

    $apiKey=$this->apiKey;

    $ch = curl_init();
    $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/bibs/{mms_id}';
    $templateParamNames = array('{mms_id}');
    $templateParamValues = array(urlencode($mmsId));
    $url = str_replace($templateParamNames, $templateParamValues, $url);
    $queryParams = '?' . urlencode('view') . '=' . urlencode('full') . '&' . urlencode('expand') . '=' . urlencode('None') . '&' . urlencode('apikey') . '=' . urlencode($this->apiKey).'&format=xml';
    curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    curl_close($ch);

    $bib = new SimpleXMLElement($response);
    return $bib;



  }

  function add972($xml){

    /* Need to add this to bib->record
    <datafield ind1=" " ind2=" " tag="972">
      <subfield code="a">pbooks</subfield>
      <subfield code="9">local</subfield>
    </datafield>
    */

    $nineSevenTwo=$xml->record->addChild("datafield");
    $nineSevenTwo->addAttribute("ind1", " ");
    $nineSevenTwo->addAttribute("ind2", " ");
    $nineSevenTwo->addAttribute("tag", "972");
    //echo $xml->asXML();
    $firstSubfield=$nineSevenTwo->addChild("subfield", "pbooks");
    $firstSubfield->addAttribute("code", "a");
    $secondSubfield=$nineSevenTwo->addChild("subfield", "local");
    $secondSubfield->addAttribute("code", "9");
    //var_dump($xml);
    //echo $xml->asXML();
    return $xml;

  }

  function updateBib($mmsId, $xml){

    # object to string
    $xml=$xml->asXML();

    $ch = curl_init();
    $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/bibs/{mms_id}';
    $templateParamNames = array('{mms_id}');
    $templateParamValues = array(urlencode($mmsId));
    $url = str_replace($templateParamNames, $templateParamValues, $url);
    $queryParams = '?' . urlencode('validate') . '=' . urlencode('false') . '&' . urlencode('override_warning') . '=' . urlencode('true') . '&' . urlencode('apikey') . '=' . urlencode($this->apiKey).'&format=xml';
    curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
    $response = curl_exec($ch);
    curl_close($ch);

    $bib = new SimpleXMLElement($response);
    if($bib->mms_id=="$mmsId"){
      return true;
    }

  }

#Post to Slack
  function postMessage(){
    $webhook=$this->webhook;
    $message=$this->message;

        $data = "payload=" . json_encode(array(
             "channel"       =>  "#reports",
             "text"          =>  $message
         ));
    // You can get your webhook endpoint from your Slack settings
     $ch = curl_init($webhook);
     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
     curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     $result = curl_exec($ch);
     curl_close($ch);
     //var_dump($result);
 }


}

$addPbooks=new addPbooks();
$addPbooks->controller();


 ?>
