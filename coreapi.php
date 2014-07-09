<?php
/**
  Core Catalyst API
  
  Author: Darren James Harkness (darren@athabascau.ca / darren@staticred.com)
    
  The Core Catalyst API can be used by any PHP application. This API helps you 
  to upload video to, and get video information from, the Core Catalyst 
  video streaming system.  At the moment, this API is quite limited; however, 
  I expect its functionality will grow over time. 

  License

  The code in this library is licensed under Creative Commons Attribution-
  Sharealike 3.0 Unported (CC-BY-SA 3.0).
  
  http://creativecommons.org/licenses/by-sa/3.0/
  
  You are free to share and remix this code, and make commercial use of it, 
  provided you attribute the work to the original author, and redistribute
  your changes under the same or similar license. 
  
  Requirements
  
  The following is required to use this library
  
    PHP 5.x
    libcurl 
    SEM Labs' PHP cURL class <http://semlabs.co.uk/journal/object-oriented-curl-class-with-multi-threading>
  
  This library ships with a working sample configuration but overall usage 
  is as follows:
  
  PLEASE NOTE: Given how the Core Catalyst API works, you must include 'index' 
  at the end of the apiurl attribute.  If you do not, Curl will only see the 
  redirect.
  
  View the README file for examples of how to use this PHP class with the Core
  Catalyst Video Streaming API. 

*/

  // instead of rolling our own curl, we're making use of SEM Labs' Curl
  // library for PHP.  You might argue that this is a little much, since we can
  // use the built-in Perl functions, but it gives us some room to grow in the 
  // future. 
  require_once("CURL.php");

class coreapi {

  // added in a version attribute so we can do a quick check. Probably mostly 
  // useless, but it makes me feel better. :)
  public $version = "1.0";

  // auth - this can be configured within the class, or as shown in the above
  // example, set as part of the application using the class. It's preferred 
  // that you set it within the application to add a level of security to the
  // API. 
  public $apiuser = "user";
  public $apipwd = "password";
  // IMPORTANT: Include the index at the end of the API URL.  This is necessary
  // for Curl to return XML data from the API. 
  public $apiurl = "http://teacherstv.ca/api/video/index"; 
  public $CDN = "http://wpc.64a2.edgecastcdn.net";
  public $CDNurl = "/0064A2/cds/screens/";
  
  // the $apigroup variable will prepend an identifier to the video title. This
  // is to help larger institutions perform some basic accounting for video
  // streaming storage and use. If this field is blank, it will not be added to
  // the title. 
  public $apigroup;
  
  // AU has a requirement to back up to a NAS. This could be used for any mounted
  // location, however. Change this to true to activate this process and configure 
  // the $backup_location variable. 
  public $backup_video = false;
  
  // the path to the NAS mount on the local system. 
  public $backup_location; 
  
  // report errors?
  public $log_errors = false;
  public $display_errors = false; 
  
  // sorting criteria.  At the moment, only two sorting criteria are implemented:
  //   * alpha - sorts alphabetically
  //   * index - sorts by array key
  public $sortcriteria = 'index';
  
  // video details - this all comes back from the Core Catalyst API, so there's
  // no need to change any of these or set default values (with the exception of
  // the video_id)
  public $video_id;
  public $video_id_list;
  public $video_list_details;
  public $video_url;
  public $video_category; // not yet implemented in the API, but coming soon.
  public $published_date;
  public $status;
  public $video_status;
  public $download;
  public $embed;
  public $uploaded_by;
  public $access_key;
  public $title;
  public $screenshot;
  public $description;
  public $created;
  public $modified;
  public $asset_type;
  public $uploaded_by_username;
  public $embed_width = 480;
  public $embed_height = 320;
  public $embed_type = "flash";
  public $embed_controls = true;
  public $embed_autoplay;
  public $embed_preload;
  
  // category variables
  public $category;
  public $category_id;
  public $category_list;
  public $video_list;
  
  // holding spot for API error messages.
  public $error;

  // used for the uploaded file.   
  public $video_file;
  
  // not yet implemented:
  public $apikey;
  public $apisecret;


  // curl variables
  
  // timeout, in seconds, for curl connection
  public $curl_batchsize = 10 ;
  public $curl_connect_timeout = 120;
  public $curl_timeout = 120;
  public $curl_info;
  
  // private attributes used within the system. Do not change/edit.
  private $ch;
  private $method;

  // plain english translation of the video status codes
  public $video_status_code = array(
    -1 => "Deleted",
    0 => "New",
    1 => "Downloading to transcoding system",
    2 => "Downloaded to transcoding system",
    3 => "Encoding video file",
    4 => "Uploading to Content Distribution Network",
    5 => "Encoding Complete and ready for embedding",
    9 => "Error during transcoding or uploading process."
    );
    
  public $status_code = array(
    -1 => "Deleted",
    1 => "Available",
    2 => "Submitted"
    );
    
  public $allowed_tags = "<b><i><em><strong><p><u><ul><ol><li>";
  
  
  /**
    * Tests the connection, returns error value if no connection.
    *
    * Usage:
    *
    * // include the PHP library
    * include_once("libraries/coreapi/coreapi.php");
    * 
    * // instantiate the class
    * $video = new coreapi();
    * 
    * // configure the connection
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * 
    * if (!$video->test_connection()) {
    *   print("No connection available.");
    * }
    *
    *
    * @param none
    * @return mixed - returns true if connection successful, otherwise returns http result as int
    */
  public function test_connection() {

    $this->get_categories();    
    if ($this->curl_info[0]['http_code'] === 200) {
      return true;
    } else {
      return $this->curl_info[0]['http_code'];
    }
  
  }
  
