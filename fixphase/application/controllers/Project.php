<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'/libraries/Auth_Controller.php';

class Project extends Auth_Controller{

    public function index(){
        echo "index";
    }

    public function index_get(){
        //GET requests
        //use this to insure that the request is coming from an ajax call not from a browser
        //if($this->input->is_ajax_request()){
        if($this->get('pid') == null){
            $this->get_all_projects();
        }else{
            //request to get data about a specific project with the project id
            $this->get_project($this->get('pid'));
        }
        //}
    }

    public function index_post(){
        //POST requests
        $this->insert_project();
    }

    public function summary_get(){
        if($this->get('pid') != null){
            $this->get_project_summary($this->get('pid'));
        }
    }

    public function contributor_get(){
        if($this->get('pid') != null){
            $this->get_project_contributors($this->get('pid'));
        }
    }

    public function contributor_put(){
        $this->insert_contributor();
    }

    public function insert_contributor(){

        //$this->load->model('project_model');
        //$this->project_model->insert_contributor('515166','1', '1');
        $user_id = $this->put('user_id');
        $project_id = $this->put('pid');
        $email = $this->put('email');
        $role = $this->put('role');

        $this->load->model('user_model');

        $json = new stdClass();
        $json->data = new stdClass();

        if($user_id != null && $project_id != null && $email != null && $role != null && $this->user_model->user_exists($user_id) && $user_id == $this->session->userdata('user_id')){
            $contributor_id = $this->user_model->get_user_by_email($email);
            if($contributor_id){
                $contributor_id = $contributor_id[0]->user_id;
                $this->load->model('project_model');
                if(!$this->project_model->is_contributor($contributor_id, $project_id)){
                    $result = $this->project_model->insert_contributor($contributor_id, $project_id, $role);
                    if($result) {
                        $user_name = $this->user_model->get_user_name($user_id)->full_name;
                        $project_name = $this->project_model->get_project($project_id)->project_name;
                        if($user_name && $project_name){
                            //email
                            $this->load->library('email');

                            $config['protocol']    = 'smtp';
                            $config['smtp_host']    = 'ssl://smtp.gmail.com';
                            $config['smtp_port']    = '465';
                            $config['smtp_timeout'] = '7';
                            $config['smtp_user']    = 'customers.gonow@gmail.com';
                            $config['smtp_pass']    = 'gonow123';
                            $config['charset']    = 'utf-8';
                            $config['newline']    = "\r\n";
                            $config['mailtype'] = 'text'; // or html
                            $config['validation'] = TRUE; // bool whether to validate email or not

                            $this->email->initialize($config);
                            $this->email->set_newline("\r\n");
                            $this->email->from('customers.gonow@gmail.com', 'Abdulaziz Alaa');
                            $this->email->to($email);

                            $this->email->subject('FixPhase - Contributor');

                            $r = ($role==1)?"Developer":"Tester";
                            $message =  "FixPhase \r\n\n" .
                            "This email is sent to inform you with a contributor invitation \n\n ".
                            "You have been invited by {$user_name} to be a contributor in {$project_name} project \n\n".
                            "Your role will be a {$r} \n\n".
                                "Regards \n".
                                "FixPhase Team \r\n";

                            $this->email->message($message);

                            if($this->email->send()){
                                $json->data->project_id = $result;
                            }else{
                                $json->data->error = new stdClass();
                                $json->data->error->id = '2';
                                $json->data->error->message = 'Error sending email';
                            }
                        }
                    }else{
                        $json->data->error = new stdClass();
                        $json->data->error->id = '2';
                        $json->data->error->message = 'Error user could not be invited';
                    }
                }else{
                    $json->data->error = new stdClass();
                    $json->data->error->id = '2';
                    $json->data->error->message = 'Error Duplicate Entry';
                }
            }else{
                $json->data->error = new stdClass();
                $json->data->error->id = '2';
                $json->data->error->message = 'Invalid email';
            }

        }else{
            $json->data->error = new stdClass();
            $json->data->error->id = '2';
            $json->data->error->message = 'Invalid user id';
        }

        $this->response($json);
    }

    public function insert_project(){
        $user_id = $this->post('user_id');
        $project_name = $this->post('project_name');
        $project_summary = $this->post('project_summary');

        $this->load->model('user_model');

        $json = new stdClass();
        $json->data = new stdClass();

        if($user_id != null && $project_name != null && $project_summary != null && $this->user_model->user_exists($user_id) && $user_id == $this->session->userdata('user_id')){
            $this->load->model('project_model');
            $result = $this->project_model->insert_project($project_name, $project_summary, $user_id);
            if($result){
                $json->data->project_id = $result;
            }else{
                $json->data->error = new stdClass();
                $json->data->error->id = '2';
                $json->data->error->message = 'Invalid Entry';
            }
        }else{
            $json->data->error = new stdClass();
            $json->data->error->id = '2';
            $json->data->error->message = 'Invalid user id';
        }

        $this->response($json);
    }

