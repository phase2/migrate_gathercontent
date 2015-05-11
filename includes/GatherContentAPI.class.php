<?php
/**
 * @file
 * Contains an interface class for the GatherContent API, documented here:
 * http://help.gathercontent.com/developer-api/
 */


class GatherContentAPI {
  private $api_url;
  private $api_key;
  private $password;
  private $account;

  public $error_code = '';
  public $error_msg = '';

  /**
   * Constructor.
   */
  public function __construct($account, $api_key) {
    $this->api_key = $api_key;
    $this->account = $account;
    $this->api_url = "https://{$account}.gathercontent.com/api/0.4/";
    $this->password = 'x';
  }

  public function get_url() {
    return $this->api_url;
  }

  public function get_me() {
    $resp = $this->_curl('get_me');
    return $this->_decode_response($resp, 'user');
  }

  public function get_users() {
    $retval = FALSE;
    $resp = $this->_curl('get_users');
    return $this->_decode_response($resp, 'users');
  }

  public function get_user($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_user', $args);
    return $this->_decode_response($resp, 'user');
  }

  public function get_my_group() {
    $retval = FALSE;
    $resp = $this->_curl('get_my_group');
    return $this->_decode_response($resp, 'group');
  }

  public function get_groups() {
    $resp = $this->_curl('get_groups');
    return $this->_decode_response($resp, 'groups');
  }

  public function get_group($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_group', $args);
    return $this->_decode_response($resp, 'group');
  }

  public function get_projects() {
    $resp = $this->_curl('get_projects');
    return $this->_decode_response($resp, 'projects');
  }

  public function get_project($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_project', $args);
    return $this->_decode_response($resp, 'project');
  }

  public function get_page($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_page', $args);
    return $this->_decode_response($resp, 'page');
  }

  public function get_pages_by_project($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_pages_by_project', $args);
    return $this->_decode_response($resp, 'pages');
  }

  public function get_file($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_file', $args);
    return $this->_decode_response($resp, 'file');
  }

  public function get_files_by_project($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_files_by_project', $args);
    return $this->_decode_response($resp, 'files');
  }

  public function get_files_by_page($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_files_by_page', $args);
    return $this->_decode_response($resp, 'files');
  }

  public function get_custom_state($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_custom_state', $args);
    return $this->_decode_response($resp, 'custom_state');
  }

  public function get_custom_states_by_project($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_custom_states_by_project', $args);
    return $this->_decode_response($resp, 'custom_states');
  }

  public function get_template($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_template', $args);
    return $this->_decode_response($resp, 'template');
  }

  public function get_templates_by_project($id) {
    $args = array(
      'id' => $id,
    );
    $resp = $this->_curl('get_templates_by_project', $args);
    return $this->_decode_response($resp, 'templates');
  }

  /**
   * Executes command against the GatherContent server.  Base on code from:
   * http://help.gathercontent.com/developer-api/
   */
  private function _curl($command = '', $postfields = array()) {
    // Reset internal error fields.
    $this->error_code = '';
    $this->error_msg = '';

    // Check for cached content, short-circuit if there.
    $cid = $this->_get_cache_key($command, $postfields);
    $cache = cache_get($cid);
    if (FALSE !== $cache) {
      return array('code' => 200, 'data' => $cache->data);
    }

    // Build up query data and init curl.
    $postfields = http_build_query($postfields);
    $session = curl_init();

    curl_setopt($session, CURLOPT_URL, $this->api_url . $command);
    curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($session, CURLOPT_USERPWD, $this->api_key . ":" . $this->password);
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_POSTFIELDS, $postfields);

    if (substr($this->api_url, 0, 8) == 'https://') {
      curl_setopt($session, CURLOPT_SSL_VERIFYPEER, true);
    }

    $response = curl_exec($session);
    $httpcode = curl_getinfo($session, CURLINFO_HTTP_CODE);
    curl_close($session);

    // Store in cache if a good response.
    if (200 == $httpcode) {
      cache_set($cid, $response, 'cache', CACHE_TEMPORARY);
    }

    return array('code' => $httpcode, 'data' => $response);
  }

  /**
   * Generates a unique cache key from the request parameters.
   */
  protected function _get_cache_key($command, $postfields) {
    // Sort args on key and urlencode for uniqueness
    asort($postfields);
    $key = 'gathercontent:' . md5($this->api_url . $command . http_build_query($postfields));
    return $key;
  }

  /**
   * Decodes response data.
   */
  private function _decode_response($resp, $prop) {
    $retval = FALSE;
    if (200 == $resp['code']) {
      $data = json_decode($resp['data']);
      if (!empty($data->{$prop})) {
        $retval = $data->{$prop};

        // Check to see if we need to base64 decode any "config" properties
        if (is_array($retval)) {
          $retval_by_ids = array();
          foreach ($retval as $item) {
            if (property_exists($item, 'config')) {
              // base64 + JSON decode
              $item->config = json_decode(base64_decode($item->config));
            }
            $retval_by_ids[$item->id] = $item;
          }
          $retval = $retval_by_ids;
        }
        elseif (property_exists($retval, 'config')) {
          // base64 + JSON decode
          $retval->config = json_decode(base64_decode($retval->config));
        }
      }
    }
    else {
      // Attempt to unravel some kind of error reporting.
      $this->error_code = $resp['code'];
      if (!empty($resp['data'])) {
        $data = json_decode($resp['data']);
        $this->error_msg = (!empty($data->error)) ? $data->error : 'Unknown error';
      }
    }
    return $retval;
  }
}