  /**
    * retrieves information about a specific video from the Core Catalyst API.
    * You must set $this->video_id before calling this function.  It will 
    * set the video attributes based on the information received from the API.
    * 
    * Usage:
    *
    * // include the PHP library
    * include_once("libraries/coreapi/coreapi.php");
    * 
    * // instantiate the class
    * $video = new coreapi();
    * 
    * // configure the connection
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * 
    * // specify the video's ID (this can be found in the Core Catalyst administration portal)
    * $video->video_id = "762";
    * 
    * // call the method
    * $video->get_info();
    * 
    * @param none
    * @return bool - returns true/false based on success of the API query. 
    */
  public function get_info() {
  
    // we're not getting anywhere without a video ID. 
    if (!isset($this->video_id)) {
      $this->tattle("No Video ID supplied for coreapi::getInfo()");
      return false;
    } else {
      
      // build the API call and pass it to callAPI();
      $apicall = "?method=getStatus&id=" . $this->video_id;
      
      if (!$xml = $this->callAPI($apicall, $this->apiurl)) {
        $this->tattle("coreapi::get_info - No data returned from API with the following call: $apicall");
        return false;
      }

      // did we get an error?  Let's check and bail out if we did. 
      if (isset($xml->getStatus->error) && $xml->getStatus->error <> "") {
        // and let's log this, so we can figure out what happened. 
        $this->tattle("An error occurred when querying the API: " . $xml->getStatus->error);
        return false;
      } else {
        // yay valid search results! 
        $this->title = (string)$xml->getStatus->result->title;
        $this->video_url = (string)$xml->getStatus->result->video_url;
        $this->published_date = (string)$xml->getStatus->result->published_date;
        $this->status = (string)$xml->getStatus->result->status;
        $this->video_status = (string)$xml->getStatus->result->video_status;
        $this->download = (string)$xml->getStatus->result->download;
        $this->embed = (string)$xml->getStatus->result->embed;
        $this->get_screenshot();
        $this->description = (string)$xml->getStatus->result->description;
        $this->access_key = (string)$xml->getStatus->result->access_key;
        $this->uploaded_by = (string)$xml->getStatus->result->uploaded_by;
        $this->uploaded_by_username = (string)$xml->getStatus->result->uploaded_by_username;
        $this->created = (string)$xml->getStatus->result->created;
        $this->modified = (string)$xml->getStatus->result->modified;
        $this->asset_type = (string)$xml->getStatus->result->asset_type;
        return true;
      } // end check for errors;     
    } // end check for video id
  } // end coreapi::get_info();

  /**
    * retrieves information for a list of video IDs from the Core Catalyst API.
    * You must set $this->video_id_list before calling this function.  It will 
    * set the video attributes based on the information received from the API.
    *
    * Values will be returned in $this->video_list_details
    * 
    * Usage:
    *
    * // include the PHP library
    * include_once("libraries/coreapi/coreapi.php");
    * 
    * // instantiate the class
    * $video = new coreapi();
    * 
    * // configure the connection
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * 
    * // specify a list of video IDs (this can be found in the Core Catalyst administration portal)
    * $video->video_id_list = array(
    *     '732',
    *     '733',
    *     '734',
    *     '735',
    *     '731',
    *     '730',
    *     '722',
    *     '723',
    *     '752',
    *     '753',
    *     '754',
    *     '755',
    *     '751',
    *     '750',
    *     '762',
    *     '763',
    *     '764',
    *     '765',
    *     '761',
    *     '760',
    *     );
    * 
    * // call the method
    * $video->get_info_multi();
    * 
    * @param none
    * @return bool - returns true/false based on success of the API query. 
    */
  public function get_info_multi() {
    
    
    // we're not getting anywhere without a video ID. 
    if (!isset($this->video_id_list) or !is_array($this->video_id_list)) {
      $this->tattle("No Video ID list supplied for coreapi::getInfo() or video list not supplied as an array");
      return false;
    } else {
      
      foreach ($this->video_id_list as $videoid) {
          // build the API call and pass it to callAPI();
          $apicall[] = "?method=getStatus&id=" . $videoid;
      }
      
      // call 
      
      if (!$xml = $this->callAPIMulti($apicall, $this->apiurl)) {      // BREAKPOINT
         $this->tattle("coreapi::get_info - No data returned from API with the following call: $apicall");
        return false;
      }

      // did we get an error?  Let's check and bail out if we did. 
      if (isset($xml->getStatus->error) && $xml->getStatus->error <> "") {
        // and let's log this, so we can figure out what happened. 
        $this->tattle("An error occurred when querying the API: " . $xml->getStatus->error);
        return false;
      } else {



        $this->video_list_details = array();
        foreach ($xml as $item) {
          // if a valid video is returned in the list, process it. 
          if (!isset($item->getStatus->error)) {
            $vid = (string)$item->getStatus->result->video_id;  
            // yay valid search results! 
            $this->video_list_details["{$vid}"]->video_id = $vid;
            $this->video_list_details["{$vid}"]->title = (string)$item->getStatus->result->title;
            $this->video_list_details["{$vid}"]->video_url = (string)$item->getStatus->result->video_url;
            $this->video_list_details["{$vid}"]->published_date = (string)$item->getStatus->result->published_date;
            $this->video_list_details["{$vid}"]->status = (string)$item->getStatus->result->status;
            $this->video_list_details["{$vid}"]->video_status = (string)$item->getStatus->result->video_status;
            $this->video_list_details["{$vid}"]->download = (string)$item->getStatus->result->download;
            $this->video_list_details["{$vid}"]->embed = (string)$item->getStatus->result->embed;
            $this->video_list_details["{$vid}"]->description = (string)$item->getStatus->result->description;
            $this->video_list_details["{$vid}"]->access_key = (string)$item->getStatus->result->access_key;
            $this->video_list_details["{$vid}"]->uploaded_by = (string)$item->getStatus->result->uploaded_by;
            $this->video_list_details["{$vid}"]->uploaded_by_username = (string)$item->getStatus->result->uploaded_by_username;
            $this->video_list_details["{$vid}"]->created = (string)$item->getStatus->result->created;
            $this->video_list_details["{$vid}"]->modified = (string)$item->getStatus->result->modified;
            $this->video_list_details["{$vid}"]->asset_type = (string)$item->getStatus->result->asset_type;          
            $this->get_screenshot($item);
          } else {
            $this->tattle("An error occurred when querying the API: " . $xml->getStatus->error);
            return false;
          }
        }
        
      return true;
      } // end check for errors;     
    } // end check for video id
  } // end coreapi::get_info_multi();