    public function get_project_summary($project_id){
        //request to get a specific project summary with the project id
        /*
        {
            "data":
            {
                ‘summary’ : ‘ ’
            },
            "error":{"id":""}
        }
        */
        $this->load->model('project_model');
        $data = $this->project_model->get_project($project_id);
        if($data){
            if($this->get('user_id') == $this->session->userdata('user_id') && $this->project_model->has_auth($this->get('user_id'), $project_id)){

                $json = array(
                    'data' => array(
                        'summary' => $data->description
                    )
                );
            }else{
                //unauthorized
                $json = array(
                    'data' => '',
                    'error' => array(
                        'id' => '1'
                    )
                );
            }
            $this->response($json);
        }else{
            //not found
            $this->response('',404);
        }
    }

    public function get_project_contributors($project_id){
        //request to get a specific project contributors with the project id
        /*
        {
            "data":
            {
                ‘contributors’ : [ { ‘username’ : ‘ ‘, ‘id’ : ‘ ’ } ]
            },
            "error":{"id":""}
        }
        */
        $this->load->model('project_model');
        $data = $this->project_model->get_project($project_id);
        if($data){
            if($this->get('user_id') == $this->session->userdata('user_id') && $this->project_model->has_auth($this->get('user_id'), $project_id)){

                $json = array(
                    'data' => array(
                        'contributors' => $this->project_model->get_contributors($project_id)
                    )
                );
            }else{
                //unauthorized
                $json = array(
                    'data' => '',
                    'error' => array(
                        'id' => '1'
                    )
                );
            }
            $this->response($json);
        }else{
            //not found
            $this->response('',404);
        }
    }

    public function get_project($project_id){
        //request to get data about a specific project with the project id
        /*
        {
            "data":
            {

                ‘summary’ : ‘ ’,
                ‘owner’: ‘’,
                ‘contributors’ : [ { ‘username’ : ‘ ‘, ‘id’ : ‘ ’ } ],
                ‘statistics’ :
                {
                    ‘total_bugs’ : ‘ ‘,
                    ‘opened_bugs’ : ‘ ‘,
                    ‘assigned_bugs’ : ‘ ‘,
                    ‘solved_bugs’ : ‘ ‘,
                    ‘closed_bugs’ : ‘ ‘
                }

            },
            "error":{"id":""}
        }
        */
        $this->load->model('project_model');
        $data = $this->project_model->get_project($project_id);
        if($data){
            if($this->get('user_id') == $this->session->userdata('user_id') && $this->project_model->has_auth($this->get('user_id'), $project_id)){
                $statistics = new stdClass();
                $statistics->total_bugs = $this->project_model->get_bugs_count($project_id, '');
                $statistics->opened_bugs = $this->project_model->get_bugs_count($project_id, 'opened');
                $statistics->assigned_bugs = $this->project_model->get_bugs_count($project_id, 'assigned');
                $statistics->solved_bugs = $this->project_model->get_bugs_count($project_id, 'solved');
                $statistics->closed_bugs = $this->project_model->get_bugs_count($project_id, 'closed');

                $json = array(
                    'data' => array(
                        'summary' => $data->description,
                        'owner' => $data->created_by,
                        'contributors' => $this->project_model->get_contributors($project_id),
                        'statistics' => $statistics
                    )
                );
            }else{
                //unauthorized
                $json = array(
                    'data' => '',
                    'error' => array(
                        'id' => '1'
                    )
                );
            }
            $this->response($json);
        }else{
            //not found
            $this->response('',404);
        }
    }

    public function get_all_user_projects(){
        //request to get all the projects in the database related to specific user
        //will return ids and names and owner id only
        /*
        {
          "data":
            {
                [
                  {"project_id":"1","project_name":"game", created_by:'515166'},
                  {"project_id":"2","project_name":"hayyy", created_by:'515166'}
                ]
            },
          "error":{"id":""}
        }
        */
        $this->load->model('project_model');
        $data = $this->project_model->get_all_user_projects($this->session->userdata('user_id'), 0, 500);
        if($data){
            if($this->get('user_id') == $this->session->userdata('user_id')){

                $json = array(
                    'data' => $data
                );

            }else{
                //unauthorized
                $json = array(
                    'data' => '',
                    'error' => array(
                        'id' => '1'
                    )
                );
            }
            $this->response($json);
        }else{
            //not found
            $this->response('',404);
        }
    }

    public function get_all_projects(){
        //request to get all the projects in the database
        //will return ids and names and owner id only
        /*
            {
              "data":
                {
                    [
                      {"project_id":"1","project_name":"game", created_by:'515166'},
                      {"project_id":"2","project_name":"hayyy", created_by:'515166'}
                    ]
                },
              "error":{"message":""}
            }
        */
        $this->load->model('project_model');
        $data = $this->project_model->get_all_projects(0, 500);
        if($data){
            if($this->get('user_id') == $this->session->userdata('user_id')){

                $json = array(
                    'data' => $data
                );
            }else{
                //unauthorized
                $json = array(
                    'data' => '',
                    'error' => array(
                        'id' => '1'
                    )
                );
            }

            $this->response($json);
        }else{
            //not found
            $this->response('',404);
        }


    }

}
