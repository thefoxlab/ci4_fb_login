<?php

namespace App\Libraries;

require './vendor/autoload.php';

use Facebook\Facebook as FB;
use Facebook\Authentication\AccessToken;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Helpers\FacebookJavaScriptHelper;
use Facebook\Helpers\FacebookRedirectLoginHelper;

class Facebookauth {

    /**
     * @var FB 
     */
    private $fb;

    /**
     * @var FacebookRedirectLoginHelper|FacebookJavaScriptHelper 
     */
    private $helper;
    private $session;

    /**
     * Facebook constructor. 
     */
    public function __construct() {
        // Load session
        $this->session = \Config\Services::session();

        // Load required libraries and helpers 
        if (!isset($this->fb)) {
            $this->fb = new FB([
                'app_id' => FB_APP_ID,
                'app_secret' => FB_APP_SECRET,
                    /* 'default_graph_version' => $this->config->item('facebook_graph_version') */
            ]);
        }

        // Load correct helper depending on login type 
        // set in the config file 
        $this->helper = $this->fb->getRedirectLoginHelper();

        // Try and authenticate the user right away (get valid access token) 
        $this->authenticate();
    }

    /**
     * @return FB 
     */
    public function object() {
        return $this->fb;
    }

    /**
     * Check whether the user is logged in. 
     * by access token 
     * 
     * @return mixed|boolean 
     */
    public function is_authenticated() {
        $access_token = $this->authenticate();
        if (isset($access_token)) {
            return $access_token;
        }
        return false;
    }

    /**
     * Do Graph request 
     * 
     * @param       $method 
     * @param       $endpoint 
     * @param array $params 
     * @param null  $access_token 
     * 
     * @return array 
     */
    public function request($method, $endpoint, $params = [], $access_token = null) {
        try {
            $response = $this->fb->{strtolower($method)}($endpoint, $params, $access_token);
            return $response->getDecodedBody();
        } catch (FacebookResponseException $e) {
            return $this->logError($e->getCode(), $e->getMessage());
        } catch (FacebookSDKException $e) {
            return $this->logError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Generate Facebook login url for web 
     * 
     * @return  string 
     */
    public function login_url() {
        // Get login url 
        return $this->helper->getLoginUrl(
                        base_url('auth/fb_login'),
                        array('email')
        );
    }

    /**
     * Generate Facebook logout url for web 
     * 
     * @return string 
     */
    public function logout_url() {
        // Get logout url 
        return $this->helper->getLogoutUrl(
                        $this->get_access_token(),
                        base_url('auth/logout')
        );
    }

    /**
     * Destroy local Facebook session 
     */
    public function destroy_session() {
        $this->session->remove('fb_access_token');
    }

    /**
     * Get a new access token from Facebook 
     * 
     * @return array|AccessToken|null|object|void 
     */
    private function authenticate() {
        $access_token = $this->get_access_token();

        /* if ($access_token && $this->get_expire_time() > (time() + 30) || $access_token && !$this->get_expire_time()) {
          $this->fb->setDefaultAccessToken($access_token);
          return $access_token;
          } */

        if ($access_token) {
            $this->fb->setDefaultAccessToken($access_token);
            return $access_token;
        }

        // If we did not have a stored access token or if it has expired, try get a new access token 
        if (!$access_token) {
            try {
                $access_token = $this->helper->getAccessToken();
            } catch (FacebookSDKException $e) {
                $this->logError($e->getCode(), $e->getMessage());
                return null;
            }

            // If we got a session we need to exchange it for a long lived session. 
            if (isset($access_token)) {
                $access_token = $this->long_lived_token($access_token);

                //$this->set_expire_time($access_token->getExpiresAt());
                $this->set_access_token($access_token);
                $this->fb->setDefaultAccessToken($access_token);

                return $access_token;
            }
        }

        // Collect errors if any when using web redirect based login 
        if ($this->helper->getError()) {
            // Collect error data 
            $error = array(
                'error' => $this->helper->getError(),
                'error_code' => $this->helper->getErrorCode(),
                'error_reason' => $this->helper->getErrorReason(),
                'error_description' => $this->helper->getErrorDescription()
            );
            return $error;
        }
        return $access_token;
    }

    public function get_user_info() {
        return $this->request('get', '/me?fields=id,first_name,last_name,email,link,gender,picture');
    }

    /**
     * Exchange short lived token for a long lived token 
     * 
     * @param AccessToken $access_token 
     * 
     * @return AccessToken|null 
     */
    private function long_lived_token(AccessToken $access_token) {
        if (!$access_token->isLongLived()) {
            $oauth2_client = $this->fb->getOAuth2Client();

            try {
                return $oauth2_client->getLongLivedAccessToken($access_token);
            } catch (FacebookSDKException $e) {
                $this->logError($e->getCode(), $e->getMessage());
                return null;
            }
        }
        return $access_token;
    }

    /**
     * Get stored access token 
     * 
     * @return mixed 
     */
    private function get_access_token() {
        return $this->session->get('fb_access_token');
    }

    /**
     * Store access token 
     * 
     * @param AccessToken $access_token 
     */
    private function set_access_token(AccessToken $access_token) {
        $this->session->set('fb_access_token', $access_token->getValue());
    }

    /**
     * @return mixed 
     */
    private function get_expire_time() {
        return $this->session->get('fb_expire');
    }

    /**
     * @param DateTime $time 
     */
    private function set_expire_time(DateTime $time = null) {
        if ($time) {
            $this->session->set('fb_expire', $time->getTimestamp());
        }
    }

    /**
     * @param $code 
     * @param $message 
     * 
     * @return array 
     */
    private function logError($code, $message) {
        log_message('error', '[FACEBOOK PHP SDK] code: ' . $code . ' | message: ' . $message);
        return ['error' => $code, 'message' => $message];
    }

    /**
     * Enables the use of CI super-global without having to define an extra variable. 
     * 
     * @param $var 
     * 
     * @return mixed 
     */
    /* public function __get($var) {
      return get_instance()->$var;
      } */
}