  /**
    * This function queries the API to see what the video status is on the Core 
    * Catalyst system. 
    * 
    * The following values are returned:
    *
    *   -1 (Deleted)
    *   0 (New)
    *   1 (Downloading)
    *   2 (Downloaded)
    *   3 (Encoding)
    *   4 (Uploading to CDN)
    *   5 (Complete)
    *   9 (Error)     
    *
    * You can use this in concert with the the $this->video_status_code array to
    * get a plain-language status report. 
    *
    * Usage:
    *
    * $video = new coreapi();
    * $video->video_id = 123;
    * $status = $video->get_video_status();
    * print($video->video_status_code[$status]);
    *
    * @param none
    * @return int
    */
  public function get_video_status() {
      if (!isset($this->video_id) or $this->video_id == "") {
        $this->tattle("No video ID set");
        return false;
      }
      
      if ($this->get_info()) {
        return $this->video_status;
      } else {
        return false;
      }
      
    return false;
  } // end coreapi::get_video_status();


  /**
    * builds URL for the screenshot. 
    *
    * this is a helper function for the get_info() function but can be called 
    * on its own.
    *
    * @param none
    * @return bool
    */
    
  public function get_screenshot($item = NULL) {

    $curl = new CURL();
  
    if (!isset($item) || $item === NULL) {
      if (isset($this->video_id) && $this->video_id <> "") {
        $ssurl = $this->CDN . $this->CDNurl . $this->video_id . ".png";
        if ($this->callAPI($ssurl, null)) {
          $this->screenshot = $ssurl;
          return true;
        } else {
          $this->screenshot = "";
          return false;
        }
      } // end if
    } else {
      $ssurl = $this->CDN . $this->CDNurl . $item[0]->getStatus->result->video_id . ".png";
      
      if ($this->callAPI($ssurl, null)) {
        $this->video_list_details["{$item[0]->getStatus->result->video_id}"]->screenshot = $ssurl;
        return true;
      } else {
        $this->screenshot = "";
        return false;
      }
    }
  
  
  } // end coreapi::get_screenshot


  /**
    * builds and returns an embeddable object for the video.  You must set $this->video_id
    * and call $this->getInfo() before calling this function. You may also set $this->embed_type
    * to switch between flash and mobile embed codes.
    * 
    * Usage: 
    * 
    * // create the $video object
    * $video = new coreapi();
    * 
    * // configure the connection
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * 
    * // set the video ID and embedding options
    * $video->video_id = "762";
    * $video->embed_width = $cfg->videoWidth;
    * $video->embed_height = $cfg->videoWidth / $cfg->aspectRatio;
    * $video->embed_type = 'flash';
    * 
    * // load the video from the API
    * $video->getInfo();
    * 
    * // build and print the embed code. 
    * print($video->embed());
    * 
    * @param none;
    * @return mixed returns string of embed object if successful, false if an error occurs. 
    */
  public function embed() {
    // let's check to see if the video's been retrieved yet. 
    if (isset($this->video_id) && $this->video_id <> "") {
      if (!isset($this->access_key) or $this->access_key == "") {
      // ok, sometimes I'm lazy.  Let's try and get the video's info, even though
      // we sorta require it already. 
        $this->get_info();
        if (!isset($this->access_key) or $this->access_key == "") {
          $this->tattle("No access key returned for video.");
          return false;
        }
      }
      } else {
        $this->tattle("Video id not supplied, or video does not contain access key");
        return false;
      }
    
    
    // what kind of embed code are we using?
    switch ($this->embed_type) {
    
      // returns a flash object.
      case 'flash':
      default:
        $output = '
        <object width="' . $this->embed_width . '" height="' . round($this->embed_height) . '" data="' . $this->get_flash_url() . '" type="application/x-shockwave-flash">
          <param name="allowScriptAccess" value="never">
          <param name="allowNetworking" value="internal">
          <param name="wmode" value="opaque">
          <param name="movie" value="' . $this->get_flash_url() .  '">
          <param name="allowFullScreen" value="true">
          <embed src="' . $this->get_flash_url() . '" type="application/x-shockwave-flash" width="' . $this->embed_width . '" height="' . round($this->embed_height) . '" allowscriptaccess="never" allownetworking="internal">
            <p class="flash_js_notice" style="background: #ffffcc; color: black !important; text-align: center;">
              <a href="http://get.adobe.com/flashplayer/">Flash Player is required to view this file / Flash Player est nécessaire pour afficher ce fichier</a>.
              </p>
        </object>';
                
      break;

      // returns an h.264 video object.  Please see the following URL for
      // current browser support:
      // 
      // http://diveintohtml5.org/video.html
      case 'mobile':
        $output = '
        <video src="' . $this->get_mp4_url() . '" width="' . $this->embed_width . '" height="' . round($this->embed_height) . '"';

        if (isset($this->embed_preload) && $this->embed_preload == true) {
          $output .= " preload";
        }
        
        if (isset($this->embed_controls) && $this->embed_controls == true) {
          $output .= " controls";
        }

        if (isset($this->embed_autoplay) && $this->embed_autoplay == true) {
          $output .= " autoplay";
        }
        $output .= '></video>';
      break;
      
      // returns a hybrid html5 object with Android support and Flash fallback; 
      // this still isn't supported by Firefox. Oh, well. 
      case 'hybrid':
      
        $output = '<video id="movie" width="' . $this->embed_width . '" height="' . $this->embed_height . '" preload controls>
            <source src="' . $this->get_mp4_url() . '" type="video/mp4"/>
            <object width="' . $this->embed_width . '" height="' . $this->embed_height . '" type="application/x-shockwave-flash"
              data="flowplayer-3.2.1.swf"> 
              <param name="movie" value="flowplayer-3.2.1.swf" /> 
              <param name="allowfullscreen" value="true" /> 
              <param name="flashvars" value=\'config={"clip": {"url": "' . $this->get_flash_url() . '", "autoPlay":false, "autoBuffering":true}}\' /> 
            </object>
          </video>
          <script>
            var v = document.getElementById("movie");
            v.onclick = function() {
              if (v.paused) {
                v.play();
              } else {
                v.pause();
              }
            };
          </script>';
      break;
      
    } // end   switch. 
      
    return $output;
    
    } // end self::embed()
    
