<?php

namespace App\Controllers;

class Auth extends FrontController {

    public function __construct() {
        parent::__construct();
        $db = db_connect();
    }

    /**
     * FB login function
     * @access public
     * @description helper function
     * @author Mahek
     */
    public function fb_login() {
        $facebook = new \App\Libraries\Facebookauth();
        $userData = array();


        if ($facebook->is_authenticated()) {
            // Get user info from facebook 
            $fbUser = $facebook->get_user_info();

            // Preparing data for database insertion 
            $userData['fb_id'] = !empty($fbUser['id']) ? $fbUser['id'] : '';
            $userData['first_name'] = !empty($fbUser['first_name']) ? $fbUser['first_name'] : '';
            $userData['last_name'] = !empty($fbUser['last_name']) ? $fbUser['last_name'] : '';
            $userData['email'] = !empty($fbUser['email']) ? $fbUser['email'] : '';

            /* $userData['picture'] = !empty($fbUser['picture']['data']['url']) ? $fbUser['picture']['data']['url'] : '';
              $userData['link'] = !empty($fbUser['link']) ? $fbUser['link'] : 'https://www.facebook.com/'; */

            return $this->social_registration($userData);
        } else {
            // Facebook authentication url 
            $fb_uri = $facebook->login_url();
            return redirect()->to($fb_uri);
        }
    }

    /**
     * Social registration function
     * @access public
     * @description helper function
     * @author Mahek
     */
    function social_registration($userData = array()) {
        if (!empty($userData)) {
            if (isset($userData['id'])) {
                unset($userData['id']);
            }
            $condition['email'] = $userData['email'];
            $this->general->set_table('user');
            $already_registered = $this->general->get('*', $condition);

            if ($already_registered) {
                $updateCondition['user_id'] = $userId = $already_registered[0]['user_id'];
                $userData['updated_time'] = DATE_TIME;
                $this->general->update($userData, $updateCondition);
            } else {
                $userData['status'] = STATUS_ACTIVE;
                $userData['created_time'] = DATE_TIME;
                $userId = $this->general->save($userData);
            }

            $user_details_condition['user_id'] = $userId;
            $userDetails = $this->general->get('*', $user_details_condition);

            $this->session->set('user_logged_in', $userDetails[0]);
            return redirect()->to(base_url(USER_FOLDER_NAME . '/dashboard'));
        } else {
            return redirect()->to(base_url());
        }
    }

    /**
     * Logout : Clear sesssion
     * @access public
     * @return true or false (redirect to view)
     * @author by Rajnish Savaliya (TheFoxLab.com)
     */
    public function logout() {
        $user_id = session()->get('user_logged_in');
        if ($user_id != '' || $user_id != null) {
            session()->destroy();
            $this->set_msg("Successfully Logout");

            $facebook = new \App\Libraries\Facebookauth();
            $facebook->destroy_session();
        }
        return redirect()->to(base_url());
    }

}

?>