  /**
    * updates information for an existing video ID.
    *
    * Usage:
    *
    * $video = new coreapi();
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * 
    * $video->video_id = "732";
    * $video->title = "New Video Title.";
    * $video->description = "This is an updated description for the video.";
    * $video->published = 1; // 0 = not published, 1 = published, 2 = site default
    * $video->download = 2; // 0 = not downloadable, 1 = downloadable, 2 = site default
    * $video->embed = 2; // 0 = not embeddable, 1 = embeddable, 2 = site default
    *
    * if ($video->update_info() == true) {
    *   echo "success!";
    * }
    *
    * @param none
    * @return bool
    *
    */
  public function update_info() {
    if (!isset($this->video_id) && $this->video_id <> "") {
      $this->tattle("No Video ID set for update.");
      return false;
    }  
    
    $params['method'] = "update";
    $params['id'] = $this->video_id;
    
    // now set things that need to be set. 
    if (isset($this->title)) {
      $params['title'] = strip_tags($this->title,$this->allowed_tags);
    }
    if (isset($this->description)) {
      $params['description'] = strip_tags($this->description,$this->allowed_tags);
    }
    if (isset($this->published)) {
      $params['published'] = (int)$this->published;
    }
    if (isset($this->embed)) {
      $params['embed'] = (int)$this->embed;
    }
    if (isset($this->download)) {
      $params['download'] = (int)$this->download;
    }
    

      // build the API call and send it to callAPI()
      $apicall = "?" .  http_build_query($params);
      $xml = $this->callAPI($apicall, $this->apiurl);
      
      if ($xml === false) { 
        return false;
      }

      // did we get an error?  Let's check and bail out if we did. 
      if (isset($xml->update->error) && $xml->update->error <> "") {
        // and let's log this, so we can figure out what happened. 
        $this->tattle("An error occurred when querying the API: " . $xml->update->error);
        return false;
      } else {    
        return true;
      }
 
  } // end self::update_info();
  /**
    * Helper function to return the flash URL for a video object.
    * @param none
    * @return mixed returns a string with the URL for the Flash video or false
    * if an error occurs. 
    */
  public function get_flash_url() {

    // let's check that the video object has been loaded already. This function
    // is useless without it.
    if (!isset($this->access_key) && $this->access_key=="") {
      $this->tattle("coreapi object must be populated before calling get_flash_url");
      return false;
    } 

    // we don't want pesky trailing slashes at the end of the URL. 
    $api_url = rtrim($this->apiurl,"/");
    
    // remove the index from the API URL if it's there
    $flash_url = str_replace('/api/video/index','/v/',$api_url) . $this->access_key;
    return $flash_url;
  
  } // end coreapi::get_flash_url();
 
  /**
    * Helper function to return the specific URL to the h.264 MP4 file for a 
    * video.  This is useful in a HTML5 context, where we want to use the 
    * <video> tag.  This is mostly used for mobile devices, such as iOS or
    * Android, that make use of h.264. 
    *
    * At the moment, we have to build the MP4 url programmatically from the 
    * $this->video_url attribute (this is a link to the SIML file). In the
    * future, the API will hopefully supply a direct URL.
    *
    * @param none
    * @return mixed returns a string with the URL for the Flash video or false
    * if an error occurs. 
    */
  public function get_mp4_url() {
    
    // let's check that the video object has been loaded already. This function
    // is useless without it.
    if (!isset($this->video_url) && $this->video_url=="") {
      $this->tattle("coreapi object must be populated before calling get_mp4_url");
      return false;
    } 
        
    // replace video_url RTMP location with HTTP location. 
    $mp4_url = str_replace('rtmp://fms', 'http://wpc', $this->video_url);
    
    return $mp4_url;
    
 } // end coreapi::get_mp4_url();
 
 
/* VIDEO UPLOAD MANAGEMENT */
  
  /**
    * Deletes a video from Core Catalyst. Please note that deletion is permanent
    * and unrecoverable. Use with caution.
    *
    * Usage:
    *
    * $video = new coreapi();
    * $video->video_id = 123;
    * $video->delete_video();
    *
    * @param none
    * @return bool
    */
  public function delete_video() {
    if (!isset($this->video_id) or $this->video_id == "") {
      $this->tattle("No video id supplied for deletion.");
      return false;
    }

    // build the API Call and send it to callAPI()
    $apicall = "?method=delete&id=" . $this->video_id;
    $xml = $this->callAPI($apicall, $this->apiurl);
    
    if ($xml === false) {
      $this->tattle("coreapi::delete_video - No data returned from API");
      return false;
    }
    
    if ($xml->delete->status == "success") {
      return true;
    } else {
      return false;
    }
  
  }
  
  /**
    * Uploads a video file to the Core Catalyst system. For this, we assume the 
    * file already exists on the server, and that your application has handled
    * the actual upload from the end client to the server. 
    * 
    * If successful, the function will update the coreapi object with metadata
    * and the video ID and return true.
    *
    * FAIR WARNING. Uploading takes time.  The larger the video file, the
    * longer it will take. You may have to experiment with your timeouts
    * for longer videos.  
    *
    * I've tested this class with video files up to 107M with no ill effect but
    * your experience may differ based on your server configuration and 
    * available bandidth. 
    *
    * Usage:
    * 
    * // create the $video object
    * $video = new coreapi();
    * 
    * // configure the connection
    * $video->apiuser = $cfg->coreapi_user;
    * $video->apipwd = $cfg->coreapi_pwd;
    * $video->apiurl = $cfg->coreapi_url;
    * $video->apigroup = $cfg->coreapi_group;
    * 
    * ..code to handle file upload
    *
    * $video->video_file = $filename;
    * $video->upload_to_core();
    *
    * It would be really nice to be able to fork off this process and be able 
    * to poll it... but that's beyond my meagre ability. 
    *
    * @param none
    * @return bool
    */
  public function upload_to_core() {
    
    // check for required fields before upload.  We require, at a minimum, the
    // video file. 
    if (!isset($this->video_file) or !file_exists($this->video_file)) {
      $this->tattle("Video file not set before upload or does not exist on server.");
      return false;
    }
    if (!isset($this->title) or $this->title == "") {
      $this->title = basename($this->video_file);
    } 
    if (!isset($this->description)) {
      $this->description = "No description supplied.";
    }
    
    // get the MIME type for the uploaded file.  Not yet used, but could be 
    // useful for future along with a check for video/ in the mimetype. 
    if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileinfo = finfo_file($finfo, $this->video_file);
    }
    
    
    // are we prepending with an identifier? If so, let's do it now.
    if (isset($this->apigroup) && $this->apigroup <> "") {
      $this->title = $this->apigroup . "_" . $this->title;
    }
    
    // we don't use the callAPI() function here, because we need specific CURLOPT settings. 
    
    $curl = new CURL();
    $post = array(
      'title' => strip_tags($this->title,$this->allowed_tags),
      'description' => strip_tags($this->description,$this->allowed_tags),
      'upload' => "@" . $this->video_file
      );

    $opts = array(
      CURLOPT_USERPWD => $this->apiuser . ":" . $this->apipwd,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_FOLLOWLOCATION => 1,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_CONNECTTIMEOUT => $this->curl_connect_timeout,
      CURLOPT_TIMEOUT => $this->curl_timeout,
      );
  
    $curl->addSession($this->apiurl . "?method=upload",$opts);
    
    $response = $curl->exec();
    
    // get information about the connection
    $info = $curl->info();
    
    // clear the resource
    $curl->clear();      
    
    if ($info[0]['http_code'] == 0) {
      $this->tattle("Could not connect to CoreAPI");
      return false;
    }
                
    $xml = simplexml_load_string($response);
      // check to see if there was an error while uploading.
      if (isset($xml->upload->result->video_id) && $xml->upload->result->video_id <> "") {
        if (isset($xml->upload->error) && $xml->upload->error <> "") {
          // and let's log this, so we can figure out what happened. 
          $this->tattle("An error occurred when uploading the video: " . $xml->upload->error);
          return false;
        } else {      
          
          // success! Let's set the object's attributes with the data we get back.
          
          $this->video_id = (int)$xml->upload->result->video_id;
          $this->access_key = (string)$xml->upload->result->access_key;
          $this->created = (string)$xml->upload->result->created;
          $this->published_date = (string)$xml->upload->result->published_date;
          $this->status = (string)$xml->upload->result->status;
          $this->published = (string)$xml->upload->result->published;
          $this->embed = (string)$xml->upload->result->embed;
          $this->download = (string)$xml->upload->result->download;
          $this->description = (string)$xml->upload->result->description;
          $this->uploaded_by = (string)$xml->upload->result->uploaded_by;
          $this->uploaded_by_username = (string)$xml->upload->result->uploaded_by_username;
        }
      } else {
        $this->tattle("A problem occurred when attempting to upload the video.");
        return false;
      }

    // ok, we've uploaded the file; now let's deal with the backup.      
    if ($this->backup_video == true)   {
      $this->backup_video(); 
    }
    
    return true;
  } // end coreapi::upload()


/* CATEGORY CALLS */  


  /**
    * Get a list of categories from the API, and set $this->category_list with 
    * the returned list of categories. 
    * 
    * The list can be sorted by settings $this->sortcriteria
    * 
    * The array will look something like this (where the array key is equal to 
    * the category ID in the API):
    *
    *   array(5) {
    *     [24]=>
    *     string(7) "Science"
    *     [25]=>
    *     string(5) "Music"
    *     [26]=>
    *     string(7) "Nursing"
    *     [27]=>
    *     string(10) "Psychology"
    *     [28]=>
    *     string(10) "Physics204"
    *   }
    * @param none
    * @return bool  
    */
    
  public function get_categories() {
      // Let's use cURL like grownups do!
      $curl = new CURL();
      
      // build the API call and send it to callAPI();
      $apicall = "?method=getCategories";
        
      if (!$xml = $this->callAPI($apicall, $this->apiurl)) {
        $this->tattle("coreapi::get_categories - No data returned from API");
        return false;
      }

      if (isset($xml->getCategories->result) && $xml->getCategories->count > 0) {
        // We need to convert the XML object to a keyed array we can work with.
        foreach ((array)$xml->getCategories->result as $key=>$val) {
          $categories["{$val->category_id}"] = (string)$val->name;
        }
        
        // save the array to $this->category_list;
        $this->category_list = $categories;
        if (isset($this->sortcriteria) && $this->sortcriteria == "alpha") {
          asort($this->category_list);
        }
        if (isset($this->sortcriteria) && $this->sortcriteria == "index") {
          ksort($this->category_list);
        }
        
        return true; 
        
      } else {
        $this->tattle("No categories were returned.");
        return false;
      }
  } // end coreapi::get_categories();
  
  
  /**
    * Returns list of video IDs within a category.  This sets the $this->video_list 
    * attribute with an array of videos keyed to their IDs in the API. This list 
    * can be sorted based on one of two sort criteria: index or alpha (default).
    *
    * An example
    * of returned data looks like the following:
    *
    *   array(3) {
    *     [349]=>
    *     string(19) "ANTH390_video_1.mp4"
    *     [366]=>
    *     string(19) "ANTH390_video_2.mp4"
    *     [367]=>
    *     string(19) "ANTH390_video_3.mp4"
    *   }    
    *
    * @param string $sort Sort criteria.  Valid values are index or alpha. 
    * @return mixed Returns object containing a list of videos; false if no 
    * categories exist
    */
  public function get_category_videos() {

  
    // check that a category ID has been specified, and bail if it hasn't
    if (!isset($this->category_id) or $this->category_id == "") {
      $this->tattle("No category ID identified. Cannot display list of videos.");
      return false;
    } 
      // build the API call and send it to callAPI()
      $apicall = "?method=getCategoryVideoIds&categoryId=" . $this->category_id;
          
      if (!$xml = $this->callAPI($apicall, $this->apiurl)) {
        $this->tattle("coreapi::get_category_videos - No data returned from API: $apicall");
        return false;
      }
             
      // let's see if we get anything back!
      if (isset($xml->getCategoryVideoIds->result) && $xml->getCategoryVideoIds->count > 0) {
        // We need to convert the XML object to a keyed array we can work with.

        
        foreach ((array)$xml->getCategoryVideoIds->result as $key=>$val) {
          // BUGFIX: the API reports back deleted videos in this list, so let's
          // make sure we can actually use the video files returned. This can
          // report false errors, so let's turn error display/reporting off 
          // temporarily if it's on.
  
          if ($this->display_errors == true) {
            $display_errors = $this->display_errors;
            $this->display_errors = false;
          } 
          if ($this->log_errors == true) {
            $log_errors = $this->log_errors;
            $this->log_errors == false; 
          }
  
          // the title isn't returned as part of the getCategoryVideoIds method, 
          // so let's go grab them to make things easier to work with.

          $this->video_id_list[] = (int)$val->video_id;
          
          
          // BUGFIX: Now set it back.
          if (isset($display_errors)) {
            $this->display_errors = $display_errors;
          }
          if (isset($log_errors)) {
            $this->log_errors = $log_errors;
          }
        }

        $this->get_info_multi();
                
        $videos = array();
        if (is_array($this->video_list_details)) {
          foreach ($this->video_list_details as $val) {
              $videos[(int)"{$val->video_id}"] = (string)$this->video_list_details["{$val->video_id}"]->title;
          }
        } else {
            $videos[(int)"{$video_list_details}"] = (string)$this->video_list_details->title;
        }
        
        // save the array to $this->video_list;
        $this->video_list = $videos;
        if (isset($this->sortcriteria) && $this->sortcriteria == "alpha") {
          asort($this->video_list);
        }
        if (isset($this->sortcriteria) && $this->sortcriteria == "index") {
          ksort($this->video_list);
        }

        return true; 
        
      } else {
        $this->tattle("No videos were returned.");
        return false;
      }
  
  } // end coreapi::get_category_videos();

  /**
    * Returns list of video IDs within a category.  This sets the $this->video_list 
    * attribute with an array of videos keyed to their IDs in the API. This list 
    * can be sorted based on one of two sort criteria: index or alpha (default).
    *
    * An example
    * of returned data looks like the following:
    *
    *   array(3) {
    *     []=>
    *     int(3) 349
    *     []=>
    *     int(3) 366
    *     []=>
    *     int(3) 367
    *   }    
    *
    * @param string $sort Sort criteria.  Valid values are index or alpha. 
    * @return mixed Returns object containing a list of video ids; false if no 
    * categories exist
    */
  public function get_category_video_ids() {

  
    // check that a category ID has been specified, and bail if it hasn't
    if (!isset($this->category_id) or $this->category_id == "") {
      $this->tattle("No category ID identified. Cannot display list of videos.");
      return false;
    } 
      // build the API call and send it to callAPI()
      $apicall = "?method=getCategoryVideoIds&categoryId=" . $this->category_id;
          
      if (!$xml = $this->callAPI($apicall, $this->apiurl)) {
        $this->tattle("coreapi::get_category_videos - No data returned from API: $apicall");
        return false;
      }
                  
      // let's see if we get anything back!
      if (isset($xml->getCategoryVideoIds->result) && $xml->getCategoryVideoIds->count > 0) {
        // We need to convert the XML object to a keyed array we can work with.

        
        foreach ((array)$xml->getCategoryVideoIds->result as $key=>$val) {

          // BUGFIX: the API reports back deleted videos in this list, so let's
          // make sure we can actually use the video files returned. This can
          // report false errors, so let's turn error display/reporting off 
          // temporarily if it's on.
  
          if ($this->display_errors == true) {
            $display_errors = $this->display_errors;
            $this->display_errors = false;
          } 
          if ($this->log_errors == true) {
            $log_errors = $this->log_errors;
            $this->log_errors == false; 
          }
  
          // the title isn't returned as part of the getCategoryVideoIds method, 
          // so let's go grab them to make things easier to work with.

          $this->video_id_list[] = (int)$val->video_id;
          
          // BUGFIX: Now set it back.
          if (isset($display_errors)) {
            $this->display_errors = $display_errors;
          }
          if (isset($log_errors)) {
            $this->log_errors = $log_errors;
          }
        }
        
        return true; 
        
      } else {
        $this->tattle("No videos were returned.");
        return false;
      }
  
  } // end coreapi::get_category_video_ids();


  /**
    * Gets a list of category names and IDs for a specific video file
    * This sets the $this->category_list attribute attribute with an array of 
    * videos keyed to their IDs in the API. This list can be sorted based on
    * the value of $this->sortcriteria. 
    *    
    * An example of returned data looks like the following:
    *
    *   array(3) {
    *     [36]=>
    *     string(19) "Canadian Film Site"
    *     [33]=>
    *     string(19) "e-lab general"
    *   }    
    
    * @param none 
    * @return bool
    */
  public function get_categories_by_videoid() {
    
    // check quickly for a video ID. We can't do much without it. 
    if (!isset($this->videoid)) {
      tattle("No Video ID supplied.  Cannot get list of categories.");
      return false;
    }

    $this->get_categories();
    
    // build the API call and send it to callAPI()
    $apicall = "?method=getVideoCategoryIds&videoId=" . $this->videoid;
    $xml = $this->callAPI($apicall, $this->apiurl);
    
    if ($xml === false) {
      $this->tattle("coreapi::get_categories_by_videoid - No data returned from API");
      return false;
    }

    // let's see if we get anything back!
    if (isset($xml->getVideoCategoryIds->result) && $xml->getVideoCategoryIds->count > 0) {
      // We need to convert the XML object to a keyed array we can work with.
      foreach ((array)$xml->getVideoCategoryIds->result as $key=>$val) {

        // BUGFIX: the API reports back deleted videos in this list, so let's
        // make sure we can actually use the video files returned. This can
        // report false errors, so let's turn error display/reporting off 
        // temporarily if it's on.

        if ($this->display_errors == true) {
          $display_errors = $this->display_errors;
          $this->display_errors = false;
        } 
        if ($this->log_errors == true) {
          $log_errors = $this->log_errors;
          $this->log_errors == false; 
        }

        // the title isn't returned as part of the getVideoCategoryIds method, 
        // so let's go grab them to make things easier to work with.

          $cats["{$val->category_id}"] = (string)$this->category_list["{$val->category_id}"];
        
        // BUGFIX: Now set it back.
        if (isset($display_errors)) {
          $this->display_errors = $display_errors;
        }
        if (isset($log_errors)) {
          $this->log_errors = $log_errors;
        }
      }
      
      // save the array to $this->category_list;
      $this->category_list = $cats;
      if (isset($this->sortcriteria) && $this->sortcriteria == "alpha") {
        asort($this->category_list);
      }
      if (isset($this->sortcriteria) && $this->sortcriteria == "index") {
        ksort($this->video_list);
      }
      return true; 
      
    } else {
      $this->tattle("No videos were returned.");
      return false;
    }  
  
  } // end self::get_categories_by_videoid()

  
  /**
    * Adds a video to a specific category
    * @param none
    * @return bool
    */
  public function set_category() {
    // we need two things set ahead of time, the video ID and the category ID
    if (!isset($this->category_id) or $this->category_id == "") {
      $this->tattle("The category ID was not set.  Cannot set the video's category.");
      return false;
    }
    if (!isset($this->video_id) or $this->video_id == "") {
      $this->tattle("The video ID was not set. Cannot assign a video to the category");
      return false;
    }
    
    // build the API call and send it to callAPI()
    $apicall = "?method=addVideoToCategory&videoId=" . $this->video_id . "&categoryId=" . $this->category_id;
    $xml = $this->callAPI($apicall, $this->apiurl);

    if ($xml === false) {
      $this->tattle("coreapi::set_category - No data returned from API");
      return false;
    }
  
    if ($xml->addVideoToCategory->status == "success") {
      return true;
    } else {
      $this->tattle("Video ID {$this->video_id} was not assigned to category ID {$this->category_id}");
      return false;
    }
  
  
  } // end coreapi::set_category();
  
  
  /**
    * Removes a video from a specified category
    * @param none
    * @return bool
    */
    
  public function unset_category() {
    // we need two things set ahead of time, the video ID and the category ID
    if (!isset($this->category_id) or $this->category_id == "") {
      $this->tattle("The category ID was not set.  Cannot set the video's category.");
      return false;
    }
    if (!isset($this->video_id) or $this->video_id == "") {
      $this->tattle("The video ID was not set. Cannot assign a video to the category");
      return false;
    }
    
    // build the API call and send it to callAPI()
    $apicall = "?method=removeVideoFromCategory&videoId=" . $this->video_id . "&categoryId=" . $this->category_id;
    $xml = $this->callAPI($apicall, $this->apiurl);
    
    if ($xml === false) {
      $this->tattle("coreapi::unset_category - No data returned from API");
      return false;
    }
  
    if ($xml->removeVideoFromCategory->status == "success") {
      return true;
    } else {
      $this->tattle("Video ID {$this->video_id} was not assigned to category ID {$this->category_id}");
      return false;
    }
  
  
  } // end coreapi::unset_category();
  

/* // HELPER FUNCTIONS // */

  
  /**
    * Uploads a video to a specified NAS backup location. This is a private
    * function used by coreapi::upload() 
    * @param none
    * @return bool
    */
  private function backup_video() {
    
    // does the file exist in the first place?
    if (!file_exists($this->video_file)) {
      $this->tattle("Source file does not exist, so could not perform backup");
      return false;
    }
    
    // let's check that the location variable's set, and that it is valid.
    if (!isset($this->backup_location) or $this->backup_location == "") {
      $this->tattle("Backup location is not set, so could not run backup.");
      return false;
    }
    
    // does it exist?
    if (!file_exists($this->backup_location)) {
      $this->tattle($this->backup_location . " does not exist on the server. Cannot backup to this location.");
      return false;
    }
    
    // ok, it's exists. Somewhere, Heidegger is happy. Now... can we write 
    // to it?
    if (!is_writable($this->backup_location)) {
      $this->tattle($this->backup_location . " is not writeable. Cannot backup to this location.");
      return false;
    }
    
    // need to strip off the source directory so we can pop it into the destination.
    $filename = basename($this->video_file);
    
        // check - do we have a group identifier?  If so, we need to prepend it 
    // to the destination filename
    if (isset($this->apigroup) && $this->apigroup <> "") {
      $filename = $this->apigroup . "_" . $filename;
    } 
    
    $this->backup_location = rtrim($this->backup_location,"/");
    
    // Phew! Made it! Let's move the file now. Note, we don't need the original 
    // copy, since that's already been sent off to the streaming server. We've 
    // got to conserve space, people!    
    if (!rename($this->video_file,$this->backup_location . "/$filename")) {
      // OK, what'd you do?  There's no way you should get here.
      $this->tattle("A problem occurred while backup up the video file.");
    } else {
      return true;
    }
  } // end coreapi::backup_video();


  /**
    * runs multiple cURL sessions, returns data
    *
    * @param $apicall array specific API calls
    * @param $apiurl str the API's base URL
    * @return mixed If data is found, return an XML object. If an error is encountered, return false.   
    */
  
  private function callAPIMulti($apicalls, $apiurl = "") {

    $opts = array(
    // we're behind HTTPAuth, so send credentials
    CURLOPT_USERPWD => $this->apiuser . ":" . $this->apipwd,
    // we want to get the data back
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => $header,
    CURLOPT_POST => false,
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1',
    // if the site goes down, let's make sure we can bail
    CURLOPT_CONNECTTIMEOUT => $this->curl_connect_timeout,
    // the proxy can give a false positive on whether the site is 
    // available or not. let's set a max time for curl to wait for 
    // data to come back. 
    CURLOPT_TIMEOUT => $this->curl_timeout,
    );
  
    $urls = array();
    foreach($apicalls as $apicall) {
      $urls[] = $apiurl . $apicall;
    }
    
    $results = $this->multiRequest($urls, $opts);
    
    foreach($results as $result) {
      $xmlitem = simplexml_load_string($result);
      $xmlmulti["{$xmlitem->getStatus->result->video_id}"] = $xmlitem;
      
    }
    return $xmlmulti;
  }

  /**
    * Helper function for cURL calls
    */  
  private function multiRequest($data, $options = array()) {
   
    // array of curl handles
    $curly = array();
    // data to be returned
    $result = array();
   
    // multi handle
    $mh = curl_multi_init();
   
    // loop through $data and create curl handles
    // then add them to the multi-handle
    foreach ($data as $id => $d) {
   
      $curly[$id] = curl_init();
   
      $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
      curl_setopt($curly[$id], CURLOPT_URL,            $url);
      curl_setopt($curly[$id], CURLOPT_HEADER,         0);
      curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);
   
      // post?
      if (is_array($d)) {
        if (!empty($d['post'])) {
          curl_setopt($curly[$id], CURLOPT_POST,       1);
          curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
        }
      }
   
      // extra options?
      if (!empty($options)) {
        curl_setopt_array($curly[$id], $options);
      }
   
      curl_multi_add_handle($mh, $curly[$id]);
    }
   
    // execute the handles
    $running = null;
    do {
      curl_multi_exec($mh, $running);
    } while($running > 0);
   
   
    // get content and remove handles
    foreach($curly as $id => $c) {
      $result[$id] = curl_multi_getcontent($c);
      curl_multi_remove_handle($mh, $c);
    }
   
    // all done
    curl_multi_close($mh);
   
    return $result;
  }  


  /**
    * runs cURL, returns data
    *
    * @param $apicall str specific API calls
    * @param $apiurl str the API's base URL
    * @return mixed If data is found, return an XML object. If an error is encountered, return false.   
    */
  
  private function callAPI($apicall,$apiurl = "") {
  
    // Let's use cURL like grownups do!
    $curl = new CURL();
    
    // we need to authenticate, and we need to get results back as an object. 
    $opts = array(
      // we're behind HTTPAuth, so send credentials
      CURLOPT_USERPWD => $this->apiuser . ":" . $this->apipwd,
      // we want to get the data back
      CURLOPT_RETURNTRANSFER => true,
      // if the site goes down, let's make sure we can bail
      CURLOPT_CONNECTTIMEOUT => $this->curl_connect_timeout,
      // the proxy can give a false positive on whether the site is 
      // available or not. let's set a max time for curl to wait for 
      // data to come back. 
      CURLOPT_TIMEOUT => $this->curl_timeout,
      );
    $curl->addSession($apiurl . $apicall ,$opts);
    $response = $curl->exec();
    // get information about the connection
    $this->curl_info = $curl->info();
    // clear the resource
    $curl->clear();      
    
    if ($this->curl_info[0]['http_code'] == 0) {
      $this->tattle("Could not connect to CoreAPI: $apicall");
      return false;
    }      
        
    // now, handle the output.  We only really expect two kinds of content: 
    // XML and PNG.  So let's check for those, and handle them appropriately. 
    if ($this->curl_info[0]['content_type'] == 'text/xml;charset=utf-8') {
      $xml = simplexml_load_string(trim($response));
      return $xml;
    } else if ($this->curl_info[0]['content_type'] == 'image/png') {
      // we're really only interested in whether it exists or not
      if ($this->curl_info[0]['http_code'] == "200") {
        return true;
      } else {
        $this->tattle("Error loading screenshot URL: HTTP Error " . $this->curl_info[0]['http_code']);
        return false;
      }
    } else {
      // we got back something we didn't expect. Let's be on the cautious
      // side and return false. 
      $this->tattle("Unexpected data returned: $response");
      return false;
    }
  } // end coreapi::callAPI();


  /**
    * Provides basic error reporting to screen and to the system's error logs.
    * By default, this is configured to log errors to the system log. 
    *
    * The logging is configured through the two following class attributes:
    *    * $this->log_errors (logs errors to the logfiles)
    *    * $this->display_errors (logs errors to the screen)
    * 
    * Usage:
    *   if (!file_exists($this->backup_location)) {
    *     $this->tattle($this->backup_location . " does not exist on the server. Cannot backup to this location.");
    *     return false;
    *   }
    */
  private function tattle($msg) {
    $this->error = $msg;
    if ($this->log_errors == true) {
      error_log("COREAPI: " . $msg,0);
    } // end log errors 
    if ($this->display_errors == true) {
      print("<div style='background-color: #ffffcc; color: red; font-weight: bold;'>
      <p>
        An error was encountered by CoreAPI:
      </p>
      <p>
      $msg
      </p>
      </div>");
    } // end display errors.
  } // end coreapi::tattle();
  
  private function xml_attribute($object, $attribute) {
      if(isset($object[$attribute]))
          return (string) $object[$attribute];
      }

} // end coreapi class

