<?php

//include 'pdfmerger/PDFMerger.php';
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Admin extends CI_Controller {

    function __construct() {
        parent::__construct();

        /* Standard Libraries */
        $this->load->database();
        $this->load->helper('url');
        /* ------------------ */

        $this->load->library('grocery_CRUD');
        $this->load->library('tank_auth');
        date_default_timezone_set('Asia/Calcutta');
        if (!($this->tank_auth->is_logged_in() && ($this->tank_auth->get_role() == 'admin' || $this->tank_auth->get_role() == 'reviewer')/*&&($this->tank_auth->get_function_access($this->tank_auth->get_user_id(),$this->router->method))*/)) {

            redirect('/auth/logout');
        }
	
    }

    function check_permission($role) {
        if (!($this->tank_auth->is_logged_in() && ($this->tank_auth->get_role() == $role))) {

            redirect('/auth/logout');
        }
    }
	
	function forms()
	{
		$data="";
		$this->load->view('forms.php',$data);
	}
    
    function _example_output($output = null) {
		 $this->load->model('admin_model');
		//var_dump($this->admin_model->get_list_of_functions($this->tank_auth->get_user_id()));
        //$output['links']=array();
		$data['output']=$this->admin_model->get_list_of_functions($this->tank_auth->get_user_id());
		
		$this->load->view('dynamic_link.php', $data);
		//$data="";
		//$this->load->view('forms.php',$data);
		$this->load->view('admin.php', $output);
    }

    function read_file($folder, $file, $subfolder = '') {
        $folder = urldecode($folder);
        $file = str_replace("%20", " ", $file);
        if ($subfolder != '')
            $remoteImage = "../admin/assets/uploads/files/$folder/$subfolder/$file";
        else
            $remoteImage = "../admin/assets/uploads/files/$folder/$file";

        $imginfo = getimagesize($remoteImage);
        //var_dump($imginfo);
        //$this->output->set_content_type($imginfo['mime']);
        header("Content-type:" . $imginfo['mime']);
        $data = readfile($remoteImage);
        $this->output->append_output($data);
    }

    function read_image($folder, $file) {
        echo "<img  src=" . site_url() . "/admin/read_file/$folder/$file>";
    }

    //////////////////////////
    function addresses_management() {
        $this->load->library('grocery_CRUD');
        $this->load->library('ajax_grocery_CRUD');

//create ajax_grocery_CRUD instead of grocery_CRUD. This extends the functionality with the set_relation_dependency method keeping all original functionality as well
        $crud = new ajax_grocery_CRUD();

//this is the default grocery CRUD model declaration
        $crud->set_table('address');
        $crud->set_relation('ad_country_id', 'country', 'c_name');
        $crud->set_relation('ad_state_id', 'state', 's_name');

//this is the specific line that specifies the relation.
// 'ad_state_id' is the field (drop down) that depends on the field 'ad_country_id' (also drop down).
// 's_country_id' is the foreign key field on the state table that specifies state's country
        $crud->set_relation_dependency('ad_state_id', 'ad_country_id', 's_country_id');
         $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///////////////////////////// Mains Questions Search////////////////////////
    //////////////////////////////////////SEARCH
    function search() {
        $string = $_REQUEST['q'];
        $output = "";
        $this->load->database();
        $this->load->helper('form');
        $this->load->library('table');
        $output.=form_open('admin/search');
        $output.=form_input('q', $string);
        $output.=form_submit('search_button', 'SEARCH');
        $output.=form_close("");

        $tmpl = array(
            'table_open' => '<table border="1" cellpadding="4" cellspacing="0">',
        );

        $this->table->set_template($tmpl);

        $output.="<h1> SEARCH RESULT FOR  $string</h1>";
        $q = "SELECT qid, tid, (select GROUP_CONCAT(test_id) from  mains_test_questions where `mains_test_questions`.`qid`= `question`.`qid`)   as used_in_tests, created_by, question_text,question_status from question where question_text like '%" . $string . "%' or qid like '%" . $string . "%' order by qid desc LIMIT 100";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);



        echo $output;
    }

    ////////////////////
    function payment_report() {
        $this->check_permission('admin');
        /* $this->load->database();
          $this->load->library('table');
          $output="<h1> Payment Report  </h1>";
          $q="SELECT m.member_id,concat(m.firstname,' ',m.lastname) as name, m.login ,p.merchantTxnID, a.amount, p.gateway, p.txnEnd as date, m.status as activation_status FROM `pgtoav` p left join members m on p.member_id=m.member_id  left join avtopg a on a.merchantTxnId=p.merchantTxnID WHERE `respcode`=0 order by merchantTxnId desc";
          $query = $this->db->query($q);
          $output.=$this->table->generate($query);
          echo  $output; */
        $crud = new grocery_CRUD();
        $crud->set_table('view_payment_report');
        $crud->set_theme('datatables');
        $crud->set_primary_key('merchantTxnID');
        $crud->order_by('merchantTxnID', 'desc');
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    //////////////////Package bundling
    function bundled_package() {
        $crud = new grocery_CRUD();
        $crud->set_table('bundled_package');
        $crud->set_theme('datatables');
        $crud->callback_after_insert(array($this, 'assign_course'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function assign_course($post_array, $primary_key) {
        $pp = $post_array['primary_package'];
        $ppt = $post_array['primary_package_type'];
        $sp = $post_array['secondary_package'];
        $spt = $post_array['secondary_package_type'];
        $table['mains'] = "packages_students";
        $table['prelims'] = "pt_packages_students";
        $table['interview'] = "interview_student";
        $table['material'] = "material_package_student";
        $table['classroom'] = "classroom_students";

        $q = "insert ignore into " . $table[$spt] . "(package_id,member_id)(select $sp,member_id from " . $table[$ppt] . " where package_id=$pp)";
        $query = $this->db->query($q);
    }

    //////////////////complain
    function complain($user = -1, $status = 0, $priority = 0) {
        $userid = $this->tank_auth->get_user_id();
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
        $crud->set_table('complain');

        //$crud->set_relation('member_id','members','{member_id}-  {firstname} {lastname}',null,'member_id');
        $crud->set_relation('category', 'complain_category', '{id}- {name}');
        $crud->set_relation('status', 'complain_status', '{id}- {name}');
        $crud->set_relation('assigned_to', 'users', '{username}-{id}', array('activated' => '1'));
        $crud->set_relation('created_by', 'users', '{id}-{username}');
        $crud->columns('id', 'member_id', 'status', 'complain', 'category', 'priority', 'follow_up', 'created_by', 'assigned_to', 'received_on', 'last_updated', 'resolution_comment');
        $crud->add_fields('member_id', 'complain', 'category', 'priority', 'assigned_to');
        // $crud->fields('member_id', 'status', 'complain', 'category', 'priority', 'follow_up', 'created_by', 'assigned_to', 'received_on','resolution_comment');
        $crud->unset_edit_fields('member_id', 'created_by');

        $crud->callback_before_update(array($this, 'backup_complain'));
        $crud->add_action('History', '', '', '', array($this, 'complain_histoy_link'));
        $crud->field_type('created_by', 'hidden', $userid);
        $crud->order_by('id', 'desc');

        if ($user == 0) {
            
        } else if ($user == -1)
            $crud->where('assigned_to', $userid);
        else if ($user > 0)
            $crud->where('assigned_to', $user);

        if ($status)
            $crud->where('complain.status', $status);
        else
            $crud->where('complain.status <=', 4);

        if ($priority)
            $crud->where('complain.priority', $priority);

        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function backup_complain($post_array, $primary_key) {


        $this->load->database();

        $email = $this->tank_auth->get_email($post_array['assigned_to']);
        $cc = $this->tank_auth->get_email($post_array['created_by']);
        $q = "INSERT INTO `bc_complain`( `complain_id`, `member_id`, `status`, `complain`, `category`, `priority`, `follow_up`, `created_by`, `assigned_to`, `received_on`, `last_updated`,`resolution_comment`)  (select * from complain where id=$primary_key);";
        $query = $this->db->query($q);

        $this->load->library('email');
        $this->email->from('payal.s@visionias.in', 'VISION IAS Complaint Management Team');
        $this->email->to($email);
        $this->email->cc($cc . ',info@visionias.in, payal.s@visionias.in, ajay.visionias@gmail.com, shikha.upadhyay@visionias.in ');   //email a copy to info also
        $this->email->subject("Complain No. $primary_key assigned to You");
        $this->email->message("<br>Reg. No." . $post_array['member_id'] . "<br>Status " . $post_array['status'] . "<br>Complain:" . $post_array['complain'] . "<br>Follow Up:" . $post_array['follow_up'] . "<br>Assigned to " . $post_array['assigned_to'] . "<br>Resolution " . $post_array['resolution_comment']);
        $this->email->send();

        return;
    }

    function complain_histoy_link($primary_key, $row) {
        return site_url('admin/complain_history') . '/' . $primary_key;
    }

    function complain_history($id) {
        $userid = $this->tank_auth->get_user_id();
        $crud = new grocery_CRUD();
        $crud->set_table('bc_complain');

        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}', null, 'member_id');
        $crud->set_relation('category', 'complain_category', '{id}- {name}');
        $crud->set_relation('status', 'complain_status', '{id}- {name}');
        $crud->set_relation('assigned_to', 'users', '{id}-{username}');
        $crud->set_relation('created_by', 'users', '{id}-{username}');
        $crud->columns('id', 'complain_id', 'member_id', 'status', 'complain', 'category', 'priority', 'follow_up', 'created_by', 'assigned_to', 'received_on', 'last_updated');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        if ($id)
            $crud->where('complain_id', $id);
        $crud->order_by('id', 'desc');
        $output = $crud->render();
        $this->_example_output($output);
    }

    /////////////////////function upload
    function uploader() {
        $output = "";
        $this->load->view('upload_view.php', $output);
    }

    function upload() {
        // A list of permitted file extensions
        $allowed = array('png', 'jpg', 'gif', 'zip');

        if (isset($_FILES['upl']) && $_FILES['upl']['error'] == 0) {

            $extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);

            if (!in_array(strtolower($extension), $allowed)) {
                echo '{"status":"error"}';
                exit;
            }

            if (move_uploaded_file($_FILES['upl']['tmp_name'], '../../admin/assets/uploads/' . $_FILES['upl']['name'])) {

                echo '{"status":"success"}' . site_url('admin/assets/uploads/') . $_FILES['upl']['name'];
                exit;
            }
        }

        echo '{"status":"error"}';
        exit;
    }

    //////////////////email
    function email_messages() {
        //$this->db->get_where('mains_tests', array('test_id' => $test_id));
        $crud = new grocery_CRUD();
        $crud->set_table('email_messages');
        $crud->set_relation('package_id', 'mains_tests_packages', '{package_id}-{package_name}');
        $crud->add_fields('package_id', 'subject', 'message', 'attachment');

        $crud->set_field_upload('attachment', 'assets/uploads/files/email');
        $crud->callback_before_insert(array($this, 'send_group_email'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function send_group_email($post_array) {

        $this->load->model('admin_model');
        $data = $this->admin_model->email_get($post_array['package_id']);

        $this->load->library('email');
        foreach ($data as $row) {

            $this->email->from('admin@visionias.in', 'Vision IAS ADMIN');
            $this->email->to($row->login);
            $this->email->bcc('ashvani.kumar@gmail.com');
            $this->email->subject($post_array['subject']);
            $this->email->message($post_array['message']);
            $this->email->attach($row->attachment);

            $send = $this->email->send();
            $log = $this->email->print_debugger();
        }
        return $post_array;
    }

    ///////////////////////////////////////////////// Experts
    function users() {
        $crud = new grocery_CRUD();
		$password='no';
		if(isset($_REQUEST['password']))
			$password=$_REQUEST['password'];
        $crud->set_table('users');
        $crud->set_theme('datatables');
        //$crud->columns('id', 'username', 'email', 'mobile', 'activated', 'role_id');
		$crud->columns('id', 'username', 'email', 'mobile', 'activated', 'role_id');
        $crud->set_relation('role_id', 'roles', '{id}-{role}', null, 'id');
        if($password=='yes')
		$crud->fields( 'username','password','activated', 'banned',  'ban_reason');
	     else 
		//$crud->fields( 'id','username','functions', 'test_codes', 'short_code',  'email', 'mobile', 'role_id', 'checkingcopy', 'medium', 'type');
	    $crud->fields( 'username','functions', 'test_codes', 'short_code',  'email', 'mobile', 'role_id', 'checkingcopy', 'medium', 'type');
        $crud->set_relation_n_n('test_codes', 'test_expert', 'mains_tests', 'expert', 'test', 'test_id');
        $crud->set_relation_n_n('functions', 'app_access', 'app_function',  'expert_id','function_id', 'display_name','id');
		
		$crud->callback_before_insert(array($this, 'encrypt_password_expert'));
		$crud->callback_after_insert(array($this, 'add_linked_functions'));
		$crud->callback_after_update(array($this, 'add_linked_functions'));
        $crud->callback_before_update(array($this, 'encrypt_password_expert'));
        $crud->add_action('Assign', '../../images/anf.jpg', '', '', array($this, 'assign_pt_question'));
        $crud->add_action('Work Status', '../../images/anf.jpg', '', '', array($this, 'status_pt_question'));
        $crud->unset_print();
        $crud->unset_export();
        ////$crud->unset_add();
        //// $crud->unset_edit();
        $crud->unset_delete();
        $output = $crud->render();
        $this->_example_output($output);
    }
	
	//////////////Expert View only function
	function expert_view() {
        $crud = new grocery_CRUD();
        $crud->set_table('users');
        $crud->set_theme('datatables');
        $crud->columns('id', 'username', 'email', 'mobile', 'activated', 'role_id');
        $crud->set_relation('role_id', 'roles', '{id}-{role}', null, 'id');
        
        $crud->set_relation_n_n('test_codes', 'test_expert', 'mains_tests', 'expert', 'test', 'test_id');
       
		
        $crud->unset_print();
        $crud->unset_export();
        $crud->unset_add();
        $crud->unset_edit();
		$crud->unset_read();
        $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

	
	function exp_pwd() {
        $crud = new grocery_CRUD();
		
        $crud->set_table('users');
        $crud->set_theme('datatables');
        $crud->columns('id', 'username', 'email', 'mobile', 'activated', 'role_id');
        $crud->set_relation('role_id', 'roles', '{id}-{role}', null, 'id');
        
		$crud->fields( 'username','password','activated', 'banned',  'ban_reason');
	     
        $crud->callback_before_update(array($this, 'encrypt_password_expert'));
        
        $crud->unset_print();
          $crud->unset_export();
           ////$crud->unset_add();
           //// $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function assign_pt_question($primary_key, $row) {
        return site_url('admin/assign/') . "/" . $row->id;
    }

    function status_pt_question($primary_key, $row) {
        return site_url('admin/work_status/') . "/" . $row->id;
    }
    function add_linked_functions($post_array,$primary_key)
	{
		
		foreach($post_array['functions'] as $fn)
		{
			
			$query = $this->db->query("insert ignore into app_access(expert_id,function_id) (select $primary_key ,link_function_id from app_link_function where function_id=$fn)");
		}
		 return $post_array;
	}
    function encrypt_password_expert($post_array) {
        //$post_array['password'] = md5($post_array['password']);
		
        $post_array['password'] = $this->tank_auth->password_encrypt($post_array['password']);
        return $post_array;
    }

    function videos() {
        $crud = new grocery_CRUD();
        $crud->set_table('videos');
        $crud->set_theme('datatables');
        $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', null, 'test_id desc');
        $crud->set_field_upload('path', 'assets/uploads/files/videos');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function good_copy() {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
        $crud->set_table('good_copy');
        $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', null, 'test_id desc');
        $crud->set_field_upload('path', 'assets/uploads/files/good_copy');
        $crud->add_action('Download', '', 'admin/download_good_copy', 'ui-icon-plus');

       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $crud->unset_read();
        $output = $crud->render();
        $this->_example_output($output);
    }

    function download_good_copy($primary_key) {
        $this->load->helper('path');
        $q = "SELECT * from good_copy  WHERE id=$primary_key";
        $query = $this->db->query($q);


        header('Content-type: application/pdf');

        foreach ($query->result() as $row) {
            readfile(realpath("../") . "/admin/assets/uploads/files/good_copy/" . $row->path);
        }
    }

////////////////////
    /////////////////////////////////////////////////////
    /**
     *  Function:   convert_number
     *
     *  Description:
     *  Converts a given integer (in range [0..1T-1], inclusive) into
     *  alphabetical format ("one", "two", etc.)
     *
     *  @int
     *
     *  @return string
     *
     */
    function convert_number($number) {
        if (($number < 0) || ($number > 999999999)) {
            throw new Exception("Number is out of range");
        }

        $Gn = floor($number / 1000000);  /* Millions (giga) */
        $number -= $Gn * 1000000;
        $kn = floor($number / 1000);     /* Thousands (kilo) */
        $number -= $kn * 1000;
        $Hn = floor($number / 100);      /* Hundreds (hecto) */
        $number -= $Hn * 100;
        $Dn = floor($number / 10);       /* Tens (deca) */
        $n = $number % 10;               /* Ones */

        $res = "";

        if ($Gn) {
            $res .= $this->convert_number($Gn) . " Million";
        }

        if ($kn) {
            $res .= (empty($res) ? "" : " ") .
                    $this->convert_number($kn) . " Thousand";
        }

        if ($Hn) {
            $res .= (empty($res) ? "" : " ") .
                    $this->convert_number($Hn) . " Hundred";
        }

        $ones = array("", "One", "Two", "Three", "Four", "Five", "Six",
            "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen",
            "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eightteen",
            "Nineteen");
        $tens = array("", "", "Twenty", "Thirty", "Fourty", "Fifty", "Sixty",
            "Seventy", "Eigthy", "Ninety");

        if ($Dn || $n) {
            if (!empty($res)) {
                $res .= " and ";
            }

            if ($Dn < 2) {
                $res .= $ones[$Dn * 10 + $n];
            } else {
                $res .= $tens[$Dn];

                if ($n) {
                    $res .= "-" . $ones[$n];
                }
            }
        }

        if (empty($res)) {
            $res = "zero";
        }

        return $res;
    }

    //////////bills
    function bills() {
        $crud = new grocery_CRUD();
        $crud->set_table('bills');
        $expertid = $this->tank_auth->get_user_id();
        // $crud->set_theme('datatables');
        $crud->fields('member_id', 'name', 'description', 'total', 'basic', 'stax', 'created_by');


        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');
        $crud->add_action('Print Bill', '../../images/print1.jpg', '', '', array($this, 'print_bill_cb'));

        $crud->where('bills.created_by', $expertid);


        $crud->field_type('created_by', 'hidden');
        $crud->field_type('basic', 'hidden');
        $crud->field_type('stax', 'hidden');
        $crud->required_fields('member_id', 'total', 'name', 'description');

        $crud->callback_before_insert(array($this, 'calculate_tax'));
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function calculate_tax($post_array) {

        $post_array['basic'] = round($post_array['total'] / 1.14);
        $post_array['stax'] = round($post_array['basic'] * 0.14);
        $post_array['created_by'] = $this->tank_auth->get_user_id();
        return $post_array;
    }

    function print_bill_cb($primary_key, $row) {
        return site_url('admin/print_bill/') . '/' . $row->id;
    }

    function print_bill($bill_id) {

        $q = "SELECT b.id, b.name, b.date, b.description, b.total, b.basic,b.stax, b.created_by, m.login as email from bills
        b left join members m on m.member_id=b.member_id
        where id=$bill_id";
        $query = $this->db->query($q);
        $output = "";

        foreach ($query->result() as $row) {
            $output['id'] = "vision/del/" . $row->id;
            $output['email'] = $row->email;
            $output['name'] = $row->name;
            $output['date'] = date("d.m.Y  H:i:s", strtotime($row->date) + 37600);
            $output['description'] = $row->description;
            $output['total'] = $row->total;
            $output['basic'] = $row->basic;
            $output['stax'] = $row->stax;
            $output['created_by'] = $row->created_by;
            $output['words'] = "Rs. " . $this->convert_number($output['total']) . " Only";
        }

        $data['output'] = $output;
        $html = $this->load->view('bill.php', $data, true);

        $this->load->library('pdf');
        $pdf = $this->pdf->load();

        $pdf->WriteHTML($html); // write the HTML into the PDF



        if (isset($_GET['email'])) {
            $content = $pdf->Output('', 'S');
            $content = chunk_split(base64_encode($content));
            $mailto = $output['email']; //Mailto here
            $from_name = 'Registration Desk-VISION IAS'; //Name of sender mail
            $from_mail = 'registration@visionias.in'; //Mailfrom here
			 
            $subject = 'Your Bill from VISION IAS';
            $message = 'Dear Student, Please find the bill attached.';
            $filename = "vision-bill-" . date("d-m-Y_H-i", time()); //Your Filename whit local date and time
            //Headers of PDF and e-mail
            $boundary = "XYZ-" . date("dmYis") . "-ZYX";

            $header = "--$boundary\r\n";
            $header .= "Content-Transfer-Encoding: 8bits\r\n";
            $header .= "Content-Type: text/html; charset=ISO-8859-1\r\n\r\n"; //plain
            $header .= "$message\r\n";
            $header .= "--$boundary\r\n";
            $header .= "Content-Type: application/pdf; name=\"" . $filename . "\"\r\n";
            $header .= "Content-Disposition: attachment; filename=\"" . $filename . "\"\r\n";
            $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $header .= "$content\r\n";
            $header .= "--$boundary--\r\n";

            $header2 = "MIME-Version: 1.0\r\n";
            $header2 .= "From: " . $from_name . " \r\n";
            $header2 .= "CC: dispatch.visionias@gmail.com \r\n";
            $header2 .= "Return-Path: $from_mail\r\n";
            $header2 .= "Content-type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $header2 .= "$boundary\r\n";

            if (mail($mailto, $subject, $header, $header2, "-r" . $from_mail))
                echo "Email sent";
            else
                echo "Could not send the email";
        }
        else {
            $pdf->Output($pdfFilePath, 'I'); // save to file because we can
        }
    }

    function print_bill_online($merchantTxnID) {

        $q = "SELECT *,p.created_by as user from avtopg p
        left join members m on m.member_id=p.member_id
        where merchantTxnID=$merchantTxnID";
        $query = $this->db->query($q);
        $output = "";

        foreach ($query->result() as $row) {
            $output['id'] = "vision/online/$row->merchantTxnId/$row->invoiceNo";
			$output['member_id'] = $row->member_id;
            $output['email'] = $row->email;
			$output['fee_balance'] = $row->fee_balance;
			$output['fee_date'] = $row->fee_date;
            $output['name'] = $row->firstname . " " . $row->lastname . "<br>" . $row->street . "<br>" . $row->city . "<br>" . $row->state . "<br>" . $row->country . "<br>" . $row->pincode;
            $output['date'] = $row->TxnStartTS;
            $output['description'] = '';
            $output['total'] = $row->Amount;
			
            $output['created_by'] = $row->user;
            $output['basic'] = round($output['total'] / 1.14);
            $output['stax'] = $output['total'] - $output['basic'];
            $output['words'] = "Rs. " . $this->convert_number($output['total']) . " Only";
            parse_str($row->Courses);
            if (isset($Prelims)) {
                foreach ($Prelims as $key => $value) {
                    $output['description'].= "<br>Prelims Module No. " . $value;
                }
            }

            if (isset($Mains)) {
                foreach ($Mains as $key => $value) {
                    $output['description'].="<br>Mains Module No. " . $value;
                }
            }

            if (isset($Interview)) {
                foreach ($Interview as $key => $value) {
                    $output['description'].="<br>Interview Module No. " . $value;
                }
            }
            if (isset($Material)) {
                foreach ($Material as $key => $value) {
                    $output['description'].="<br>Material Module No. " . $value;
                }
            }
			 if (isset($Classroom)) {
                foreach ($Classroom as $key => $value) {
                    $output['description'].="<br>Classroom Module No. " . $value;
                }
            }
        }

        $data['output'] = $output;

        $html = $this->load->view('bill.php', $data, true);

        $this->load->library('pdf');
        $pdf = $this->pdf->load();

        $pdf->WriteHTML($html); // write the HTML into the PDF



        if (isset($_GET['email'])) {
            $content = $pdf->Output('', 'S');
            $content = chunk_split(base64_encode($content));
            $mailto = $output['email']; //Mailto here
			
            $from_name = 'Registration Desk-VISION IAS'; //Name of sender mail
            $from_mail = 'registration@visionias.in'; //Mailfrom here
            $subject = 'Your Bill From VISION IAS';
            $message = 'Dear Student, Please find the bill attached.';
            $filename = "vision-bill-" . date("d-m-Y_H-i", time()); //Your Filename with local date and time
            //Headers of PDF and e-mail
            $boundary = "XYZ-" . date("dmYis") . "-ZYX";

            $header = "--$boundary\r\n";
            $header .= "Content-Transfer-Encoding: 8bits\r\n";
            $header .= "Content-Type: text/html; charset=ISO-8859-1\r\n\r\n"; //plain
            $header .= "$message\r\n";
            $header .= "--$boundary\r\n";
            $header .= "Content-Type: application/pdf; name=\"" . $filename . "\"\r\n";
            $header .= "Content-Disposition: attachment; filename=\"" . $filename . "\"\r\n";
            $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $header .= "$content\r\n";
            $header .= "--$boundary--\r\n";

            $header2 = "MIME-Version: 1.0\r\n";
            $header2 .= "From: " . $from_name . " \r\n";
            $header2 .= "CC: dispatch.visionias@gmail.com \r\n";
            $header2 .= "Return-Path: $from_mail\r\n";
            $header2 .= "Content-type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $header2 .= "$boundary\r\n";

            if (mail($mailto, $subject, $header, $header2, "-r" . $from_mail))
                echo "Email sent";
            else
                echo "Could not send the email";
        }
        else {
            $pdf->Output($pdfFilePath, 'I'); // save to file because we can
        }
    }

    /////Members table
    function members($start = 0, $end = 500) {
        $this->check_permission('admin');
        $crud = new grocery_CRUD();
        $crud->set_table('members');
        $crud->set_theme('datatables');



        $crud->columns('member_id', 'firstname', 'lastname', 'pt_test', 'mains_test', 'material', 'interview');
        $crud->set_relation('optional_1', 'subjects', '{subject_id}-{subject_name}', null, 'subject_id');
        $crud->set_relation('optional_2', 'subjects', '{subject_id}-{subject_name}', null, 'subject_id');
        $crud->set_relation_n_n('mains_test', 'packages_students', 'mains_tests_packages', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('material', 'material_package_student', 'material_package', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('interview', 'interview_student', 'interview_packages', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('pt_test', 'pt_packages_students', 'pt_tests_packages', 'member_id', 'package_id', 'package_name');

        $crud->set_relation('status', 'membership_status', '{status_id}-{status_name}');
        $crud->set_relation('country_pr', 'country', '{name}', null, 'name asc');
        $crud->set_relation('state_pr', 'state', '{name}', null, 'name asc');
        //$crud->set_relation('city_pr', 'city','{name}-{state}',null,'name asc');

        $crud->set_relation('country_pm', 'country', '{name}', null, 'name asc');
        $crud->set_relation('state_pm', 'state', '{name}', null, 'name asc');
        // $crud->set_relation('city_pm', 'city','{name}-{state}',null,'name asc');

        $crud->set_relation('last_university', 'university', '{name}', null, 'name asc');
        $crud->callback_before_insert(array($this, 'encrypt_password_callback'));
        $crud->callback_after_insert(array($this, 'new_registration_email'));
        //$crud->callback_before_update(array($this,'encrypt_password_callback'));
        //$crud->add_fields('firstname');
        $crud->fields('member_id', 'firstname', 'lastname');
        $crud->add_fields('firstname', 'lastname', 'login', 'passwd', 'mains_test', 'material', 'interview', 'pt_test', 'dob', 'sex', 'qualification', 'last_university', 'about', 'designation', 'fathers_name', 'fathers_occupation', 'country_pr', 'state_pr', 'city_pr', 'pincode_pr', 'street_pr', 'country_pm', 'state_pm', 'city_pm', 'pincode_pm', 'street_pm', 'telephone_local', 'telephone_permanent', 'previous_attempts_count', 'previous_attempts_details', 'optional_1', 'optional_2', 'medium', 'status', 'time', 'mode', 'centre', 'payment_details', 'photo', 'fee_balance', 'fee_date', 'fee_comment', 'idproof');
        $crud->edit_fields('member_id', 'firstname', 'lastname', 'priority', 'login', 'mains_test', 'material', 'interview', 'pt_test', 'dob', 'sex', 'qualification', 'last_university', 'about', 'designation', 'fathers_name', 'fathers_occupation', 'country_pr', 'state_pr', 'city_pr', 'pincode_pr', 'street_pr', 'country_pm', 'state_pm', 'city_pm', 'pincode_pm', 'street_pm', 'telephone_local', 'telephone_permanent', 'previous_attempts_count', 'previous_attempts_details', 'optional_1', 'optional_2', 'medium', 'status', 'time', 'mode', 'centre', 'payment_details', 'photo', 'fee_balance', 'fee_date', 'fee_comment', 'instruction', 'idproof');
        $crud->set_field_upload('photo', 'assets/uploads/files/registration');
        $crud->set_field_upload('idproof', 'assets/uploads/files/registration');
        //$crud->set_field_upload('payment_slip','assets/uploads/files/registration');
        $crud->add_action('schedule', '../../images/clock.jpg', 'admin/personal_schedule');
        $crud->add_action('Change Password', '../../images/rp.jpg', 'admin/pwd_reset/edit');
        //$crud->where('member_id >= ',$start);
        //$crud->where('member_id <= ',$end);
        if (isset($_POST['search'])) {
            $crud->like('firstname', $_POST['search']);
            $crud->or_like('lastname', $_POST['search']);
            $crud->or_like('login', $_POST['search']);
            $crud->or_like('member_id', $_POST['search']);
        }
        $crud->limit(100, 0);
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function email_to_admin_and_user($post_array, $primary_key) {



        if ($post_array['status'] == 1) {

            $this->load->library('email');

            $this->email->from('registration@visionias.in', 'Registration Desk-VISION IAS');
            $this->email->to($post_array['login']);
            $this->email->cc('admin@visionias.in, info@visionias.in, registration@visionias.in,jyoti@visionias.in,dispatch2.visionias@gmail.com,entry.visionias@gmail.com,shiwani.k@visionias.in,trapti@visionias.in,rn.visionias@gmail.com,sam.visionias@gmail.com');
//$this->email->bcc('ashvani.kumar@gmail.com');



            $this->email->subject('Vision IAS New Course Registration: Registration No.' . $post_array['member_id']);
            $msg = "";

            $msg.="Dear " . $post_array['firstname'] . ",<br> Courses assigned to  you are updated. You are now registered for";

            $msg.="<br><h3>Prelims</h3>";
            foreach ($post_array['pt_test'] as $value) {
                $msg.= "<br>Course ID:" . $value;
            }

            $msg.="<br><h3>Mains</h3>";
            foreach ($post_array['mains_test'] as $value) {
                $msg.= "<br>Course ID:" . $value;
            }


            $msg.="<br><h3>Material Support</h3>";
            foreach ($post_array['material'] as $value) {
                $msg.= "<br>Course ID:" . $value;
            }

            $this->email->message($msg);


            $this->email->send();
        }

        return true;
    }

    function members1() {
        $crud = new grocery_CRUD();
        $crud->set_table('members');

        $crud->fields('member_id', 'firstname', 'lastname', 'pt_test');
        $crud->set_relation_n_n('pt_test', 'pt_packages_students', 'pt_tests_packages', 'member_id', 'package_id', 'package_name', 'id');
$crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function new_registration_email($post_array, $primary_key) {
        if ($post_array['status'] == 1) {
            $this->load->helper('string');
            $post_array['member_id'] = $primary_key;
            //die($primary_key);
            // $post_array['passwd']="8ndfg@78sh";
            $this->load->library('email');

            $this->email->from('registration@visionias.in', 'Registration Desk-VISION IAS');
            $this->email->to($post_array['login']);
            $this->email->bcc('admin@visionias.in, info@visionias.in, registration@visionias.in,jyoti@visionias.in,dispatch2.visionias@gmail.com,entry.visionias@gmail.com,shiwani.k@visionias.in,trapti@visionias.in,rn.visionias@gmail.com,sam.visionias@gmail.com');
            //$this->email->bcc('info@visionias.in,registration@visionias.in');

            $this->email->subject('Vision IAS Registration Details: Registration No.' . $post_array['member_id']);
            $msg = "";

            $msg.="Dear " . $post_array['firstname'] . ",<br> Welcome to Vision IAS STUDENT ZONE. ";

            $msg.="<br> Your Registration No is <b>:" . $post_array['member_id'] . "</b>";
            $msg.="<br> STUDENT ZONE login ID: <b>" . $post_array['login'] . "</b>";
            $msg.="<br> STUDENT ZONE Password: <b>" . $post_array['plain_passwd'] . "</b>";
            $this->form_validation->set_message('participant_unique', 'A user with this firstname, lastname and date of birth already exists');
            $msg.="<br> Please always quote your REGISTRATION NUMBER while communicating with VISION IAS Team";
            $msg.="<br> Student Zone can be accessed directly from home page or via url   visionias.in/student ";
            $msg.="<br> for any technical problem related to student zone please contact at registration@visionias.in ";
            $msg.="<p>How to send Mains Answer booklet </p>
<ul >
  <li>Please upload properly scanned copies from student zone only. </li>
  <li>Name your answer booklet ( soft copy ) in this format:&nbsp;(Registration Number_ Test No_Name);&nbsp;( ex : 3288_351_Praveen ).&nbsp; </li>
  <li>The soft copy should not have a dark background which makes it difficult to read and write comments.&nbsp; </li>
  <li>Please ensure good readability;before sending the copy; </li>
  <li>It should be well printable on A4 size format; Please make  a SINGLE PDF for one copy.; </li>
  <li>YOUR COPY WILL BE RETURNED IF IT NOT FOUND IN PROPER FORMAT OR IF NOT PROPERLY READABLE.</li>
  <li>If you are sending your copy/answer booklet via airmail / speed post, then send it to the Rajender Nagar Center ONLY. </li>
 </ul>
<p>VISION  IAS </p>
<p>75,1st Floor,Old  Rajender Nagar Market Near Axis Bank,New  Delhi - 110060 </p>
<ul >
  <li>Your checked answer booklet will be uploaded within 10 days at online platform. Please make a complain if you do not receive an email notification of uploading of your unchecked copy within 3 days of sending, and uploading of your checked copy within 13 days of sending.  </li>
</ul>";


            $msg.="<br> <br> Best Wishes <br> Team Vision IAS <br> visionias.in";
            $msg.='<p align=center><font face="Monotype Corsiva" size="5" color="#FF6600">
When you are inspired by some great purpose, some extraordinary project,
<br>all your thoughts break their bonds, Your mind transcends limitations, your consciousness expands in
every direction,
<font face="Monotype Corsiva" size="5" color=black>
<br>and you find yourself in a new, great, and wonderful world.
<br>Dormant forces, faculties and talents become alive, </font>
<font color="green" face="Monotype Corsiva" size="5">
<br>and your discover yourself to be a greater person by far than you ever dreamed yourself
to be.
<br>~~~~~~~~~~~~~~~~Patanjali~~~~~~~~~~~~~~~~~~~</font></p> ';

            $this->email->message($msg);


            $this->email->send();
        }
        return $post_array;
    }

    function encrypt_password_callback($post_array) {
        //$post_array['passwd']=  random_string('alnum', 8);
        $post_array['plain_passwd'] = $post_array['passwd'];
        $post_array['passwd'] = md5($post_array['passwd']);
        return $post_array;
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

///////////////////////Assign Courses
    function update_courses() {
        //$this->db->get_where('mains_tests', array('test_id' => $test_id));
        $crud = new grocery_CRUD();

        $crud->set_table('members');
        $crud->columns('member_id', 'mains_test', 'pt_test', 'material', 'interview');
        $crud->edit_fields('member_id', 'mains_test', 'pt_test', 'material', 'interview', 'status');
        $crud->field_type('member_id', 'readonly');
        //$crud->field_type('status','readonly');

        $crud->set_relation_n_n('mains_test', 'packages_students', 'mains_tests_packages', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('material', 'material_package_student', 'material_package', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('pt_test', 'pt_packages_students', 'pt_tests_packages', 'member_id', 'package_id', 'package_name');
        $crud->set_relation_n_n('interview', 'interview_student', 'interview_packages', 'member_id', 'package_id', 'package_name');
        // $crud->callback_after_update(array($this, 'email_to_admin_and_user'));

        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

//////////////////////////// Password Reset
    function pwd_reset() {

        $crud = new grocery_CRUD();

        $crud->set_table('members');
        $crud->columns('member_id', 'firstname', 'lastname');
        $crud->edit_fields('member_id', 'firstname', 'lastname', 'login', 'passwd', 'status');
        $crud->set_relation('status', 'membership_status', '{status_id}-{status_name}');
        $crud->callback_before_update(array($this, 'encrypt_pwd'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();

        $this->_example_output($output);
    }

    function encrypt_pwd($post_array) {

        if ($post_array['status'] == 1) {
            //   $post_array['passwd']=  generateRandomString(10);
            // $post_array['passwd']="8ndfg@78sh";
            $this->load->library('email');

            $this->email->from('registration@visionias.in', 'Registration Desk-VISION IAS');
            $this->email->to($post_array['login']);
            $this->email->bcc('info@visionias.in, admin@visionias.in,entry.visionias@gmail.com,jyoti@visionias.in,shiwani.k@visionias.in, trapti@visionias.in,dispatch.visionias@visionias.in');

            $this->email->subject('Password Reset: Registration No.' . $post_array['member_id']);
            $msg = "";

            $msg.="Dear " . $post_array['firstname'] . ",<br> Greetings from VISION IAS !!! ";
			$msg.=" ";
			$msg.="<br> Your password has been reset.</b>";
            $msg.="<br> Your Registration No is <b>:" . $post_array['member_id'] . "</b>";
            $msg.="<br> STUDENT ZONE login ID: <b>" . $post_array['login'] . "</b>";
            $msg.="<br> STUDENT ZONE Password: <b>" . $post_array['passwd'] . "</b>";

            
            $msg.="<br> Student Zone can be accessed directly from home page or via url   visionias.in/student ";
            $msg.="<br> for any technical problem related to student zone please contact at registration@visionias.in ";
			$msg.="<br> Please always quote your REGISTRATION NUMBER while communicating with VISION IAS Team";
            $msg.="<p><b>How to send Mains Answer booklet </b></p>
<ul >
  <li>Please upload properly scanned copies from student zone only. </li>
  <li>Name your answer booklet ( soft copy ) in this format:&nbsp;(Registration Number_ Test No_Name);&nbsp;( ex : 3288_351_Praveen ).&nbsp; </li>
  <li>The soft copy should not have a dark background which makes it difficult to read and write comments.&nbsp; </li>
  <li>Please ensure good readability before sending the copy; </li>
  <li>It should be well printable on A4 size format, please make  a SINGLE PDF for one copy.</li>
  <li>If you are not using Vision IAS answer booklets, you can write on A4 size plain paper, with proper margins drawn on bot sides. It is important to leave first page for all the details and back side of first page blank for writing comments. You must attempt the questions in sequence and write questions also along with the answers.</li>
  <li><b>YOUR COPY WILL BE RETURNED IF IT IS NOT FOUND IN PROPER FORMAT OR IF NOT PROPERLY READABLE.</b></li>
  <li>If you are sending your copy/answer booklet via airmail / speed post, then send it to the Rajender Nagar Center only,address is given below. </li>
 </ul>

<p>VISION  IAS,75,1st Floor,Old  Rajender Nagar Market (Near Axis Bank),New  Delhi - 110060 </p>
<ul >
  <li>Your checked copy will be uploaded within 10 days at online platform. Please make a complain if you do not receive an email notification of uploading of your unchecked copy within 3 days of sending and uploading of your checked copy within 13 days of sending.  </li>
</ul>";
            $msg.="<br> <br> Best Wishes <br> Team Vision IAS <br> visionias.in";
            $msg.='<p align=center><font face="Monotype Corsiva" size="5" color="#FF6600">
When you are inspired by some great purpose, some extraordinary project,
<br>all your thoughts break their bonds, Your mind transcends limitations, your consciousness expands in
every direction,
<font face="Monotype Corsiva" size="5" color=black>
<br>and you find yourself in a new, great, and wonderful world.
<br>Dormant forces, faculties and talents become alive, </font>
<font color="green" face="Monotype Corsiva" size="5">
<br>and your discover yourself to be a greater person by far than you ever dreamed yourself
to be.
<br>~~~~~~~~~~~~~~~~Patanjali~~~~~~~~~~~~~~~~~~~</font></p> ';

            $this->email->message($msg);


            $this->email->send();
        }
        $post_array['passwd'] = md5($post_array['passwd']);
        return $post_array;
    }

///////////////////////////////////////////////////// Assign course to course
    function assign_course_to_course() {
        $output = "<h1>Assign course to course</h1>";
        if (isset($_POST['new_course_id'])) {
            $old_course_id = $_POST['old_course_id'];
            $old_course_table = $_POST['old_course_type'];
            $new_course_id = $_POST['new_course_id'];
            $new_course_table = $_POST['new_course_type'];

            $q = "insert ignore into $new_course_table(member_id, package_id) ( select member_id,'$new_course_id' from $old_course_table where package_id='$old_course_id');";
            echo $q;
            // $query = $this->db->query($q);
        } else {
            $attributes = array('name' => 'myform', 'target' => '_blank');
            $output.=form_open('admin/assign_course_to_course', $attributes);
            $options = array(
                'pt_packages_students' => 'Prelims',
                'packages_students' => 'Mains',
                'material_package_student' => 'Material',
            );

            $output.="Course Type " . form_dropdown('old_course_type', $options, 'Prelims');
            $output.="Student of Course ID " . form_input('old_course_id', '');


            $output.="Course Type " . form_dropdown('new_course_type', $options, 'Material');

            $output.="New Course ID " . form_input('new_course_id', '');

            $output.=form_submit('mysubmit', 'Assign');
            $output.=form_close("");
            $output;
            echo $output;
            // $this->load->view('admin.php',$output);
        }
    }

/////////////////////////////////////////////////////
    /////////////
    function list_students($test_id) {
        //$this->db->get_where('mains_tests', array('test_id' => $test_id));
        $crud = new grocery_CRUD();
        $crud->set_table('mains_tests');
        $crud->set_relation('copy_of', 'mains_tests', '{test_id}-{test_name}');
        //$crud->set_relation('member_id','members','{member_id}- {lastname} {firstname}');
        $crud->set_relation('status', 'test_status', '{status_id}-{status_name}');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');

        $crud->add_action('Add Score', 'http://www.nulledtemplates.com/images/ActiveDen-Extendable-Photo-Gallery-Rip_-evia_2.png', 'example/list_students');
        //$output = $crud->where('test_id',$test_id);
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function asd($test_id = 0, $member_id = 0, $date = 0, $day = 0, $rec = 0, $ans = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('asd');
        //$crud->columns('SN','day');
        $crud->set_primary_key('SN');
        if ($test_id)
            $crud->where('test_id', $test_id);
        if ($member_id)
            $crud->where('member_id', $member_id);
        if ($day)
            $crud->where('day>=', $day);
        if ($rec)
            $crud->where('RECEIVED_FROM_EXPERT', null);
        if ($ans)
            $crud->where("answer_script_checked=''", null);

        if ($date) {
            $date = str_replace('-', '/', $date);
            $crud->like('RECEIVED_FROM_EXPERT', $date);
            //echo $date;
        }
        $crud->order_by('SN', 'desc');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function getFilesFromDir($dir) {

        $files = array();
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if (is_dir($dir . '/' . $file)) {
                        $dir2 = $dir . '/' . $file;
                        //$files[] = getFilesFromDir($dir2);
                    } else {
                        $files['name'] = $file;
                        if (file_exists($file)) {
                            $stat = stat($file);
                            $files['name'] = date("d-m-Y", $stat['mtime']);
                        }
                    }
                }
            }
            closedir($handle);
        }

        return $files;
    }

    function copy_list() {

        $files = $this->getFilesFromDir("assets/uploads/files/answer_script/unchecked");
        var_dump($files);
    }

    function copy_verification($start = 0, $limit = 1000, $mid = 0, $date_un = 0, $date_ch = 0, $date_dl = 0, $date_re = 0, $date_appeared = 0, $date_appeared_2 = 0, $subject = 0, $test = 0, $center = "ALL", $expert = 0, $assigned = 0, $medium = 'All', $email = 'no', $inNote = 0, $note = '', $download = 'no') {
        $this->load->model('admin_model');
        $output = "";
        $tbldata = "";
        $count = 0;
        $condition = "";
        if (isset($_POST['member_id'])) {
            $mid = $_POST['member_id'];
        }
        if (isset($_POST['un'])) {
            $date_un = $_POST['un'];
        }
        if (isset($_POST['ch'])) {
            $date_ch = $_POST['ch'];
        }
        if (isset($_POST['dl'])) {
            $date_dl = $_POST['dl'];
        }
        if (isset($_POST['re'])) {
            $date_re = $_POST['re'];
        }

        if (isset($_POST['appeared'])) {
            $date_appeared = $_POST['appeared'];
        }
        if (isset($_POST['appeared_2'])) {
            $date_appeared_2 = $_POST['appeared_2'];
        }
        if (isset($_POST['subject'])) {
            $subject = $_POST['subject'];
        }
        if (isset($_POST['test'])) {
            $test = $_POST['test'];
        }
        if (isset($_POST['center'])) {
            $center = $_POST['center'];
        }
        if (isset($_POST['expert'])) {
            $expert = $_POST['expert'];
        }
        if (isset($_POST['assigned'])) {
            $assigned = $_POST['assigned'];
        }
        if (isset($_POST['start'])) {
            $start = $_POST['start'];
        }
        if (isset($_POST['limit'])) {
            $limit = $_POST['limit'];
        }
        if (isset($_POST['medium'])) {
            $medium = $_POST['medium'];
        }
        if (isset($_POST['email'])) {
            $email = $_POST['email'];
        }
        if (isset($_POST['note'])) {
            $note = $_POST['note'];
        }
        if (isset($_POST['inNote'])) {
            $inNote = $_POST['inNote'];
        }
        if (isset($_POST['download'])) {
            $download = $_POST['download'];
        }
        /////////GENERATE FORM///////////////
        $attributes = array('name' => 'myform', 'method' => 'post');

        $output.=form_open('admin/copy_verification', $attributes);
        $output.="Reg. No. " . form_input('member_id', $mid);
        $output.="Test No." . form_input('test', $test);

        $options = array(
            '0' => 'All',
            'Hindi' => 'Hindi',
            'English' => 'English',
        );
        $output.="Medium" . form_dropdown('medium', $options, $medium);
        $data = array(
            'name' => 'appeared_2',
            'id' => 'appeared_2',
            'value' => $date_appeared_2,
            'size' => '10',
        );
        $output.="Date From " . form_input($data);
        $data = array(
            'name' => 'appeared',
            'id' => 'appeared',
            'value' => $date_appeared,
            'size' => '10',
        );
        $output.="Date To " . form_input($data);

        $data = array(
            'name' => 'un',
            'id' => 'un',
            'value' => $date_un,
            'size' => '10',
        );
        $output.="<br/>Unchecked Upload " . form_input($data);

        $data = array(
            'name' => 'dl',
            'id' => 'dl',
            'value' => $date_dl,
            'size' => '10',
        );
        $output.="Deadline " . form_input($data);
        $data = array(
            'name' => 're',
            'id' => 're',
            'value' => $date_re,
            'size' => '10',
        );
        $output.="Receiving " . form_input($data);
        $data = array(
            'name' => 'ch',
            'id' => 'ch',
            'value' => $date_ch,
            'size' => '10',
        );
        $output.="Checked Upload" . form_input($data);
        $options = array(
            '0' => 'All',
            '1' => 'GS-1',
            '11' => 'Geography-11',
            '19' => 'Philosophy-19',
            '22' => 'Psyschology-22',
            '23' => 'Public Admin-23',
            '24' => 'Sociology-24',
            '53' => 'Essay-53',
        );
        $output.="Subject" . form_dropdown('subject', $options, $subject);

        $options = array(
            'ALL' => 'ALL',
            'MN' => 'MN',
            'RN' => 'RN',
            'KB' => 'KB',
            'JP' => 'JP',
            'DL' => 'DL',
            'DO' => 'DO',
        );
        $output.="<br/>Center" . form_dropdown('center', $options, $center);
        $options = array(
            '0' => 'ALL',
            '5' => 'Un-uploaded',
            '1' => 'Un-assigned',
            '2' => 'Assigned',
            '3' => 'UnChecked',
			'6' => 'Review Pending-Online',
			'7' => 'Review Pending-Offline',
			'8' => 'Rejected After Review',
            '4' => 'Checked',
			'9' => 'Pending to upload',
			
        );
        $output.="Copy status: " . form_dropdown('assigned', $options, $assigned);
        $output.="Expert: ". form_dropdown("expert", $this->admin_model->get_expert(0, 0, 1));
        $output.="Show search results from: " . form_input('start', $start);
        $output.="to: " . form_input('limit', $limit);
        $options = array(
            '0' => 'Not equals to',
            '1' => 'Equals to',
        );
        $output.="<br/>Comment: " . form_dropdown('inNote', $options, $inNote);
        $output.=" ".form_input('note', $note);
        $output.="Download" . form_checkbox('download', 'yes');
        $output.="Email" . form_checkbox('email', 'yes');
        $output.='<br/>'.form_submit('mysubmit', 'Submit');
        $output.=form_close("");
        $output;
//        echo $note;die();
        //echo $output;
		echo $date_un."<br>";
        /////////////////////////////////////
        $this->load->library('table');


        if ($mid)
            $condition.=" p.member_id=" . $mid . " AND ";
        if ($date_un)
            $condition.=" date(p.unchecked_upload_date)='" . date('Y-m-d', strtotime($date_un)) . "' AND ";
        if ($date_ch)
            $condition.=" date(p.checked_upload_date)='" . date('Y-m-d', strtotime($date_ch)) . "' AND ";
        if ($date_dl)
            $condition.=" date(p.deadline)='" . date('Y-m-d', strtotime($date_dl)) . "' AND ";
        if ($date_re)
            $condition.=" date(p.receiving_from_expert)='" . date('Y-m-d', strtotime($date_re)) . "' AND ";
        if ($date_appeared) {
            if ($date_appeared_2)
                $condition.=" (date(p.appeared_on)>='" . date('Y-m-d H:i:s', strtotime($date_appeared_2)) . "' AND " . " date(p.appeared_on)<='" . date('Y-m-d H:i:s', strtotime($date_appeared)) . "') AND ";
            else
                $condition.=" date(p.appeared_on)='" . date('Y-m-d H:i:s', strtotime($date_appeared)) . "' AND ";
        }
        if ($test)
            $condition.=" p.test_id='" . $test . "' AND ";
        if ($medium)
            $condition.=" m.medium='" . $medium . "' AND ";
        if ($center != 'ALL')
            $condition.=" p.appeared_at='" . $center . "' AND ";
        if ($expert&&$expert!='')
            $condition.=" p.expert_id='" . $expert . "' AND ";
        if ($subject)
            $condition.=" t.subject_id='" . $subject . "' AND ";
        if ($note) {
            $commentCondition = 'like';
            if($inNote == 0) {
                $commentCondition = 'not like';
            }
            $condition.=" p.comments $commentCondition '%" . $note . "%' AND p.appeared_on>='2015-01-01' or p.unchecked_upload_date>='2015-01-01' AND ";
        }
        if ($assigned == 1)
            $condition.=" (p.expert_id='' )AND ";
        else if ($assigned == 2)
            $condition.=" p.expert_id!='' AND p.answer_script_checked='' AND";
        else if ($assigned == 3)
            $condition.=" p.answer_script_checked='' AND ";
        else if ($assigned == 4)
            $condition.=" p.answer_script_checked!='' AND ";
        else if ($assigned == 5)
            $condition.=" p.answer_script_unchecked='' AND p.answer_script_checked='' AND";
  		else if ($assigned == 6)
             $condition.="p.review_status=0 AND p.answer_script_checked!='' AND p.review_comment='' AND ";
		else if ($assigned == 7)
             $condition.="p.receiving_from_expert!='0000-00-00 00:00:00' AND p.answer_script_checked='' AND p.review_comment='' AND p.checked_upload_date='0000-00-00 00:00:00' AND";			 			 
		 else if ($assigned == 9)             
			 $condition.="p.review_status=1 AND p.answer_script_checked='' AND p.review_comment!='' AND ";
		 else if ($assigned == 8)             
			 $condition.="p.answer_script_checked='' AND p.review_comment!='' AND p.review_status=0 AND";
		 
        
		 
        if ($download == 'yes') {
            $q = "SELECT p.answer_script_unchecked FROM personal_schedule as p
                      LEFT JOIN mains_tests as t on p.test_id=t.test_id
                      LEFT JOIN members m on m.member_id=p.member_id

                        where $condition  (p.appeared_on>='2015-01-01' or p.unchecked_upload_date>='2015-01-01')
                        order by p.appeared_on asc, checked_upload_date desc, p.member_id asc limit $start, $limit";
            //(answer_script_unchecked!='' OR answer_script_checked!='' OR p.appeared_on!=0 )
            //  echo $q;
            $query = $this->db->query($q);
            $file_names = $query->result_array();
//           $file_names['answer_script_unchecked'][0] = '1413677628_448_420_Ashvani Kumar.pdf';
//           $file_names['answer_script_unchecked'][1] = '1413677700_448_421_Ashvani Kumar.pdf';
            $file_path = realpath("../") . "/admin/assets/uploads/files/answer_script/unchecked/";

            $this->zipFilesAndDownload($file_names, $file_path);
        }
        $q = "SELECT *,DATEDIFF(checked_upload_date,unchecked_upload_date) as days FROM personal_schedule as p
                      LEFT JOIN mains_tests as t on p.test_id=t.test_id
                      LEFT JOIN members m on m.member_id=p.member_id

                        where $condition  (p.appeared_on>='2015-01-01' or p.unchecked_upload_date>='2015-01-01')
                        order by m.priority desc, p.appeared_on asc, checked_upload_date desc, p.member_id asc limit $start, $limit";
        //(answer_script_unchecked!='' OR answer_script_checked!='' OR p.appeared_on!=0 )
          echo $q;
		  //die();
        $query = $this->db->query($q);

        $data['query'] = $query;
        $data['output'] = $output;
        $data['email'] = $email;
        $this->load->view('answer_scripts.php', $data);
    }
function copy_status_view($start = 0, $limit = 1000, $mid = 0, $date_un = 0, $date_ch = 0, $date_dl = 0, $date_re = 0, $date_appeared = 0, $date_appeared_2 = 0, $subject = 0, $test = 0, $center = "ALL", $expert = 0, $assigned = 0, $medium = 'All', $email = 'no', $inNote = 0, $note = '', $download = 'no') {
        $this->load->model('admin_model');
        $output = "";
        $tbldata = "";
        $count = 0;
        $condition = "";
        if (isset($_POST['member_id'])) {
            $mid = $_POST['member_id'];
        }
        if (isset($_POST['un'])) {
            $date_un = $_POST['un'];
        }
        if (isset($_POST['ch'])) {
            $date_ch = $_POST['ch'];
        }
        if (isset($_POST['dl'])) {
            $date_dl = $_POST['dl'];
        }
        if (isset($_POST['re'])) {
            $date_re = $_POST['re'];
        }

        if (isset($_POST['appeared'])) {
            $date_appeared = $_POST['appeared'];
        }
        if (isset($_POST['appeared_2'])) {
            $date_appeared_2 = $_POST['appeared_2'];
        }
        if (isset($_POST['subject'])) {
            $subject = $_POST['subject'];
        }
        if (isset($_POST['test'])) {
            $test = $_POST['test'];
        }
        if (isset($_POST['center'])) {
            $center = $_POST['center'];
        }
        if (isset($_POST['expert'])) {
            $expert = $_POST['expert'];
        }
        if (isset($_POST['assigned'])) {
            $assigned = $_POST['assigned'];
        }
        if (isset($_POST['start'])) {
            $start = $_POST['start'];
        }
        if (isset($_POST['limit'])) {
            $limit = $_POST['limit'];
        }
        if (isset($_POST['medium'])) {
            $medium = $_POST['medium'];
        }
        if (isset($_POST['email'])) {
            $email = $_POST['email'];
        }
        if (isset($_POST['note'])) {
            $note = $_POST['note'];
        }
        if (isset($_POST['inNote'])) {
            $inNote = $_POST['inNote'];
        }
        if (isset($_POST['download'])) {
            $download = $_POST['download'];
        }
        /////////GENERATE FORM///////////////
        $attributes = array('name' => 'myform', 'method' => 'post');

        $output.=form_open('admin/copy_status_view', $attributes);
        $output.="Reg. No. " . form_input('member_id', $mid);
        $output.="Test No." . form_input('test', $test);

        $options = array(
            '0' => 'All',
            'Hindi' => 'Hindi',
            'English' => 'English',
        );
        $output.="Medium" . form_dropdown('medium', $options, $medium);
        $data = array(
            'name' => 'appeared_2',
            'id' => 'appeared_2',
            'value' => $date_appeared_2,
            'size' => '10',
        );
        $output.="Date From " . form_input($data);
        $data = array(
            'name' => 'appeared',
            'id' => 'appeared',
            'value' => $date_appeared,
            'size' => '10',
        );
        $output.="Date To " . form_input($data);

        $data = array(
            'name' => 'un',
            'id' => 'un',
            'value' => $date_un,
            'size' => '10',
        );
        $output.="<br/>Unchecked Upload " . form_input($data);

        $data = array(
            'name' => 'dl',
            'id' => 'dl',
            'value' => $date_dl,
            'size' => '10',
        );
        $output.="Deadline " . form_input($data);
        $data = array(
            'name' => 're',
            'id' => 're',
            'value' => $date_re,
            'size' => '10',
        );
        $output.="Receiving " . form_input($data);
        $data = array(
            'name' => 'ch',
            'id' => 'ch',
            'value' => $date_ch,
            'size' => '10',
        );
        $output.="Checked Upload" . form_input($data);
        $options = array(
            '0' => 'All',
            '1' => 'GS-1',
            '11' => 'Geography-11',
            '19' => 'Philosophy-19',
            '22' => 'Psyschology-22',
            '23' => 'Public Admin-23',
            '24' => 'Sociology-24',
            '53' => 'Essay-53',
        );
        $output.="Subject" . form_dropdown('subject', $options, $subject);

        $options = array(
            'ALL' => 'ALL',
            'MN' => 'MN',
            'RN' => 'RN',
            'KB' => 'KB',
            'JP' => 'JP',
            'DL' => 'DL',
            'DO' => 'DO',
        );
        $output.="<br/>Center" . form_dropdown('center', $options, $center);
        $options = array(
            '0' => 'ALL',
            '5' => 'Un-uploaded',
            '1' => 'Un-assigned',
            '2' => 'Assigned',
            '3' => 'UnChecked',
            '4' => 'Cheked',
        );
        $output.="Copy status: " . form_dropdown('assigned', $options, $assigned);
        $output.="Expert: ". form_dropdown("expert", $this->admin_model->get_expert(0, 0, 1));
        $output.="Show search results from: " . form_input('start', $start);
        $output.="to: " . form_input('limit', $limit);
        $options = array(
            '0' => 'Not equals to',
            '1' => 'Equals to',
        );
        $output.="<br/>Comment: " . form_dropdown('inNote', $options, $inNote);
        $output.=" ".form_input('note', $note);
        $output.="Download" . form_checkbox('download', 'yes');
        $output.="Email" . form_checkbox('email', 'yes');
        $output.='<br/>'.form_submit('mysubmit', 'Submit');
        $output.=form_close("");
        $output;
//        echo $note;die();
        //echo $output;
        /////////////////////////////////////
        $this->load->library('table');


        if ($mid)
            $condition.=" p.member_id=" . $mid . " AND ";
        if ($date_un)
            $condition.=" date(p.unchecked_upload_date)='" . date('Y-m-d H:i:s', strtotime($date_un)) . "' AND ";
        if ($date_ch)
            $condition.=" date(p.checked_upload_date)='" . date('Y-m-d H:i:s', strtotime($date_ch)) . "' AND ";
        if ($date_dl)
            $condition.=" date(p.deadline)='" . date('Y-m-d H:i:s', strtotime($date_dl)) . "' AND ";
        if ($date_re)
            $condition.=" date(p.receiving_from_expert)='" . date('Y-m-d H:i:s', strtotime($date_re)) . "' AND ";
        if ($date_appeared) {
            if ($date_appeared_2)
                $condition.=" (date(p.appeared_on)>='" . date('Y-m-d H:i:s', strtotime($date_appeared_2)) . "' AND " . " date(p.appeared_on)<='" . date('Y-m-d H:i:s', strtotime($date_appeared)) . "') AND ";
            else
                $condition.=" date(p.appeared_on)='" . date('Y-m-d H:i:s', strtotime($date_appeared)) . "' AND ";
        }
        if ($test)
            $condition.=" p.test_id='" . $test . "' AND ";
        if ($medium)
            $condition.=" m.medium='" . $medium . "' AND ";
        if ($center != 'ALL')
            $condition.=" p.appeared_at='" . $center . "' AND ";
        if ($expert)
            $condition.=" p.expert_id='" . $expert . "' AND ";
        if ($subject)
            $condition.=" t.subject_id='" . $subject . "' AND ";
        if ($note) {
            $commentCondition = 'like';
            if($inNote == 0) {
                $commentCondition = 'not like';
            }
            $condition.=" p.comments $commentCondition '%" . $note . "%' AND ";
        }
        if ($assigned == 1)
            $condition.=" (p.expert_id='' )AND ";
        else if ($assigned == 2)
            $condition.=" p.expert_id!='' AND ";
        else if ($assigned == 3)
            $condition.=" p.answer_script_checked='' AND ";
        else if ($assigned == 4)
            $condition.=" p.answer_script_checked!='' AND ";
        else if ($assigned == 5)
            $condition.=" p.answer_script_unchecked='' AND p.answer_script_checked='' AND";
        
		
        if ($download == 'yes') {
            $q = "SELECT p.answer_script_unchecked FROM personal_schedule as p
                      LEFT JOIN mains_tests as t on p.test_id=t.test_id
                      LEFT JOIN members m on m.member_id=p.member_id

                        where $condition  (p.appeared_on>='2014-01-01' or p.unchecked_upload_date>='2014-09-01')
                        order by p.appeared_on asc, checked_upload_date desc, p.member_id asc limit $start, $limit";
            //(answer_script_unchecked!='' OR answer_script_checked!='' OR p.appeared_on!=0 )
            //  echo $q;
            $query = $this->db->query($q);
            $file_names = $query->result_array();
//           $file_names['answer_script_unchecked'][0] = '1413677628_448_420_Ashvani Kumar.pdf';
//           $file_names['answer_script_unchecked'][1] = '1413677700_448_421_Ashvani Kumar.pdf';
            $file_path = realpath("../") . "/admin/assets/uploads/files/answer_script/unchecked/";

            $this->zipFilesAndDownload($file_names, $file_path);
        }
        $q = "SELECT *,DATEDIFF(checked_upload_date,unchecked_upload_date) as days FROM personal_schedule as p
                      LEFT JOIN mains_tests as t on p.test_id=t.test_id
                      LEFT JOIN members m on m.member_id=p.member_id

                        where $condition  (p.appeared_on>='2014-01-01' or p.unchecked_upload_date>='2014-09-01')
                        order by m.priority desc, p.appeared_on asc, checked_upload_date desc, p.member_id asc limit $start, $limit";
        //(answer_script_unchecked!='' OR answer_script_checked!='' OR p.appeared_on!=0 )
//          echo $q;die();
        $query = $this->db->query($q);

        $data['query'] = $query;
        $data['output'] = $output;
        $data['email'] = $email;
        $this->load->view('answer_scripts1.php', $data);
    }
    function mergeAndDownload($valid_files, $file_path) {
        $pdf = new PDFMerger;
        foreach ($valid_files as $file) {
            $pdf->addPDF($file_path . $file);
        }
        $pdf->merge('file', realpath("../") . "/admin/assets/uploads/files/answer_script/temp/" . '/navin_' . time() . '.pdf');
        die();
    }

    function zipFilesAndDownload($files, $file_path) {
        $valid_files = array();
        if (is_array($files)) {
            foreach ($files as $file) {
                if (strlen($file['answer_script_unchecked']) > 3) {
                    $fileName = $file_path . $file['answer_script_unchecked'];
                    if (file_exists($fileName)) {
                        $valid_files[] = $file['answer_script_unchecked'];
                    }
                }
            }
        }
        if (count($valid_files > 0)) {
            $zip = new ZipArchive();
            $zip_name = time() . ".zip";
            if ($zip->open($zip_name, ZIPARCHIVE::CREATE) !== TRUE) {
                $error .= "* Sorry ZIP creation failed at this time";
            }

            foreach ($valid_files as $file) {
                $zip->addFile($file_path . $file, $file);
            }

            $zip->close();
            if (file_exists($zip_name)) {
                // force to download the zip
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Cache-Control: private", false);
                header('Content-length: ' . filesize($zip_name));
                header('Content-type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                readfile($zip_name);
                // remove zip file from temp path
                unlink($zip_name);
            }
        } else {
            echo "No valid files to zip";
            exit;
        }
    }

    // The function is deprecated
    /* function zipAndDownload($files, $archive_file_name, $file_path) {
      $this->load->library('zip');
      $valid_files = array();
      if (is_array($files)) {
      foreach ($files as $file) {
      if (strlen($file['answer_script_unchecked']) > 3) {
      $fileName = $file_path . $file['answer_script_unchecked'];
      if (file_exists($fileName)) {
      $valid_files[] = $fileName;
      }
      }
      }
      }
      if (count($valid_files > 0)) {
      $zip = new ZipArchive();
      $zip_name = time() . ".zip";
      if ($zip->open($zip_name, ZIPARCHIVE::CREATE) !== TRUE) {
      $error .= "* Sorry ZIP creation failed at this time";
      }

      foreach ($valid_files as $file) {
      $this->zip->read_file($file);
      }
      $this->zip->download('my_backup.zip');
      }
      } */

    // The below function is of no use 
    /* function zipFilesAndDownload($file_names, $archive_file_name, $file_path) {
      $zip = new ZipArchive();
      $i = 1;
      //create the file and throw the error if unsuccessful
      if ($zip->open($archive_file_name, ZIPARCHIVE::CREATE) !== TRUE) {
      exit("cannot open <$archive_file_name>\n");
      }
      //var_dump($file_path);
      //add each files of $file_name array to archive
      foreach ($file_names as $files) {
      if (strlen($files['answer_script_unchecked']) > 3) {
      //        echo $files['answer_script_unchecked'].'<br/>';
      $zip->addFile($file_path . $files['answer_script_unchecked'], $files['answer_script_unchecked']);
      }
      //        echo "<br>".$i++." ".$file_path.$files['answer_script_unchecked']."<br>";
      }
      //    die();
      $zip->close();
      //    die();
      //then send the headers to foce download the zip file
      header("Content-type: application/zip");
      header("Content-Disposition: attachment; filename=$archive_file_name");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile("$archive_file_name");
      exit;
      } */

    function notice() {
        $crud = new grocery_CRUD();
        $crud->set_table('notice');
        $expertid = $this->tank_auth->get_user_id();
        $crud->field_type('created_by', 'hidden', $expertid);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function mains_tests() {
        $crud = new grocery_CRUD();
        $crud->set_table('mains_tests');
        $crud->set_theme('datatables');
        $crud->columns('test_id', 'questions', 'subject_id', 'test_name', 'description', 'question_paper', 'answer_format', 'answer_format_hindi', 'scheduled', 'took_place', 'status', 'created_by');
        $crud->fields('test_id', 'subject_id', 'test_name', 'description', 'question_paper', 'answer_format', 'answer_format_hindi', 'question_paper_swf', 'question_paper_copy', 'answer_format_swf', 'scheduled', 'took_place', 'status', 'created_by');
        // $crud->set_relation('copy_of','mains_tests','{test_id}-{test_name}');
        //$crud->set_relation('member_id','members','{member_id}- {lastname} {firstname}');
        $crud->set_relation('status', 'test_status', '{status_id}-{status_name}');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        //$crud->set_relation_n_n( 'questions', 'mains_test_questions', 'question', 'test_id','qid' ,'question_text');

        $crud->add_action('Print Paper', '../../images/print1.jpg', '', '', array($this, 'print_paper_callback'));


        $crud->add_action('add to dispatch', '../../images/dispatch.jpg', 'dispatch/get_members', '', '');
        //$crud->set_field_upload('discussion_audio','assets/uploads/files/discussion');
        $crud->set_field_upload('question_paper', 'assets/uploads/files/question_paper');
        $crud->set_field_upload('answer_format_hindi', 'assets/uploads/files/answer_format');
        $crud->set_field_upload('answer_format', 'assets/uploads/files/answer_format');
        $crud->set_field_upload('question_paper_swf', 'assets/uploads/files/question_paper');
        $crud->set_field_upload('question_paper_copy', 'assets/uploads/files/question_paper');
        $crud->set_field_upload('answer_format_swf', 'assets/uploads/files/answer_format');
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function print_paper_callback($primary_key, $row) {
        return site_url('admin/print_test/') . '/' . $row->test_id . '/1';
    }

    function mains_tests_packages() {
        $crud = new grocery_CRUD();
        $crud->set_table('mains_tests_packages');
        $crud->set_theme('datatables');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        //$crud->set_relation('member_id','members','{member_id}- {lastname} {firstname}');
        $crud->set_relation('status', 'package_status', '{status_id}-{status_name}');



        $crud->set_field_upload('pdf', 'assets/uploads/files/schedules');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function mains_test_questions($test_id = 0) {

        $crud = new grocery_CRUD();
        $crud->set_table('mains_test_questions');
        $crud->set_theme('datatables');
        $crud->add_fields('qid', 'test_id', 'q_no_on_paper');
        $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', null, 'test_name asc');
        $crud->set_relation('qid', 'question', '{qid}-{question_text}', null, 'qid desc');

        $crud->order_by('q_no_on_paper', 'asc');
        if ($test_id != 0)
            $crud->where('mains_test_questions.test_id', $test_id);
        // $crud->unset_delete();
        //$crud->unset_edit();
        $crud->add_action('Add Answer Format', '../../images/anf.jpg', '', '', array($this, 'add_answer_format'));
        $crud->callback_before_insert(array($this, 'include_expert_id'));
        $crud->set_relation('created_by', 'users', '{id}-{username}');
        $expertid = $this->tank_auth->get_user_id();
        $crud->field_type('created_by', 'hidden', $expertid);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function add_answer_format($primary_key, $row) {
        return site_url('admin/question/edit') . '/' . $row->qid;
    }

    function include_expert_id($post_array) {
        $post_array['created_by'] = $this->tank_auth->get_user_id();
        return $post_array;
    }

    function mains_test_score($test_id = 0, $member_id = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('mains_test_score');
        $crud->set_theme('datatables');
        //$crud->set_relation('package_id','mains_tests_packages','{package_id}-{package_name}');
        $crud->set_relation('qid', 'question', '{qid}-{question_text}', null, 'qid desc');
        $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', null, 'test_id desc');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');
        $crud->set_field_upload('answer_script', 'assets/uploads/files/answer_script');

        if ($test_id)
            $crud->where('mains_test_score.test_id', $test_id);
        if ($member_id)
            $crud->where('mains_test_score.member_id', $member_id);

        $crud->add_action('Reset Score', '../../images/anf.jpg', '', '', array($this, 'action_reset_mains_score'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function action_reset_mains_score($primary_key, $row) {
        return site_url('admin/reset_mains_score') . '/' . $row->test_id . '/' . $row->member_id;
    }

    function reset_mains_score($test_id = 0, $member_id = 0, $sure = 0) {
        $output = "....ne...";
        if ($sure == 0) {

            $yesurl = site_url('admin/reset_mains_score') . '/' . $test_id . '/' . $member_id . "/1";
            $nourl = site_url('admin/mains_test_score') . '/' . $test_id . '/' . $member_id;
            $output = "are you sure you want to delete score of Student <b>$member_id</b> for test <b>$test_id</b>.............<a href=$yesurl><b>Yes</b></a>......<a href=$nourl><b>No</b></a>";
        } else if ($sure == 1) {
            $query = $this->db->query("DELETE FROM mains_test_score WHERE test_id=$test_id AND member_id=$member_id");
            if ($query)
                $output = "Score of Student <b>$member_id</b> for test <b>$test_id</b> deleted";
            else
                $output = "Error occurred while deleting Score of Student <b>$member_id</b> for test <b>$test_id</b>";
        }

        $data['output'] = $output;
        $this->load->view('link.php', $data);
        //return $query->result();
    }

    /////////////interview packages
    function interview_packages() {

        $crud = new grocery_CRUD();
        $crud->set_table('interview_packages');
        $crud->set_theme('datatables');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        $crud->set_relation('status', 'package_status', '{status_id}-{status_name}');
        $crud->set_field_upload('pdf', 'assets/uploads/files/schedules');


        $expertid = $this->tank_auth->get_user_id();
        $crud->field_type('created_by', 'hidden', $expertid);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///////////////////////////////////////study_material_packages
    function material_package() {

        $crud = new grocery_CRUD();
        $crud->set_table('material_package');
        $crud->set_theme('datatables');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        $crud->set_relation('status', 'package_status', '{status_id}-{status_name}');
        $crud->set_field_upload('pdf', 'assets/uploads/files/schedules');


        $expertid = $this->tank_auth->get_user_id();
        $crud->field_type('created_by', 'hidden', $expertid);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function material_package_student($member_id = 0) {

        $crud = new grocery_CRUD();
        $crud->set_table('material_package_student');
        $crud->set_theme('datatables');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');
        $crud->set_relation('package_id', 'material_package', '{package_name}');
        if ($member_id != 0)
            $crud->where('material_package_student.member_id =', $member_id);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function material_package_material() {

        $crud = new grocery_CRUD();
        $crud->set_table('material_package_material');
        $crud->set_theme('datatables');
        $crud->set_relation('material_id', 'study_material', '{material_id}-  {material_name} ', null, 'material_id desc');
        $crud->set_relation('package_id', 'material_package', '{package_id}-  {package_name}', null, 'package_id desc');
        $crud->callback_before_insert(array($this, 'plan_dispatch'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function plan_dispatch($post_array) {
        $q = "select * from study_material where form='hard_copy_also' and material_id=" . $post_array['material_id'];
        $query = $this->db->query($q);
        if ($query->num_rows() > 0) {
            $q1 = "INSERT IGNORE INTO study_material_student(member_id,material_id,status) (SELECT member_id,'" . $post_array['material_id'] . "','planned' FROM material_package_student WHERE package_id=" . $post_array['package_id'] . ")";
            $result1 = mysql_query($q1);
        }
    }

    ////////////////////////////////////////////////////////////////////////

    function package_tests($subject = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('package_tests');
        $crud->set_theme('datatables');
        $crud->set_relation('package_id', 'mains_tests_packages', '{package_id}-{package_name}', array('status' => '1'), 'package_id desc');
        if ($subject != 0)
            $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', array('subject_id' => $subject), 'test_id desc');
        else
            $crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}', null, 'test_id desc');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function packages_students($member_id = 0) {

        $crud = new grocery_CRUD();
        $crud->set_table('packages_students');
        $crud->set_theme('datatables');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}', null, 'member_id desc');
        $crud->set_relation('package_id', 'mains_tests_packages', '{package_name}', array('status' => '1'), 'package_id desc');
        //  $crud->callback_after_insert(array($this, 'add_test_for_user'));
        if ($member_id != 0)
            $crud->where('packages_students.member_id =', $member_id);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    //////////////////////////////////

    function sms_email($test_id = 0, $centre = 0, $status = 0, $start_date = 0, $end_date = 0) {
        $this->load->library('table');
        $email = "";
        $mobile = "";
        $this->db->select('firstname,lastname, appeared_on,centre,ps.status, login, telephone_local');
        $this->db->from('personal_schedule ps');
        $this->db->join('members m', 'm.member_id = ps.member_id', 'left');
        if ($test_id)
            $this->db->where('test_id', $test_id);
        if ($centre)
            $this->db->where('centre', $centre);
        if ($status)
            $this->db->where('ps.status', $status);

        if ($start_date != 0)
            $this->db->where('ps.appeared_on >=', $start_date);
        if ($end_date != 0)
            $this->db->where('ps.appeared_on >=', $end_date);

        $query = $this->db->get();

        $output = $this->table->generate($query);

        foreach ($query->result() as $row) {

            $email.=$row->login . ",";
            if ($row->telephone_local)
                $mobile.=$row->telephone_local . ",";
        }

        echo $email . "<br><br>" . $mobile . "<br><br>" . $output;
    }

    function edit_personal_schedule() {
        $output = "<h1>EDIT A COPY</h1>";
        if (isset($_POST['member_id'])) {
            $member_id = $_POST['member_id'];
            $test_id = $_POST['test_id'];
            $course = $_POST['course'];
            $table = "";
            $q = "";
            switch ($course) {
                case "pt":
                    $q = "select id as schedule_id from pt_personal_schedule where member_id='$member_id' and test_id='$test_id'";
                    $table = "pt_personal_schedule";
                    break;
                case "mains":
                    $q = "select schedule_id from personal_schedule where member_id='$member_id' and test_id='$test_id'";
                    $table = "personal_schedule";
                    break;
                case "material":
                    echo "<meta http-equiv='refresh' content='1," . site_url('admin/study_material_student_check/' . $member_id . "/" . $test_id) . "' />";
                    die();
                    break;
            }

            //echo $q;
            $query = $this->db->query($q);
            $row = $query->result_array();
            //var_dump($row);
            if (isset($row[0]['schedule_id'])) {
                $schedule_id = $row[0]['schedule_id'];
                if (isset($_REQUEST['at']))
                    echo "<meta http-equiv='refresh' content='1," . site_url('admin/' . $table . '/edit/' . $schedule_id . '?at=yes') . "' />";
                else
                    echo "<meta http-equiv='refresh' content='1," . site_url('admin/' . $table . '/edit/' . $schedule_id) . "' />";
            } else
                echo "<h1>No Schedule for this test in $course</h1>";
        }
        else {
            $attributes = array('name' => 'myform', 'target' => '_blank');
            $output.=form_open('admin/edit_personal_schedule', $attributes);
            $output.="Reg. ID " . form_input('member_id', '');
            $output.="TEST ID " . form_input('test_id', '');
            $output.=form_submit('mysubmit', 'Edit');
            $output.=form_close("");
            $output;
            echo $output;
            // $this->load->view('admin.php',$output);
        }
    }

    ///////////////////////////////////////////// Profile
    /////////////////////////////////PERSONAL SCHEDULE//////////////////////////
    function personal_schedule($schedule_id = 5000, $memberid = '0', $test_id = '0', $chk = '0', $unchk = '0') {

        $crud = new grocery_CRUD();
        $crud->set_table('personal_schedule');
        //$crud->set_theme('datatables');
        //$crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');
        //$crud->set_relation('test_id', 'mains_tests', '{test_id}-{test_name}');
        $crud->set_relation('expert_id', 'users', '{username}', null, 'username');
        $crud->set_relation('reviewer', 'users', '{username}', null, 'username');

        $crud->callback_after_insert(array($this, 'add_test_for_user'));
        $crud->set_field_upload('answer_script_unchecked', 'assets/uploads/files/answer_script/unchecked');
        $crud->set_field_upload('answer_script_checked', 'assets/uploads/files/answer_script/checked');
        $crud->set_field_upload('answer_script_rechecked1', 'assets/uploads/files/answer_script/checked');
        $crud->set_field_upload('answer_script_rechecked2', 'assets/uploads/files/answer_script/checked');
        // $crud->columns('schedule_id', 'test_id', 'member_id','time','answer_script_unchecked','answer_script_checked','marks_obtained','expert_id','receiving_date','receiving_track_no','go_to_expert', 'receiving_from_expert','dispatch_date','dispatch_track_no' );
        $crud->columns('schedule_id', 'test_id', 'status', 'receiving_date', 'receiving_from_expert', 'member_id', 'answer_script_unchecked', 'answer_script_checked', 'marks_obtained');

        $crud->add_action('Cover', '', '', '', array($this, 'print_cover_cb'));
        $crud->add_action('add score', '../../images/add2.jpg', '', '', array($this, 'marks_callback'));
        $crud->callback_before_update(array($this, 'send_mail_to_expert'));

        $crud->add_action('add answer script to dispatch', '../../images/answer.jpg', '', '', array($this, 'answer_callback'));
        $crud->add_action('add test to dispatch', '../../images/test.jpg', '', '', array($this, 'test_callback'));
        $crud->unset_delete();
        if ($schedule_id != 0)
            $crud->where('personal_schedule.schedule_id >=', $schedule_id);
        if ($memberid != 0)
            $crud->where('personal_schedule.member_id', $memberid);
        if ($test_id != 0)
            $crud->where('personal_schedule.test_id', $test_id);
        if ($chk != 0)
            $crud->where('personal_schedule.answer_script_checked !=', '');
        //if($unchk!=0)
        $crud->where('personal_schedule.answer_script_unchecked !=', '');

        $crud->order_by('time, personal_schedule.member_id ', 'asc');

        if (isset($_REQUEST['at'])) {
            $crud->fields('member_id', 'test_id', 'appeared_on', 'appeared_at', 'receiving_from_expert');
            $crud->field_type('member_id', 'readonly');
            $crud->field_type('test_id', 'readonly');
        }
        //$crud->order_by('member_id','asc');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///email to expert on copy assignment
    function send_mail_to_expert($post_array, $primary_key) {
        $query = $this->db->query("INSERT INTO `bc_personal_schedule`(  `schedule_id`, `test_id`, `status`, `member_id`, `time`, `appeared_on`, `appeared_at`, `answer_script_unchecked`, `answer_script_checked`, `marks_obtained`, `answer_script_rechecked1`, `answer_script_rechecked2`, `expert_id`, `receiving_date`, `unchecked_upload_date`, `receiving_track_no`, `go_to_expert`, `deadline`, `receiving_from_expert`, `checked_upload_date`, `dispatch_date`, `dispatch_track_no`, `comments` ) (select  `schedule_id`, `test_id`, `status`, `member_id`, `time`, `appeared_on`, `appeared_at`, `answer_script_unchecked`, `answer_script_checked`, `marks_obtained`, `answer_script_rechecked1`, `answer_script_rechecked2`, `expert_id`, `receiving_date`, `unchecked_upload_date`, `receiving_track_no`, `go_to_expert`, `deadline`, `receiving_from_expert`, `checked_upload_date`, `dispatch_date`, `dispatch_track_no`, `comments` from personal_schedule where schedule_id=$primary_key)");
        /* if($post_array['expert_id']!='')
          {
          $this->load->library('email');
          $this->load->model('admin_model');

          //$rt=$this->admin_model->get_email('13');
          $rt=$this->admin_model->get_email($post_array['expert_id']);
          $expert_email=$rt['email'];
          $expert_name=$rt['username'];

          $this->email->from('admin@visionias.in', 'Vision IAS ADMIN');
          $this->email->to($expert_email);
          $this->email->bcc('ashvani.kumar@gmail.com');
          $this->email->subject('New Answer Script Assigned for Evaluation');
          $msg="Dear ".$expert_name.",<br> ";
          $msg.="<br> Your have been assigned New Answer Script  for Evaluation. Please logon to visionias.in/admin to download the copy. Once you finish the evaluation upload the checked answer script on the website. If your evaluation remarks are on seperate file then make that file a pdf and upload remarks only.";
          $msg.="<br>If you have any problem please mail to admin@visionias.in ";
          $msg.="<br>Regard</br> Vision IAS admin";

          $this->email->message($msg);
          $this->email->send();
          }
         */

        if ($post_array['reviewer'] != '') {
            $this->load->library('email');
            $this->load->model('admin_model');


            $rt = $this->admin_model->get_email($post_array['reviewer']);
            $expert_email = $rt['email'];
            $expert_name = $rt['username'];

            $this->email->from('sneha@visionias.in', 'Copy Management Team-VISION IAS');
            $this->email->to($expert_email);
            $this->email->cc('sneha@visionias.in,info@visionias.in,admin@visionias.in');
            $this->email->subject('Review: New Answer Script Assigned');
            $msg = "Dear " . $expert_name . ",<br> ";
            $msg.="<br> Your have been assigned New Answer Script for Review." . $post_array['message_to_reviewer'];
            if ($post_array['answer_script_unchecked'] != '')
                $this->email->attach("/home/vision89/public_html/admin/assets/uploads/files/answer_script/unchecked/" . $post_array['answer_script_unchecked']);
            if ($post_array['answer_script_checked'] != '')
                $this->email->attach("/home/vision89/public_html/admin/assets/uploads/files/answer_script/checked/" . $post_array['answer_script_checked']);
            $msg.="<br>If you have any problem please mail to sneha@visionias.in";
            $msg.="<br>Regard</br> Vision IAS Copy Management Team";

            $this->email->message($msg);
            $this->email->send();
        }
        return $post_array;
    }

    function pt_personal_schedule() {

        $crud = new grocery_CRUD();
        $crud->set_table('pt_personal_schedule');
        $crud->set_theme('datatables');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');
        $crud->set_relation('test_id', 'pt_tests', '{test_id}-{test_name}');

        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();



        if (isset($_REQUEST['at'])) {
            $crud->fields('member_id', 'test_id', 'appeared_on', 'appeared_at');
            $crud->field_type('member_id', 'readonly');
            $crud->field_type('test_id', 'readonly');
        }
        //$crud->order_by('member_id','asc');

        $output = $crud->render();
        $this->_example_output($output);
    }

    function dispatch_view($member_id = 448) {

        $crud = new grocery_CRUD();
        $crud->set_table('study_material_student');
        $crud->set_theme('datatables');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}');

        if ($member_id)
            $crud->where('study_material_student.member_id', $member_id);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function print_cover_cb($primary_key, $row) {
        return site_url('admin/print_cover/') . '/' . $row->schedule_id;
    }

    function print_cover($schedule_id) {

        $q = "SELECT * from personal_schedule ps left join members m on ps.member_id=m.member_id left join mains_tests mt on mt.test_id=ps.test_id where ps.schedule_id=$schedule_id";
        $query = $this->db->query($q);
        $output = "";

        foreach ($query->result() as $row) {
            $output['id'] = $row->schedule_id;
            $output['member_id'] = $row->member_id;
            $output['firstname'] = $row->firstname;
            $output['lastname'] = $row->lastname;
            $output['test_id'] = $row->test_id;
            $output['subject_id'] = $row->subject_id;
            $output['mode'] = $row->mode;
            $output['centre'] = $row->centre;
            $output['telephone_local'] = $row->telephone_local;
        }

        $data['output'] = $output;
        $this->load->view('cover.php', $data);
    }

    function print_attendance_sheet_cb($primary_key, $row) {
        return site_url('admin/print_attendance_sheet/') . '/' . $row->member_id . '/' . $row->package_id;
    }

    function print_attendance_sheet($member_id = 500, $module_id_from = 0, $module_id_to = 100000) {

        $q = "select pt.package_id,pt.test_id,pt.local_test_name,pt.scheduled from packages_students pks
right join package_tests pt on pt.package_id=pks.package_id
left join members m on pks.member_id=m.member_id
where pks.member_id= $member_id and (pks.package_id>=$module_id_from and pks.package_id<=$module_id_to)

UNION  ALL

select ptpt.package_id,ptpt.test_id,ptpt.local_test_name,ptpt.local_scheduled as scheduled from pt_packages_students ptpks
right join pt_package_tests ptpt on ptpt.package_id=ptpks.package_id
left join members ptm on ptpks.member_id=ptm.member_id
where ptpks.member_id= $member_id  and (ptpks.package_id>=$module_id_from and ptpks.package_id<=$module_id_to)


order by scheduled ;


 ";
        //package_id desc,   LENGTH(local_test_name) asc, local_test_name asc
        $query = $this->db->query($q);
        $student = $this->db->query("select * from members where member_id=$member_id");


        $data['tests'] = $query;
        $data['student'] = $student;
        $this->load->view('attendance_sheet.php', $data);
    }

    ///////////////////////// Class profile
    function class_profile() {
        $output = "<h1>View Profile</h1>";

        if (!isset($_POST['class_id'])) {
            $attributes = array('name' => 'myform', 'target' => '_blank');
            $output.=form_open('admin/class_profile', $attributes);
            $output.="Classroom ID " . form_input('class_id', '');

            $output.=form_submit('mysubmit', 'Open');
            $output.=form_close("");
            $output;
            echo $output;
            // $this->load->view('admin.php',$output);
        } else {

            $this->load->library('table');
            $class_id = $_POST['class_id'];
            $complain = $this->db->query("select * from complain where member_id=$member_id");

            //$this->load->view('student_profile.php',$data);
        }
    }

    /////////////////////////Send SMS////////////////////////
    function sms() {
        $crud = new grocery_CRUD();
        $crud->set_table('smsreport');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}', null, 'member_id desc');
        //$crud->set_relation('package_id','mains_tests_packages','{package_name}',array('status' => '1'),'package_id desc');
        //  $crud->callback_after_insert(array($this, 'add_test_for_user'));
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function send_sms() {
        $output = "";
        $mobile = $_GET['mobile'];
        if (!isset($_GET['message'])) {
            $attributes = array('name' => 'myform', 'target' => '_blank', 'method' => 'get');
            $output.=form_open('admin/send_sms', $attributes);
            $output.="<br>Mobile " . form_input('mobile', $mobile);
            $output.="<br>Message " . form_input('message', '');

            $output.=form_submit('mysubmit', 'Send SMS');
            $output.=form_close("");
            $output;
            echo $output;
            // $this->load->view('admin.php',$output);
        } else {
            $smsurl = "http://bulksms.mysmsmantra.com:8080/WebSMS/SMSAPI.jsp?username=visionias&password=vasu1981&sendername=vision&mobileno=" . $_GET['mobile'] . "&message=" . urlencode($_GET['message']);

            $this->load->library('curl');

            //echo $smsurl;
            // echo $this->curl->simple_get('http://bulksms.mysmsmantra.com:8080/WebSMS/SMSAPI.jsp?username=visionias&password=vasu1981&sendername=vision&mobileno=919911149748&message=Dear%20Student,Classroom%20discussion%20for%20Test~has%20been%20scheduled%20on~at~at~Please%20check%20your%20email%20for%20more%20details.');
            echo $this->curl->simple_get($smsurl);
        }
    }

    function mains_module_profile() {

        $output = "<h1>View Profile</h1>";

        if (!isset($_POST['module_id'])) {
            $attributes = array('name' => 'myform', 'target' => '_blank');
            $output.=form_open('admin/mains_module_profile', $attributes);
            $output.="Module ID " . form_input('module_id', '');

            $output.=form_submit('mysubmit', 'View');
            $output.=form_close("");
            $output;

            // $this->load->view('admin.php',$output);
        } else {
            $this->load->library('table');
            $module_id = $_POST['module_id'];
            $q = "SELECT pt.*, count(pt.test_id) FROM package_tests pt
              LEFT JOIN 
              LEFT JOIN personal_schedule ps on ps.test_id=pt.test_id
              WHERE package_id= $module_id
              GROUP BY pt.test_id
          ";
            $query = $this->db->query($q);
            $output = $this->table->generate($query);
        }

        echo $output;
    }

    function student_profile($module_id_from = 0, $module_id_to = 100000) {
        $output = "<h1>View Profile</h1>";

        if (!isset($_REQUEST['member_id'])) {
            $attributes = array('name' => 'myform', 'target' => '_blank');
            $output.=form_open('admin/student_profile', $attributes);
            $output.="Reg. ID or Email" . form_input('member_id', '');

            $output.=form_submit('mysubmit', 'Open');
            $output.=form_close("");
            $output;
            echo $output;
            // $this->load->view('admin.php',$output);
        } else {

            $this->load->library('table');
            $member_id1 = $_POST['member_id'];
            $query = $this->db->query("SELECT member_id from members where member_id='$member_id1' or login='$member_id1'");
            $id = $query->result();
            $member_id = $id[0]->member_id;


            $q = "select pks.status, pt.package_id,pt.test_id,pt.local_test_name,pt.scheduled,ps.schedule_id,ps.answer_script_checked,ps.expert_id, ps.marks_obtained,ps.appeared_on, ps.go_to_expert, ps.receiving_from_expert,ps.dispatch_date from packages_students pks
right join package_tests pt on pt.package_id=pks.package_id
left join members m on pks.member_id=m.member_id
left join personal_schedule ps on ps.member_id=m.member_id AND ps.test_id=pt.test_id

where pks.member_id= $member_id and (pks.package_id>=$module_id_from and pks.package_id<=$module_id_to) 


";


            //  $q="select * from personal_schedule ps where ps.member_id=$member_id ";
            //package_id desc,   LENGTH(local_test_name) asc, local_test_name asc
            $mains = $this->db->query($q);

            $q = "select ptpks.status, round(sum(score),2) as score,s.time, ptpt.package_id,ptpt.test_id,ptpt.local_test_name,ptpt.local_scheduled as scheduled from pt_packages_students ptpks
right join pt_package_tests ptpt on ptpt.package_id=ptpks.package_id
left join members ptm on ptpks.member_id=ptm.member_id
left join pt_test_score s on s.member_id=ptm.member_id and s.test_id=ptpt.test_id
where ptpks.member_id= $member_id  and (ptpks.package_id>=$module_id_from and ptpks.package_id<=$module_id_to)
  group by   test_id

order by package_id, scheduled 

;


";
            $pt = $this->db->query($q);

            $student = $this->db->query("select m.* , m.country_pr as country, m.state_pr as state, m.city_pr as city  from members m where member_id=$member_id");
            $complain = $this->db->query("select * from complain where member_id=$member_id");

            $sms = $this->db->query("select sms.* from smsreport sms left join members m on m.telephone_local=sms.Number where m.member_id=$member_id order by id desc");
            $pgtoav = $this->db->query("select pgtoav.merchantTxnID as billno, pgtoav.txnEnd as date, avtopg.Amount as avamount, pgtoav.amount as pgamount, pgtoav.message, pgtoav.gateway  from pgtoav left join avtopg on  avtopg.merchantTxnId=pgtoav.merchantTxnID       where pgtoav.member_id=$member_id and respcode=0 order by billno desc");
            $bills = $this->db->query("select id as billno, date from bills where member_id=$member_id");
            $material = $this->db->query("select ps.status, ps.package_id, m.material_id, m.material_name,sms.mode from material_package_student ps
                                    left join material_package_material pm on ps.package_id=pm.package_id
                                    left join study_material m on m.material_id=pm.material_id
                                    left join study_material_student sms on sms.member_id=ps.member_id  and sms.material_id= pm.material_id
                                    where ps.member_id=$member_id");
            $data['tests'] = $mains;
            $data['pt_tests'] = $pt;
            $data['material'] = $material;
            $data['student'] = $student;
            $data['complain'] = $complain;
            $data['sms'] = $sms;
            $data['pgtoav'] = $pgtoav;
            $data['bills'] = $bills;
            $this->load->view('student_profile.php', $data);
        }
    }

    function marks_callback($primary_key, $row) {
        return site_url('admin/add_marks/') . '/' . $row->test_id . '/' . $row->member_id . '/' . $row->schedule_id;
    }

    /* call back function for adding answer script to dispatch */

    function answer_callback($schedule_id, $row) {
        return site_url('dispatch/answer_dispatch/') . '/' . $schedule_id . '/' . $row->member_id;
    }

    /* callback function for adding test papers to dispatch */

    function test_callback($schedule_id, $row) {
        return site_url('dispatch/test_to_dispatch/') . '/' . $row->test_id . '/' . $row->member_id;
    }

    /*
      Function to print answer format and test paper
     */

    function print_test($test_id, $format = 0) {
        $this->load->model('admin_model');
        $rt = $this->admin_model->get_questions($test_id);
        $output = "";
        // var_dump($rt);
        if ($format == 0) {
            foreach ($rt as $row) {
                $output.="<b><font color=red>Q." . $row->q_no_on_paper . $row->question_text . "</font></b><br>";
                $output.="Answer.<br>" . $row->answer_format . "<br>";
            }
        } else if ($format == 1) {
            $mmp = 0;
            foreach ($rt as $row) {
                $nwords = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve", "thirteen", "fourteen", "fifteen", "sixteen", "seventeen", "eighteen", "nineteen", "twenty");
                $countind = 0;
                $pieces = explode("(", $row->q_no_on_paper);
                if ($pieces[1] == 'a)') {

                    $attempt = "";
                    $words = "";
                    $totals = "";
                    if ($row->attempt != 0) {
                        $attempt = " any " . $nwords[$row->attempt];
                        $totals = "$row->attempt x $row->mm =" . ($row->attempt * $row->mm);

                        $mmp+=($row->attempt * $row->mm);
                        $countind = 0;
                    } else {
                        $attempt = " the following";
                        $countind = 1;
                    }
                    //words
                    if ($row->words)
                        $words = "in about " . $row->words . " words each ";


                    $output.="<br><b>$pieces[0] . Answer  <i>$attempt</i>  $words   &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp             $totals</b>";
                }

                $output.="<br><b>(" . $pieces[1] . "</b> " . $row->question_text;
                if ($row->attempt == 0 && $row->mm != 0) {
                    $output.="   " . $row->mm . " ";
                    $mmp+= $row->mm;
                }
            }
            $output.="<br> Total. $mmp";
        }
        echo $output;
    }

    /* action function of marks call back function */

    function add_marks($test_id, $member_id, $schedule_id, $un = 0) {

        $this->load->model('add_model');
        $this->load->helper('path');

        $result = $this->add_model->add($test_id, $member_id);
        $output = "";
        if ($result) {
            $output.="<table>";
            $this->load->helper('form');
            $hidden = array('test_id' => $test_id, 'member_id' => $member_id);
            $attributes = array('id' => 'addform');
            $output.=form_open('admin/submit_marks_form', $attributes, $hidden);
            $score = '';
            if (isset($row->score))
                $score = $row->score;
            foreach ($result as $row) {
                $data = array(
                    'name' => $row->qid,
                    'value' => $score,
                    'class' => 'score',
                    'maxlength' => '10',
                    'size' => '10',
                );
                $output.="<tr><td>" . $row->q_no_on_paper . " " . form_input($data) . " " . $row->type . "[" . substr($row->question_text, 0, 20) . "]</td></tr>";
            }

            $output.="</table>";

            $output.=form_button('sum', 'sum');
            $output.=form_submit('', 'submit');
            $output.=form_close();


            $data['output'] = $output;

            $data['schedule_id'] = $schedule_id;
            $data['un'] = $un;
			$this->review_marks($test_id, $member_id, $schedule_id);
            $this->load->view('asview.php', $data);
        }

        $output = "Either questions are not added or marks already added";
        $data['output'] = $output;
        //$this->load->view('link.php',$data);
    }

    /* this function submits the marks added by add marks form */

    function submit_marks_form() {
        $this->load->model('add_model');
        $result = $this->input->post();
        $ret = $this->add_model->submit_marks_form($result);
        $output = "<hr/><p align=center><b><font color=blue>";
        if ($ret) {
            $output = "<h1>Marks added successfully.</h1>";
        } else {
            $output = "error occurred";
        }
        $data['output'] = $output;
        $this->load->view('link.php', $data);
    }

    function testf($id) {
        if ($id != '') {
            $this->load->library('email');
            $this->load->model('admin_model');

            $rt = $this->admin_model->get_email($id);
            $expert_email = $rt['email'];
            $expert_name = $rt['username'];

            echo $expert_email . "<br>" . $expert_name;
            $this->email->from('sneha@visionias.in', 'Vision IAS Copy Management Team');
            $this->email->to($expert_email);
            $this->email->bcc('sneha@visionias.in,info@visionias.in,admin@visionias.in');
            $this->email->subject('New Answer Script Assigned for Evaluation');
            $msg = "Dear " . $expert_name . ",<br> ";
            $msg.="<br> Your have been assigned New Answer Script Assigned for Evaluation. Please logon to visionias.in/admin to download the copy. Once you finish the evaluation upload the checked answer script on the website. If your evaluation remarks are on separate file then make that file a pdf and upload remarks only.";
            $msg.="<br>If you have any problem please mail to sneha@visionias.in ";
            $msg.="<br>Regard</br> Vision IAS Copy Management Team";

            $this->email->message($msg);
            $this->email->send();
        }
    }
    /////
	
	    /////////////////// Function
    function app_function() {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
        $crud->unset_print();
          $crud->unset_export();
          // //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }
	    /////////////////// Access
    function app_access() {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
		$crud->set_table('app_access');
		$crud->set_relation('function_id', 'app_function', '{display_name}', null, 'name asc');
		//$crud->set_relation_n_n('functions', 'app_access', 'app_function', 'function_id', 'expert_id', 'name');
		//$crud->set_relation_n_n('test_codes', 'test_expert', 'mains_tests', 'expert', 'test', 'test_id');
		$crud->set_relation('expert_id', 'users', '{id}-{username}');
	$crud->unset_print();
          $crud->unset_export();
           ////$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }
	
	////linked_function
	
	function app_link_function() {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
		$crud->set_relation('function_id', 'app_function', '{name}', null, 'name asc');
		$crud->set_relation('link_function_id', 'app_function', '{name}', null, 'name asc');
		$crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }
    /////////////////////////////////////////////////////////////////////
    function question($mode = 0, $expert = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('question');
        $crud->columns('qid', 'question_text', 'review_comments', 'motive', 'question_status', 'created_by', 'time');
        $crud->set_relation('created_by', 'users', '{id}-{username}', null, 'id asc');

        $crud->set_relation('tid', 'topics', '{tid}-{topic_name}');
        //$crud->set_relation('type','type','marks');
        $crud->set_relation('difficulty', 'difficulty', '{difficulty_id}- {difficulty_name}');
        $crud->set_relation('nature', 'nature', '{nature_id}-{nature_name}');
        $crud->set_relation('approachability', 'approachability', '{approachability_id}-{approachability_name}');
        $crud->set_relation('created_by', 'users', '{id}-{username}');
        $crud->unset_texteditor('question_text');
        if ($expert != 0)
            $crud->where('question.created_by', $expert);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    /////////////////// SECTION
    function section($subject = 0) {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
        $crud->set_table('section');
        $crud->columns('section_id', 'section_name', 'subject_id');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}', null, 'subject_id');

        if ($subject != 0)
            $crud->where('section.subject_id', $subject);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///////////////////SUB SECTION
    function sub_section($section = 0) {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
        $crud->set_table('sub_section');
        $crud->columns('sub_section_id', 'sub_section_name', 'section_id');
        $crud->set_relation('section_id', 'section', '{section_id}-{section_name}', null, 'section_id');

        if ($section != 0)
            $crud->where('sub_section.section_id', $section);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///////////////////Topic
    function topics($sub_section = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('topics');
        $crud->set_theme('datatables');
        $crud->columns('tid', 'tod', 'topic_name', 'sub_section');
        $crud->set_relation('sub_section', 'sub_section', '{sub_section_id}-{sub_section_name}', null, 'sub_section_id');

        if ($sub_section != 0)
            $crud->where('topics.sub_section', $sub_section);
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    ///////////////////////////////////////////////////////////////////////////////

       function avtopg() {
       $crud = new grocery_CRUD();
$crud->set_theme('datatables');
$datemonth1=date('Y-m-d', strtotime("-3 days"));
//echo "'$datemonth1'";
$crud->set_table('avtopg');
$crud->set_relation('member_id','members','member_id');
$crud->where('TxnStartTS >=',$datemonth1);
$crud->where('status !=','1');
$crud->set_primary_key('member_id','avtopg');

$crud->unset_print();
$crud->unset_export();
//$crud->unset_add();
// $crud->unset_edit();
$crud->unset_delete();
$crud->add_action('Activate', '', 'admin/activate','ui-icon-plus');

$output = $crud->render();
$this->_example_output($output);
    }
	function activate($member_id)
	{
		$q="update members set status='1' where member_id=$member_id";
		$query = $this->db->query($q);
		echo ("<SCRIPT LANGUAGE='JavaScript'>top.location.href='/admin/index.php/admin/avtopg';</SCRIPT>");
				exit();
				
		//return site_url('admin/status').'/'.$row->member_id;	
	}
	function status($member_id)
	{
				$q="update members set status='1' where member_id=$member_id";
				//$query = $this->db->query($q);
				echo ("<SCRIPT LANGUAGE='JavaScript'>top.location.href='/admin/index.php/admin/avtopg';</SCRIPT>");
				exit();
				
				
	}
  function pgtoav() {
        $crud = new grocery_CRUD();
        $crud->set_theme('datatables');
		$datemonth1=date('Y-m-d', strtotime("-3 days"));
        $crud->set_table('pgtoav');
		$crud->set_relation('member_id','members','member_id');
		$crud->where('TxnEnd >=',$datemonth1);
        $crud->columns('merchantTxnID', 'gateway');
		$crud->where('status !=','1');
		$crud->set_primary_key('member_id','pgtoav');
        $crud->set_field_upload('message', 'assets/uploads/files/registration');
        $crud->display_as('message', 'Transaction Slip or Message');
		$crud->add_action('Activate', '', 'admin/activatepgtoav','ui-icon-plus');
       $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }
function activatepgtoav($member_id)
	{
		$q="update members set status='1' where member_id=$member_id";
		$query = $this->db->query($q);
		
		echo ("<SCRIPT LANGUAGE='JavaScript'>top.location.href='/admin/index.php/admin/pgtoav';</SCRIPT>");
				exit();
				
		//return site_url('admin/status').'/'.$row->member_id;	
	}

    function study_material() {
        $crud = new grocery_CRUD();

        $crud->set_table('study_material');
        $crud->set_theme('datatables');
        $crud->columns('material_id', 'subject_id', 'material_name', 'material_pdf', 'material_swf', 'track_no', 'form', 'status');
        $crud->set_field_upload('material_pdf', 'assets/uploads/files/material_pdf');
        $crud->set_field_upload('material_swf', 'assets/uploads/files/material_swf');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        $crud->set_relation('status', 'material_status', '{material_status_id}-{material_status_name}');
        // $crud->add_action('add to dispatch','../../images/dispatch.jpg','dispatch/select_package','','');
        $crud->where('status', '1');

        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();

        $this->_example_output($output);
    }

    function study_material_student_check($member_id = 0, $material_id = 0) {
        $expertid = $this->tank_auth->get_user_id();
        if ($material_id == 0)
            redirect('dispatchnew/print_address_sheet/' . $member_id);


        $q = "SELECT * from material_package_student s 
            left join material_package_material m on m.package_id=s.package_id 
            left join members st on st.member_id=s.member_id
            where m.material_id=$material_id and s.member_id=$member_id and st.status=1";
        $query = $this->db->query($q);
        if ($query->num_rows() == 0)
            die("<h1>not subscribed or deactivated</h1>");
        $q = "SELECT * from study_material_student s where s.material_id=$material_id and s.member_id=$member_id ";
        $query = $this->db->query($q);
        if ($query->num_rows() != 0) {
            $result = $query->result();


            if ($result[0]->status == 'delivered')
                die("<h1>Already Taken </h1>");
            else
                redirect('admin/study_material_student/edit/' . $result[0]->id);
        }

        $q = "INSERT IGNORE into study_material_student (material_id,member_id,created_by,date) values('$material_id','$member_id','$expertid','" . date('Y-m-d H:i:s') . "') ";
        $query = $this->db->query($q);
        $id = $this->db->insert_id();
        redirect('admin/study_material_student/edit/' . $id);
    }

    function study_material_student() {
        $expertid = $this->tank_auth->get_user_id();

        $crud = new grocery_CRUD();

        $crud->set_table('study_material_student');
        $crud->set_theme('datatables');
        $crud->set_relation('material_id', 'study_material', '{material_id}-  {material_name} ', null, 'material_id desc');
        $crud->set_relation('member_id', 'members', '{member_id}-  {firstname} {lastname}', null, 'member_id');
        $crud->field_type('material_id', 'readonly');
        $crud->field_type('member_id', 'readonly');
        $crud->field_type('created_by', 'hidden', $expertid);
        $crud->field_type('time', 'hidden', date('Y-m-d H:i:s'));
        $crud->callback_before_update(array($this, 'backup_study_material_student'));
        //$crud->unset_edit();
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();


        $output = $crud->render();
        $this->_example_output($output);
    }

    function backup_study_material_student($post_array, $primary_key) {
        $q = "INSERT INTO `bc_study_material_student`( `id`, `material_id`, `member_id`, `mode`, `date`, `refno`, `created_by`, `time`, `status`) (select * from study_material_student where id=$primary_key);";
        $query = $this->db->query($q);
        return;
    }

    /////////////////////////////////////////

    function index() {
        $this->_example_output((object) array('output' => '', 'js_files' => array(), 'css_files' => array()));
    }

    function subject_expert() {
        $crud = new grocery_CRUD();
        $crud->set_table('subject_expert');
        $crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
        $crud->set_relation('expert_id', 'users', '{id}-{username}');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    function upload_form() {
        $output = "<form action='/admin/index.php/admin/ans_script_upload' method='post'>
            <input type='file' name='file[]' multiple>
            <input type='submit' value='upload'>
            </form>";
        echo $output;
    }

    function ans_script_upload() {
        var_dump($_FILES);
        foreach ($_FILES['file']['name'] as $filename) {
            $temp = $target;
            $tmp = $_FILES['file']['tmp_name'][$count];
            $count = $count + 1;
            $temp = $temp . basename($filename);
            echo "<br>" . $filename;
            //move_uploaded_file($tmp,$temp);
            $temp = '';
            $tmp = '';
        }
    }

    function interview_videos($mode = 0) {
        $crud = new grocery_CRUD();
        $crud->set_table('interview_videos');
        $crud->set_relation('member_id', 'members', '{member_id}-{firstname}', null, 'member_id desc');
        if ($mode != 0)
            $crud->set_field_upload('path', 'assets/uploads/files/interview');
        $crud->unset_print();
          $crud->unset_export();
           //$crud->unset_add();
           // $crud->unset_edit();
             $crud->unset_delete();

        $output = $crud->render();
        $this->_example_output($output);
    }

    //////////////////////////////////////////////// Mass Editing /////////////////////////////
    //////////////////////////////////////////AJAX SUPPORT
    function get_select_options($type) {
        $this->load->model('admin_model');
        $this->load->helper('form');
        $ff = "";
        $id = $_REQUEST['id'];
        switch ($type) {
            case 1:
                $op = $this->admin_model->get_sub_section($id);
                break;
            case 2:
                $op = $this->admin_model->get_topic($id);
                break;
            case 3:
                $op = $this->admin_model->get_package($id);
                break;
            case 4:
                $op = $this->admin_model->get_test($id, 3);
                break;
            case 5:
                $op = $this->admin_model->get_test($id, 1);
                break;
            case 6:
                $op = $this->admin_model->get_section($id);
                break;
            case 7:
                $op = $this->admin_model->get_section_topic($id);
                break;
        }
        foreach ($op as $key => $value) {
            $ff.= '<option value="' . $key . '">' . $value . '</option>';
        }
        echo $ff;
    }

    ///////////////////////////////////

    function mass_edit($start = 0, $perpage = 10, $status = 0, $expert = 0,$reviewer=0, $test_id = 0, $q_no_on_paper = 0, $of_package_id = 0, $of_test_id = 0, $tid = 0, $section_id = 0, $sub_section_id = 0, $subject_id = 1, $package_id = 0, $start_date = 0, $end_date = 0, $rating_id = 0, $difficulty_id = 0, $order = 3, $importance_id = 0) {

        $this->load->model('admin_model');
        $this->load->helper('form');
		$expert_list=$this->admin_model->get_expert();
        $output = "";
        $ff = "";
		$view="";
		if(isset($_POST['start']))
			$start=$_POST['start'];
		if(isset($_POST['perpage']))
			$perpage=$_POST['perpage'];
		if(isset($_POST['status']))
			$status=$_POST['status'];
		if(isset($_POST['expert']))
			$expert=$_POST['expert'];
		if(isset($_POST['reviewer']))
			$reviewer=$_POST['reviewer'];
		if(isset($_POST['test_id']))
			$test_id=$_POST['test_id'];
		if(isset($_POST['q_no_on_paper']))
			$q_no_on_paper=$_POST['q_no_on_paper'];
		if(isset($_POST['of_package_id']))
			$of_package_id=$_POST['of_package_id'];
		if(isset($_POST['of_test_id']))
			$of_test_id=$_POST['of_test_id'];
		
		if(isset($_POST['subject_id']))
			$subject_id=$_POST['subject_id'];
		if(isset($_POST['tid']))
			$tid=$_POST['tid'];
		if(isset($_POST['section_id']))
			$section_id=$_POST['section_id'];
		if(isset($_POST['sub_section_id']))
			$sub_section_id=$_POST['sub_section_id'];
		
		if(isset($_POST['package_id']))
			$package_id=$_POST['package_id'];
		
		if(isset($_POST['start_date']))
			$start_date=$_POST['start_date'];
		
		if(isset($_POST['end_date']))
			$end_date=$_POST['end_date'];
		if(isset($_POST['rating_id']))
			$rating_id=$_POST['rating_id'];
		
		if(isset($_POST['difficulty_id']))
			$difficulty_id=$_POST['difficulty_id'];
		
		if(isset($_POST['order']))
			$order=$_POST['order'];
		
		if(isset($_POST['importance_id']))
			$importance_id=$_POST['importance_id'];
		
        $output.="<div id='dbg'>start=$start,perpage=$perpage,status=$status,expert=$expert, test_id=$test_id, q_no_on_paper=$q_no_on_paper, of_package_id=$of_package_id, of_test_id=$of_test_id, tid=$tid, section_id=$section_id, sub_section_id=$sub_section_id, package_id=$package_id, subject_id=$subject_id, start_date=$start_date, end_date=$end_date, rating_id=$rating_id difficulty_id=$difficulty_id, order=$order</div>";
         $status_op = array(
              '0' => 'All',
			  '-1'=> 'New',
              '1' => 'Draft',
              '2' => 'Improve',
              '3' => 'Changes done',
              '4' => 'Ready',
              '5' => 'FINAL',
              '6' => 'Not Suitable',
              '8' => 'for future test',
              '12' => 'OLD',
              '13' => 'For Question Bank',
              );
			  
		$options_a = array(
                'a' => 'a',
                'b' => 'b',
                'c' => 'c',
                'd' => 'd',
                
            );
			$options_d = array(
			     '0' => 'All',
                '1' => 'Easy',
                '2' => 'Medium',
                '3' => 'Difficult',
                '4' => 'Very Difficult',
            );
			
			$options_n = array(
                '1' => 'Factual',
                '6' => 'Conceptual',
            );
		$ff.=form_open('admin/mass_edit');	
		
		$ff.="<fieldset><legend>Basic</legend>";
        $ff.="Start:" . form_input('start', $start) . "Per Page:" . form_input('perpage', $perpage);
        //$ff.="Status:".form_input('status',$status);
        $ff.="Status " . form_dropdown('status', $status_op , $status, 'id="status" , class="ff"');
        //$ff.="Expert:".form_input('expert',$expert);
        $ff.="Expert " . form_dropdown('expert', $expert_list, $expert, 'id="expert" , class="ff"');
		$ff.="Reviewer " . form_dropdown('reviewer', $expert_list, $reviewer, 'id="reviewer" , class="ff"');
         $ff.="</fieldset>";
        //$ff.="<br>From Test ID:".form_input('of_test_id',$of_test_id);
        //$ff.="Tid:".form_input('tid',$tid);
        //$ff.="Section ID:".form_input('section_id',$section_id);
        //$ff.="Sub Section ID:".form_input('sub_section_id',$sub_section_id);
		
		$ff.="<fieldset><legend>What to Select</legend>";
$ff.="Subject ID" . form_dropdown('subject_id', $this->admin_model->get_subject(), $subject_id, 'id="subject" , class="ff"');
        $ff.="SECTION " . form_dropdown('section_id', $this->admin_model->get_section($subject_id), $section_id, 'id="section", class="ff"');
        $ff.="SUB SECTION" . form_dropdown('sub_section_id', $this->admin_model->get_sub_section($section_id), $sub_section_id, 'id="sub_section", class="ff"');
        $ff.="TOPIC" . form_dropdown('tid', $this->admin_model->get_topic($sub_section_id), $tid, 'id="tid", class="ff"');
        //  $ff.="<br>Add to Test ID:".form_input('test_id',$test_id);
$ff.="</fieldset>";
       $ff.="<fieldset><legend>What to Display</legend>";
		$ff.="";
		if(isset($_POST['iv']))
		$ff.="<input type=checkbox name=iv value=iv checked>Introduction";
	    else
		$ff.="<input type=checkbox name=iv value=iv >Introduction";
	    
		if(isset($_POST['qv']))
		$ff.="<input type=checkbox name=qv value=qv checked>Question";
	    else
	    $ff.="<input type=checkbox name=qv value=qv >Question";
	
		if(isset($_POST['av']))
		$ff.="<input type=checkbox name=av value=av checked>Answer";
		else
		$ff.="<input type=checkbox name=av value=av >Answer";	
	
		if(isset($_POST['ev']))
		$ff.="<input type=checkbox name=ev value=ev checked>Explanation";
		else
		$ff.="<input type=checkbox name=ev value=ev >Explanation";	
	
		if(isset($_POST['sv']))
		$ff.="<input type=checkbox name=sv value=sv checked>Source";
		else
		$ff.="<input type=checkbox name=sv value=sv >Source";
	
		if(isset($_POST['cv']))
		$ff.="<input type=checkbox name=cv value=cv checked>Comment";
		else
		$ff.="<input type=checkbox name=cv value=cv >Comment";
	
		if(isset($_POST['hiv']))
		$ff.="<input type=checkbox name=hiv value=hiv checked>Hindi Introduction";
		else
			$ff.="<input type=checkbox name=hiv value=hiv >Hindi Introduction";
		
		if(isset($_POST['hqv']))
		$ff.="<input type=checkbox name=hqv value=hqv checked>Hindi Question";
		else
		$ff.="<input type=checkbox name=hqv value=hqv >Hindi Question";	
	
		if(isset($_POST['hav']))
		$ff.="<input type=checkbox name=hav value=hav checked>Hidi Answer";
		else
		$ff.="<input type=checkbox name=hav value=hav >Hidi Answer";
	
		if(isset($_POST['hev']))
		$ff.="<input type=checkbox name=hev value=hev checked>Hindi Explanation";
		else
		$ff.="<input type=checkbox name=hev value=hev >Hindi Explanation";
	
	    if(isset($_POST['view']))
		{
			$ff.="<input type=checkbox name=view value=yes checked>View Only";
			$view='yes';
		}
		
		else
		$ff.="<input type=checkbox name=view value=yes >View Only";
	$ff.="</fieldset>";
	
	 $ff.="<br><div id='more'>";
        //$ff.="<br>Subject ID:".form_input('subject_id',$subject_id);
        $ff.="<fieldset><legend>From</legend>";
        $ff.="From PACKAGE:" . form_dropdown('of_package_id', $this->admin_model->get_package($subject_id), $of_package_id, 'id="of_package" , class="ff"');
        $ff.="FROM TEST" . form_dropdown('of_test_id', $this->admin_model->get_test($of_package_id, 3), $of_test_id, 'id="of_test" , class="ff"');
		$ff.="</fieldset>";
		$ff.="<fieldset><legend>To</legend>";
        $ff.="Add to Package:" . form_dropdown('package_id', $this->admin_model->get_package($subject_id), $package_id, 'id="package" , class="ff"');
        $ff.="Add to TEST " . form_dropdown('test_id', $this->admin_model->get_test($package_id, 1), $test_id, 'id="test" , class="ff"');
        $ff.="Start from Q No.:" . form_input('q_no_on_paper', $q_no_on_paper);
        $ff.="</fieldset>";
		$ff.="<fieldset><legend>Additional</legend>";
		$ff.="<br>Start Date:" . form_input('start_date', $start_date);
        $ff.="End Date:" . form_input('end_date', $end_date);
        $ff.="Rating " . form_dropdown('rating_id', $this->admin_model->get_rating_scale(), $rating_id, 'id="rating", class="ff"');
        $ff.="Importance " . form_dropdown('importance_id', $this->admin_model->get_importance_scale(), $importance_id, 'id="importance", class="ff"');
    

        $ff.="Difficulty " . form_dropdown('difficulty_id', $options_d, $difficulty_id, 'id="difficulty", class="ff"');

        $options = array(
            '0' => 'no order',
            '1' => 'Random',
            '2' => 'Qid ASC',
            '3' => 'Qid DESC',
            '4' => 'Q_No_On_Paper',
        );

        $ff.="Order " . form_dropdown('order', $options, $order, 'id="order", class="ff"');
$ff.="</fieldset>";
        $ff.="</div>";
        //$ff.="<span id=mef><img src='/admin/images/apply.jpg' height=30></span>";
        //$ff.= "<span id=show_more >|<img src='/admin/images/more.jpg' height=30></span>";
		$ff.= "<input type=button value='More Options' id=show_more>";
		$ff.=form_submit('mysubmit', 'Apply');
		$ff.=form_close();
        // $ff.=".........<span id=hta><b>Hide Text Boxes</b></span>";
        $expert_id = $this->tank_auth->get_user_id();

        $result = $this->admin_model->mass_edit($status, $start, $perpage, $expert,$reviewer, $of_test_id, $tid, $section_id, $sub_section_id, $package_id, $start_date, $end_date, $rating_id, $difficulty_id, $order, $importance_id);
        $output.="";

        $output.="";
        $output.= "<br>Total:" . count($result);
        $output.="<table border=1 border-collapse:collapse><tr><th>Qid</th><th>Question</th>";
        //var_dump($result);

        foreach ($result as $row) {


            $output.="<tr><td valign=top width=20%>$row->qid <br><a href='/admin/index.php/admin/mass_edit/$start/$perpage/0/$row->created_by'>Expert: $row->created_by</a> <br>  <a href='/admin/index.php/admin/mass_edit/$start/$perpage/$row->question_status/0'>Status: $row->question_status</a><br>Used in Tests: $row->tests<br>Marks: $row->type<br>$row->topic_name <br>Negative: $row->negative<br>Date: $row->date<br>Motive: $row->motive<br><a href=/admin/index.php/pt/bc_pt_questions/$row->qid>History</a>";

            if ($of_test_id != 0)
                $output.="<br>Q_No_On_Paper: $row->q_no_on_paper";

            $output.="</td><td>";
			if(isset($_POST['iv']))
			{
            $data2 = array(
                'name' => $row->qid . "_instruction",
                'class' => 'instruction',
                'value' => $row->instruction,
                'cols' => '150',
                'rows' => '20',
                'style' => 'width:80%',
            );
            if ($view!='yes') {
                $output.="<br>Introduction".form_textarea($data2);
            } else {
                $output.=$row->instruction;
            }
			}
			
			if(isset($_POST['hiv']))
			{
            $data2 = array(
                'name' => $row->qid . "_instructionhindi",
                'class' => 'instruction',
                'value' => $row->instruction_hindi,
                'cols' => '150',
                'rows' => '20',
                'style' => 'width:80%',
            );
            if ($view!='yes') {
                $output.="<br>Hindi Introduction".form_textarea($data2);
            } else {
                $output.=$row->instruction;
            }
			}
			
            if(isset($_POST['qv']))
			{
            $data2 = array(
                'name' => $row->qid . "_text",
                'class' => 'question_text',
                'value' => $row->question_text,
                'cols' => '150',
                'rows' => '20',
                'style' => 'width:80%',
            );
            if ($view!='yes') {
                $output.=form_textarea($data2);
            } else {
                $output.=$row->question_text;
            }
			}
            //Hindi text box
			  if(isset($_POST['hqv']))
			{
            $data2 = array(
                'name' => $row->qid . "_hindi",
                'class' => 'question_hindi',
                'value' => $row->question_text_hindi,
                'cols' => '150',
                'rows' => '15',
                'style' => 'width:80%',
            );
            if ($view!='yes') {
                $output.="Hindi" . form_textarea($data2);
            } else {
                $output.=$row->question_text_hindi;
            }
            }
            //$options= $this->admin_model->get_for_drop_down('question_status');
            // $output.="<hr>  Status:".form_dropdown($row->qid."_status", $status_op , $row->question_status);
            $output.="<hr>Status " . form_dropdown($row->qid . "_status", $status_op, $row->question_status, 'id="' . $row->qid . '_status", class="update_on_change" ');
            //$options= $this->admin_model->get_for_drop_down('difficulty');
           
            $output.="  Answer:" . form_dropdown($row->qid . "_answer", $options_a, $row->answer, 'id="' . $row->qid . '_answer", class="update_on_change" ');

           
            $output.="  Difficulty:" . form_dropdown($row->qid . "_difficulty", $options_d, $row->difficulty, 'id="' . $row->qid . '_difficulty", class="update_on_change" ');

           
            //$options= $this->admin_model->get_for_drop_down('question_status');
            $output.="  Nature:" . form_dropdown($row->qid . "_nature", $options_n, $row->nature, 'id="' . $row->qid . '_nature", class="update_on_change" ');
            $data = array(
                'name' => $row->qid . '_test',
                'id' => $row->qid . '_test',
                'value' => $test_id,
                'maxlength' => '10',
                'size' => '1',
                    //'readonly'=>'readonly',
            );

            $output.="Rating " . form_dropdown($row->qid . "_rating", $this->admin_model->get_rating_scale(), $row->question_rating, 'id="' . $row->qid . '_rating", class="update_on_change" ');
            $output.="Importance " . form_dropdown($row->qid . "_importance", $this->admin_model->get_importance_scale(), $row->question_importance, 'id="' . $row->qid . '_importance", class="update_on_change"');
            $output.="  TestID:" . form_input($data);
            $data = array(
                'name' => $row->qid . '_qnoonpaper',
                'id' => $row->qid . '_qnoonpaper',
                'value' => $q_no_on_paper++,
                'maxlength' => '10',
                'size' => '1',
            );
            $output.="  Question_no_on_paper:" . form_input($data);
            $output.="<span id=" . $row->qid . "_adds class='btn btn-info ss'> <b>Add to Test</b></span>";
			
			$data = array(
                'name' => $row->qid . '_link_to_question',
                'id' => $row->qid . '_link_to_question',
                'value' => '',
                'maxlength' => '10',
                'size' => '2',
				
            );
            $output.="  LTQ:" . form_input($data);
			
			$output.="<span id=" . $row->qid . "_link class=lq > ....<b>Link</b></span>";
            $output.="<span id=" . $row->qid . "_resp ></span>";
            //$output.="  Question_no_on_paper:".form_input($row->qid.'_qnoonpaper', $q_no_on_paper++);


            $output.=" .......... SECTION:" . form_dropdown($row->qid . "_section", $this->admin_model->get_section($subject_id), '', 'id="' . $row->qid . '_section", class="qq" ');

            $query_tid = $this->db->query("SELECT tid,topic_name from gs_topics t where t.section_id=$section_id or t.tid=$row->tid");
            $op = array();
            foreach ($query_tid->result() as $row_tid) {
                // $count+= $row->count;
                $op[$row_tid->tid] = substr($row_tid->topic_name, 0, 50);
            }

            $output.="  TID:" . form_dropdown($row->qid . "_tid", $op, $row->tid, 'id="' . $row->qid . '_tid", class="update_on_change"');

            $output.="Reviewer " . form_dropdown($row->qid . "_reviewer", $expert_list, $row->reviewer, 'id="' . $row->qid . '_reviewer", class="update_on_change" ');
              $output.="<span id=" . $row->qid . "_remove  class='btn btn-danger rm' role=button > <b>Remove from Test</b></span>";
			  if(isset($_POST['ev']))
			{ 
            $output.="<hr><br>Explanation";
            $data2 = array(
                'name' => $row->qid . "_explanation",
                'class' => 'explanation',
                'value' => $row->explanation,
                'cols' => '150',
                'rows' => '20',
                'style' => 'width:80%',
            );

            
                $output.=form_textarea($data2);
            }
			
			if(isset($_POST['hev']))
			{ 
            $output.="<hr><br>Hindi Explanation";
            $data2 = array(
                'name' => $row->qid . "_explanationhindi",
                'class' => 'explanation',
                'value' => $row->explanation_hindi,
                'cols' => '150',
                'rows' => '20',
                'style' => 'width:80%',
            );

            
                $output.=form_textarea($data2);
            }
			  if(isset($_POST['sv']))
			{
                $output.="<br>Source";
                $data2 = array(
                    'name' => $row->qid . "_source",
                    'class' => 'source',
                    'value' => $row->source,
                    'cols' => '150',
                    'rows' => '2',
                    'style' => 'width:80%',
                );

                $output.=form_textarea($data2);
			}
			  if(isset($_POST['cv']))
			{
                $output.="<br>Comments";
                $data2 = array(
                    'name' => $row->qid . "_comment",
                    'class' => 'comment',
                    'value' => $row->review_comments,
                    'cols' => '150',
                    'rows' => '2',
                    'style' => 'width:80%',
                );

                $output.=form_textarea($data2);
			
                $output.="<span id=" . $row->qid . "_email class=email> <b>Email This Comment To Expert</b></span>";
            }
            $output.="</td></tr>";
			/*
			//select linked questions 
					    $q1="SELECT DISTINCT `q1`.`qid`, `gs_topics`.`topic_name`, `q1`.`motive`, `q1`.`tid`, `q1`.`question_rating`, `q1`.`question_importance`, `q1`.`date`, `q1`.`type`, `q1`.`negative`, `q1`.`question_text`, `q1`.`question_text_hindi`, (select GROUP_CONCAT(test_id) from pt_test_questions where `pt_test_questions`.`qid`= `q1`.`qid`) as tests, `q1`.`answer`, `q1`.`explanation`, `q1`.`source`, `q1`.`question_status`, `q1`.`review_comments`, `q1`.`nature`, `q1`.`difficulty`, `q1`.`created_by`, `q1`.`reviewer`, `q1`.`history`, `pt_test_questions`.`q_no_on_paper` FROM (`pt_questions` q1) JOIN `gs_topics` ON `gs_topics`.`tid` = `q1`.`tid` JOIN `pt_test_questions` ON `pt_test_questions`.`qid` = `q1`.`qid` and pt_test_questions.test_id=1 WHERE  q1.link_to_question=$row->qid";
						
                      $query1 = $this->db->query($q1);
                      
					  foreach ($query1->result() as $row)
						{
							
            $output.="<tr><td valign=top width=20%>$row->qid <br><a href='/admin/index.php/admin/mass_edit/$start/$perpage/0/$row->created_by'>Expert: $row->created_by</a> <br>  <a href='/admin/index.php/admin/mass_edit/$start/$perpage/$row->question_status/0'>Status: $row->question_status</a><br>Used in Tests: $row->tests<br>Marks: $row->type<br>$row->topic_name <br>Negative: $row->negative<br>Date: $row->date<br>Motive: $row->motive<br><a href=/admin/index.php/pt/bc_pt_questions/$row->qid>History</a>";

            if ($of_test_id != 0)
                $output.="<br>Q_No_On_Paper: $row->q_no_on_paper";

            $output.="</td><td>";

            $data2 = array(
                'name' => $row->qid . "_text",
                'class' => 'question_text',
                'value' => $row->question_text,
                'cols' => '150',
                'rows' => '10',
                'style' => 'width:80%',
            );
            if (!$view!='yes') {
                $output.=form_textarea($data2);
            } else {
                $output.=$row->question_text;
            }
            //Hindi text box
            $data2 = array(
                'name' => $row->qid . "_hindi",
                'class' => 'question_hindi',
                'value' => $row->question_text_hindi,
                'cols' => '150',
                'rows' => '10',
                'style' => 'width:80%',
            );
            if (!$view!='yes') {
                $output.="Hindi" . form_textarea($data2);
            } else {
                $output.=$row->question_text_hindi;
            }
            
            //$options= $this->admin_model->get_for_drop_down('question_status');
            // $output.="<hr>  Status:".form_dropdown($row->qid."_status", $status_op , $row->question_status);
            $output.="<hr>Status " . form_dropdown($row->qid . "_status", $status_op, $row->question_status, 'id="' . $row->qid . '_status", class="update_on_change" ');
            //$options= $this->admin_model->get_for_drop_down('difficulty');
           
            $output.="  Answer:" . form_dropdown($row->qid . "_answer", $options_a, $row->answer, 'id="' . $row->qid . '_answer", class="update_on_change" ');

           
            $output.="  Difficulty:" . form_dropdown($row->qid . "_difficulty", $options_d, $row->difficulty, 'id="' . $row->qid . '_difficulty", class="update_on_change" ');

           
            //$options= $this->admin_model->get_for_drop_down('question_status');
            $output.="  Nature:" . form_dropdown($row->qid . "_nature", $options_n, $row->nature, 'id="' . $row->qid . '_nature", class="update_on_change" ');
            $data = array(
                'name' => $row->qid . '_test',
                'id' => $row->qid . '_test',
                'value' => $test_id,
                'maxlength' => '10',
                'size' => '1',
                    //'readonly'=>'readonly',
            );

            $output.="Rating " . form_dropdown($row->qid . "_rating", $this->admin_model->get_rating_scale(), $row->question_rating, 'id="' . $row->qid . '_rating", class="update_on_change" ');
            $output.="Importance " . form_dropdown($row->qid . "_importance", $this->admin_model->get_importance_scale(), $row->question_importance, 'id="' . $row->qid . '_importance", class="update_on_change"');
            $output.="  TestID:" . form_input($data);
            $data = array(
                'name' => $row->qid . '_qnoonpaper',
                'id' => $row->qid . '_qnoonpaper',
                'value' => $q_no_on_paper++,
                'maxlength' => '10',
                'size' => '1',
            );
            $output.="  Question_no_on_paper:" . form_input($data);
            $output.="<span id=" . $row->qid . "_adds class=ss> <b>Add to Test</b></span>";
			
			$data = array(
                'name' => $row->qid . '_link_to_question',
                'id' => $row->qid . '_link_to_question',
                'value' => '',
                'maxlength' => '10',
                'size' => '2',
				
            );
            $output.="  LTQ:" . form_input($data);
			
			$output.="<span id=" . $row->qid . "_link class=lq > ....<b>Link</b></span>";
            $output.="<span id=" . $row->qid . "_resp ></span>";
            //$output.="  Question_no_on_paper:".form_input($row->qid.'_qnoonpaper', $q_no_on_paper++);


            $output.=" .......... SECTION:" . form_dropdown($row->qid . "_section", $this->admin_model->get_section($subject_id), '', 'id="' . $row->qid . '_section", class="qq" ');

            $query_tid = $this->db->query("SELECT tid,topic_name from gs_topics t where t.section_id=$section_id or t.tid=$row->tid");
            $op = array();
            foreach ($query_tid->result() as $row_tid) {
                // $count+= $row->count;
                $op[$row_tid->tid] = substr($row_tid->topic_name, 0, 50);
            }

            $output.="  TID:" . form_dropdown($row->qid . "_tid", $op, $row->tid, 'id="' . $row->qid . '_tid", class="update_on_change"');

            $output.="Reviewer " . form_dropdown($row->qid . "_reviewer", $expert_list, $row->reviewer, 'id="' . $row->qid . '_reviewer", class="update_on_change" ');

            $output.="<hr><br>Explanation";
            $data2 = array(
                'name' => $row->qid . "_explanation",
                'class' => 'explanation',
                'value' => $row->explanation,
                'cols' => '150',
                'rows' => '10',
                'style' => 'width:80%',
            );

            if (!$view!='yes') {
                $output.=form_textarea($data2);

                $output.="<br>Source";
                $data2 = array(
                    'name' => $row->qid . "_source",
                    'class' => 'source',
                    'value' => $row->source,
                    'cols' => '150',
                    'rows' => '2',
                    'style' => 'width:80%',
                );

                $output.=form_textarea($data2);

                $output.="<br>Comments";
                $data2 = array(
                    'name' => $row->qid . "_comment",
                    'class' => 'comment',
                    'value' => $row->review_comments,
                    'cols' => '150',
                    'rows' => '2',
                    'style' => 'width:80%',
                );

                $output.=form_textarea($data2);
                $output.="<span id=" . $row->qid . "_email class=email> <b>Email This Comment To Expert</b></span>";
            }
            $output.="</td></tr>";
						}*/
        }
        $attributes = array('id' => 'mass_edit');
        //$start=0,$perpage=10,$status=0,$expert=0, $test_id=0, $q_no_on_paper=0,$of_package_id=0,$of_test_id=0,$tid=0,$section_id=0,$sub_section_id=0,$subject_id=1,$package_id=0,$start_date=0,$end_date=0,$rating_id=0,$difficulty_id=0,$order=1
        $output = $ff . form_open('admin/submit_mass_edit/' . $start . "/" . $perpage . "/" . $status . "/" . $expert . "/" . $test_id . "/" . $q_no_on_paper . "/" . $of_package_id . "/" . $of_test_id . "/" . $tid . "/" . $section_id . "/" . $sub_section_id . "/" . $subject_id . "/" . $package_id . "/" . $start_date . "/" . $end_date . "/" . $rating_id . "/" . $difficulty_id . "/" . $order, $attributes) . $output;

        $output.="</table>";

        $output.="<p align=center>" . form_submit('', 'submit') . "</p>";
        $output.=form_close();
        $data['output'] = $output;

        $this->load->view('me_link.php', $data);
        //  $this->_example_output($output);
    }

    function submit_mass_edit($start, $perpage, $status, $expert, $test_id = 0, $q_no_on_paper = 0, $of_package_id = 0, $of_test_id = 0, $tid = 0, $section_id = 0, $sub_section_id = 0, $subject_id, $package_id, $start_date, $end_date, $rating_id, $difficulty_id, $order) {

        $this->load->model('admin_model');
        $result = $this->input->post();

        $output = "<hr/><p align=center><b><font color=blue>";
        $ret = $this->admin_model->submit_mass_edit($result);





        $output.="Updated successfully </b></p>";

        $output.="<br><a href='/admin/index.php/admin/mass_edit/" . ($start + $perpage) . "/" . ($perpage) . "/" . ($status) . "/" . $expert . "/" . $test_id . "/" . $q_no_on_paper . "/" . $of_package_id . "/" . $of_test_id . "/" . ($tid) . "/" . ($section_id) . "/" . $sub_section_id . "/" . $subject_id . "/" . $package_id . "/" . $start_date . "/" . $end_date . "/" . $rating_id . "/" . $difficulty_id . "/" . $order . "'>Click here to Edit next $perpage questions </a>";

        $output.="<br><a href='/admin/index.php/admin/mass_edit/" . ($start) . "/" . ($perpage) . "/" . ($status) . "/" . $expert . "/" . $test_id . "/" . $q_no_on_paper . "/" . $of_package_id . "/" . $of_test_id . "/" . ($tid) . "/" . ($section_id) . "/" . $sub_section_id . "/" . $subject_id . "/" . $package_id . "/" . $start_date . "/" . $end_date . "/" . $rating_id . "/" . $difficulty_id . "/" . $order . "'>Click here to Edit without changing start </a>";

        $data['output'] = $output;

        $this->load->view('me_link.php', $data);
    }

    ///////////////////////// MAINS QUESTION MASS EDIT ////////////////////////////////////


    function mass_edit_mains($start = 0, $perpage = 10, $status = 0, $expert = 0, $test_id = 0, $q_no_on_paper = 0, $of_test_id = 0, $tid = 0) {

        $output = "start=$start,perpage=$perpage,status=$status,expert=$expert, test_id=$test_id, q_no_on_paper=$q_no_on_paper,of_test_id=$of_test_id,tid=$tid";
        $expert_id = $this->tank_auth->get_user_id();
        $this->load->model('admin_model');
        $this->load->helper('form');
        $result = $this->admin_model->mass_edit_mains($status, $start, $perpage, $expert, $of_test_id, $tid);
        $output.="<hr/>";
        $output.="";

        $output.="<table border=1 border-collapse:collapse><tr><th>Qid</th><th>Question</th>";
        //var_dump($result);

        foreach ($result as $row) {


            $output.="<tr><td valign=top>$row->qid <br><br> <a href='/admin/index.php/admin/mass_edit_mains/$start/$perpage/0/$row->created_by'>Expert: $row->created_by</a> <br><br>  <a href='/admin/index.php/admin/mass_edit_mains/$start/$perpage/$row->question_status/0'>Status: $row->question_status</a><br>Marks:$row->type <br>Used in:$row->tests</td><td>";



            $data2 = array(
                'name' => $row->qid . "_text",
                'class' => 'text',
                'value' => $row->question_text,
                'cols' => '200',
                'rows' => '15',
                'style' => 'width:80%',
            );

            $output.=form_input($data2);




            $options = array(
                '1' => 'draft',
                '2' => 'needs review',
                '3' => 'changes done',
                '4' => 'ready',
                '5' => 'final',
                '6' => 'Not Suitable for Advanced Test',
            );
            //$options= $this->admin_model->get_for_drop_down('question_status');
            $output.="<hr>  Status:" . form_dropdown($row->qid . "_status", $options, $row->question_status);

            //$options= $this->admin_model->get_for_drop_down('difficulty');
            /*  $options = array(
              'a'  => 'a',
              'b'    => 'b',
              'c'   => 'c',
              'd' => 'd',
              'e' => 'e',
              );
              $output.="  Answer:".form_dropdown($row->qid."_answer", $options, $row->answer); */

            $options = array(
                '1' => 'Easy',
                '2' => 'Medium',
                '3' => 'Difficult',
                '4' => 'Very Difficult',
            );
            $output.="  Difficulty:" . form_dropdown($row->qid . "_difficulty", $options, $row->difficulty);

            $options = array(
                '1' => 'F',
                '2' => 'CA',
                '3' => 'FCA',
                '4' => 'CAA',
                '5' => 'U',
                '6' => 'FA',
            );
            //$options= $this->admin_model->get_for_drop_down('question_status');
            $output.="  Nature:" . form_dropdown($row->qid . "_nature", $options, $row->nature);

            $output.="  TestID:" . form_input($row->qid . '_test', $test_id);
            $output.="  Question_no_on_paper:" . form_input($row->qid . '_qnoonpaper', $q_no_on_paper++);


            $output.="<hr><br>Explanation";
            $data2 = array(
                'name' => $row->qid . "_explanation",
                'class' => 'explanation',
                'value' => $row->explanation,
                'cols' => '150',
                'rows' => '2',
                'style' => 'width:80%',
            );

            $output.=form_textarea($data2);
            $output.="<br>Source";
            $data2 = array(
                'name' => $row->qid . "_source",
                'class' => 'source',
                'value' => $row->source,
                'cols' => '150',
                'rows' => '2',
                'style' => 'width:80%',
            );

            $output.=form_textarea($data2);
            $output.="<br>Comments";
            $data2 = array(
                'name' => $row->qid . "_comment",
                'class' => 'comment',
                'value' => $row->review_comments,
                'cols' => '150',
                'rows' => '2',
                'style' => 'width:80%',
            );

            $output.=form_textarea($data2);


            $output.="</td></tr>";
        }
        $attributes = array('id' => 'mass_edit');
        $output = form_open('admin/submit_mass_edit_mains/' . $start . "/" . $perpage . "/" . $status . "/" . $expert . "/" . $test_id . "/" . $q_no_on_paper . "/" . $of_test_id, $attributes) . $output;

        $output.="</table>";

        $output.="<p align=center>" . form_submit('', 'submit') . "</p>";

        $data['output'] = $output;

        $this->load->view('me_link.php', $data);
        //  $this->_example_output($output);
    }

    function submit_mass_edit_mains($start, $perpage, $status, $expert, $test_id = 0, $q_no_on_paper = 0, $of_test_id = 0) {

        $this->load->model('admin_model');
        $result = $this->input->post();

        $output = "<hr/><p align=center><b><font color=blue>";
        $ret = $this->admin_model->submit_mass_edit_mains($result);

        $output.="Updated successfully </b></p>";
        $output.="<a href='/admin/index.php/admin/mass_edit/" . ($start + $perpage) . "/" . ($perpage) . "/" . ($status) . "/" . $expert . "/" . $test_id . "/" . $q_no_on_paper . "/" . $of_test_id . "'>Click here to rate next $perpage questions </a>";



        $data['output'] = $output;
        $this->load->view('me_link.php', $data);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////
    function print_test_pt($test_id = 0, $format = 0) {
        $this->load->model('admin_model');
        $rt = $this->admin_model->get_questions_pt($test_id);
        $output = "<style>ol {text-align: justify}</style><h1>GS(Prelims)TEST ID-$test_id  </h1> ";
		$output.="<ol>";
        // var_dump($rt);
        if ($format == 0) {
            foreach ($rt as $row) {
                $output.="<br>Q." . $row->q_no_on_paper . $row->question_text;
                $output.="Answer.<b>" . ucfirst($row->answer) . "</b><br>" . $row->explanation . "<br>";
            }
        } else if ($format == 1) {
            foreach ($rt as $row) {
                $output.="$row->instruction <li> ".substr($row->question_text,3,strlen($row->question_text)-3)."</li>";
                //$output.="Answer.<b>$row->answer</b><br>". $row->explanation."<br>";
            }
        } else if ($format == 2) {
            foreach ($rt as $row) {
                //$output.="<br>Q.".$row->q_no_on_paper.$row->question_text;
                $output.="Q $row->q_no_on_paper." . ucfirst($row->answer) . "</b> $row->explanation <br>";
            }
        }
		else if ($format == 3) {
            foreach ($rt as $row) {
                $output.="$row->instruction_hindi <li> ".substr($row->question_text_hindi,3,strlen($row->question_text_hindi)-3)."</li>";
                //$output.="Answer.<b>$row->answer</b><br>". $row->explanation."<br>";
            }
        }
		else if ($format == 4) {
            foreach ($rt as $row) {
                 $output.="Q $row->q_no_on_paper." . ucfirst($row->answer) . "</b> $row->explanation_hindi <br>";
                //$output.="Answer.<b>$row->answer</b><br>". $row->explanation."<br>";
            }
        }
		
         $output.="</ol>";
        echo $output;
		/*
		 $data['output'] = $output;

        $html = $this->load->view('bill.php', $data, true);

        $this->load->library('pdf');
        $pdf = $this->pdf->load();
        $pdf->SetColumns(2, 'L', 3);
        $pdf->WriteHTML($output,2); // write the HTML into the PDF
		$pdf->Output($pdfFilePath, 'I');*/
    }

    ///////////////////////////////////////////////// Assign //////////////////////////////////
    function assign($expert = 0, $tid = 385, $no = 0, $negative = 3, $subject_id = 1, $section_id = 90, $sub_section_id = 645) {
        $this->load->model('admin_model');
        $output = "<h1>Assign Prelims Questions to Expert</h1>";
        // $output="  Expert ID:".form_input('expert',$expert);
        $output.="<br>Expert " . form_dropdown('expert', $this->admin_model->get_expert(), $expert, 'id="expert" , class="ff"');
        $output.="<br>Subject ID" . form_dropdown('subject_id', $this->admin_model->get_subject(), '', 'id="subject" , class="ff"');
        $output.="<br>SECTION " . form_dropdown('section_id', $this->admin_model->get_section($subject_id), '', 'id="section", class="ff"');
        $output.="<br>SUB SECTION" . form_dropdown('sub_section_id', $this->admin_model->get_sub_section($section_id), '', 'id="sub_section", class="ff"');
        $output.="<br>TOPIC" . form_dropdown('tid', $this->admin_model->get_topic($sub_section_id), $tid, 'id="tid", class="ff"');

        //$output.="  Topic ID :".form_input('tid',$tid);
        $output.="<br>No of questions:" . form_input('no', $no);
        $output.="<br>Marks:" . form_input('marks', $no);
        $output.=" <br>Negative(No Negative=0):" . form_input('negative', $negative);
        $attributes = array('method' => 'post');
        $output = form_open('admin/create_pt_questions/', $attributes) . $output;
        $output.="<p align=center>" . form_submit('', 'submit') . "</p>";
        $data['output'] = $output;
        $this->load->view('me_link.php', $data);
    }

    function create_pt_questions() {
        $output = "";
        $result = $this->input->post();
        //$output.=var_dump($result);
        $expert = $result['expert'];
        $sid = $result['section_id'];
        $ssid = $result['sub_section_id'];
        $tid = $result['tid'];
        $no = $result['no'];
        $marks = $result['marks'];
        $negative = $result['negative'];
        for ($i = 0; $i < $no; $i++) {
            $query = $this->db->query("INSERT INTO pt_questions(sid,ssid,tid,created_by,question_status,type,negative) values('$sid','$ssid','$tid','$expert','-1','$marks','$negative')");
            if ($query)
                $output.="created";
        }
        $data['output'] = $output;
        $this->load->view('link.php', $data);
    }

    //////////////////////////////////////////////////////////////////////////////////////////

    function work_status($expert = 0, $subject_id = 1) {
        $output = "";
        $tbldata = "";
        $count = 0;
        $this->load->library('table');

        $output.="<h1> Editing work</h1>";
        $q = "SELECT DATE(time) as date, count(DISTINCT q.qid) as count  FROM `bc_pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid)  pt on pt.qid=q.qid where q.created_by=$expert and subject_id=$subject_id group by DATE(q.time);";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);

        $output.="<h1> PT QUESTIONS STATUS</h1>";
        $output.="<h3> TOTAL (including not suitable)</h3>";
        $q = "SELECT 'Total', count(*) as count, count(pt.id) as used , (count(*)-count(pt.id)) as avl FROM `pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid)  pt on pt.qid=q.qid where q.created_by=$expert and subject_id=$subject_id group by t.subject_id;";
        //$output.=$q;
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);
        $output.="<h3> TOTAL (excluding not suitable)</h3>";
        $q = "SELECT 'Total', count(*) as count, count(pt.id) as used , (count(*)-count(pt.id)) as avl FROM `pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid)  pt on pt.qid=q.qid where q.created_by=$expert and subject_id=$subject_id and q.question_status!=6 group by t.subject_id;";
        //$output.=$q;
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);
        $output.="<h3> SECTION WISE (excluding not suitable)</h3>";
        $q = "SELECT t.section_name, count(*) as count,SUM(case when q.question_rating = 3 then 1 else 0 end) as good, count(pt.id) as used , (count(*)-count(pt.id)) as available FROM `pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid) pt on pt.qid=q.qid where q.created_by=$expert and subject_id=$subject_id and q.question_status!=6 group by t.section_id;";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);

        $output.="<h3> STATUS WISE</h3>";

        $q = "SELECT qs.name as STATUS, count(*) as count, count(pt.id) as used , (count(*)-count(pt.id)) as available FROM `pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid) pt on pt.qid=q.qid LEFT JOIN question_status qs ON qs.id=q.question_status where q.created_by=$expert and subject_id=$subject_id group by q.question_status;";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);

        $output.="<h3> SECTION STATUS WISE</h3>";

        $q = "SELECT t.section_name, qs.name as status, count(*) as count, count(pt.id) as used , (count(*)-count(pt.id)) as available FROM `pt_questions` q left join gs_topics t on t.tid=q.tid LEFT JOIN (select p.id, p.qid from pt_test_questions p group by p.qid) pt on pt.qid=q.qid LEFT JOIN question_status qs ON qs.id=q.question_status where q.created_by=$expert and subject_id=$subject_id group by t.section_id, q.question_status;";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);



        $query = $this->db->query("SELECT qid,gs_topics.subject_id, count(qid) as count,pt_questions.tid, section_id ,topic_name,question_status, pt_questions.created_by FROM pt_questions left join gs_topics on gs_topics.tid=pt_questions.tid where created_by=$expert group by pt_questions.tid, pt_questions.created_by, question_status order by  question_status asc, pt_questions.created_by desc");
        foreach ($query->result() as $row) {
            $count+= $row->count;
            $tbldata.="<tr>";
            $tbldata.="<td>" . $row->tid . "(" . $row->topic_name . ")</td>";
            $tbldata.="<td>" . $row->count . "</td>";
            $tbldata.="<td>" . $row->created_by . "</td>";
            $tbldata.= "<td>" . $row->question_status . "</td>";
            //$tbldata.= "<td>".$row->subject_id."</td>";
            //$tbldata.= "<td><a href=".site_url("/admin/mass_edit/")."/0/".$row->count."/".$row->question_status."/".$row->created_by."/0/0/0/".$row->tid."/".$row->section_id.">Edit</a></td>";
            $tbldata.= "<td><a href=" . site_url("/pt/pt_questions/") . "/0/" . $row->created_by . "/" . $row->tid . ">View</a></td>";
            $tbldata.="</tr>";
        }
        $nr = $query->num_rows();
        $output.="<table border=1><tr><th>Tid</th><th>Count</th><th>Expert</th><th>Status</th></tr>$tbldata<table border=1><tr><td> Total</td><td>$count</td><td>Status</td></tr></table>";

        // get count by month
        $output.="<h1> No. of copies assigned by month</h1>";
        $q = "SELECT concat(concat(monthname(go_to_expert), ', '), year(go_to_expert)) as Month, count(*) as Count from personal_schedule where expert_id=$expert and answer_script_checked!='' and date(go_to_expert) != '0000-00-00' group by month(go_to_expert) order by date(go_to_expert) desc;";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);
        
        $output.="<h1> Copy Checking</h1>";
        $q = "SELECT date(go_to_expert) as Date,count(*) as Count from personal_schedule where expert_id=$expert and answer_script_checked!='' and date(go_to_expert) != '0000-00-00' group by date(go_to_expert) order by date(go_to_expert) desc;";
        $query = $this->db->query($q);
        $output.=$this->table->generate($query);
        
        $data['output'] = $output;
        $this->load->view('link.php', $data);
    }

///////////////////////////////
    function download_answer_script_checked($schedule_id, $un = 0) {
        $this->load->helper('path');
        $q = "SELECT answer_script_checked,answer_script_unchecked from personal_schedule
          WHERE schedule_id='$schedule_id'
          ";
        //AND answer_script_checked!=''
        $query = $this->db->query($q);


        header('Content-type: application/pdf');

        foreach ($query->result() as $row) {                  //echo realpath('/windows/system32');
            if ($un == 0)
                readfile(realpath("../") . "/admin/assets/uploads/files/answer_script/checked/" . $row->answer_script_checked);
            else if ($un == 1)
                readfile(realpath("../") . "/admin/assets/uploads/files/answer_script/unchecked/" . $row->answer_script_unchecked);
            //echo realpath("../");
            //echo  "admin/assets/uploads/files/answer_script/unchecked/".$row->answer_script_unchecked;
        }
    }

/////////////////////////////////
    function test() {
        $crud = new grocery_CRUD();
        $crud->set_table('dd_goods');
        $crud->set_relation('goods_country', 'dd_country', 'country_title');
        $crud->set_relation('goods_state', 'dd_state', 'state_title');
        $crud->set_relation('goods_city', 'dd_city', 'city_title');

        $this->load->library('gc_dependent_select');
// settings

        $fields = array(
// first field:
            'goods_country' => array(// first dropdown name
                'table_name' => 'dd_country', // table of country
                'title' => 'country_title', // country title
                'relate' => null // the first dropdown hasn't a relation
            ),
// second field
            'goods_state' => array(// second dropdown name
                'table_name' => 'dd_state', // table of state
                'title' => 'state_title', // state title
                'id_field' => 'state_id', // table of state: primary key
                'relate' => 'country_ids', // table of state:
                'data-placeholder' => 'select state' //dropdown's data-placeholder:
            ),
// third field. same settings
            'goods_city' => array(
                'table_name' => 'dd_city',
                'title' => '{city_id} / {city_title}', // now you can use this format )))
                'id_field' => 'city_id',
                'relate' => 'state_ids',
                'data-placeholder' => 'select city'));

        $config = array('main_table' => 'dd_goods', 'main_table_primary' => 'goods_id', "url" => base_url() . 'index.php/' . __CLASS__ . '/' . __FUNCTION__ . '/', // path to method
            'ajax_loader' => base_url() . 'ajax-loader.gif', // path to ajax-loader image. It's an optional parameter
            'segment_name' => 'Your_segment_name' // It's an optional parameter. by default "get_items"
        );
        $categories = new gc_dependent_select($crud, $fields, $config);

// first method:
//$output = $categories->render();
// the second method:
        $js = $categories->get_js();
        $output = $crud->render();
        $output->output.= $js;
        $this->_example_output($output);
    }

/////////////////////////////////

    function test1() {
        $crud = new grocery_CRUD();
        $crud->set_table('pt_questions');
        $crud->set_relation('sid', 'section', 'section_name', array('subject_id' => '1'));
        $crud->set_relation('ssid', 'sub_section', 'sub_section_name');
        $crud->set_relation('tid', 'topics', 'topic_name');

        $this->load->library('gc_dependent_select');
// settings

        $fields = array(
// first field:
            'sid' => array(// first dropdown name
                'table_name' => 'section', // table of country
                'title' => 'section_name', // country title
                'relate' => null // the first dropdown hasn't a relation
            ),
// second field
            'ssid' => array(// second dropdown name
                'table_name' => 'sub_section', // table of state
                'title' => 'sub_section_name', // state title
                'id_field' => 'sub_section_id', // table of state: primary key
                'relate' => 'section_id', // table of state:
                'data-placeholder' => 'select ssid' //dropdown's data-placeholder:
            ),
// third field. same settings
            'tid' => array(
                'table_name' => 'topics',
                'title' => '{tid} / {topic_name}', // now you can use this format )))
                'id_field' => 'tid',
                'relate' => 'sub_section',
                'data-placeholder' => 'select tid'));

        $config = array('main_table' => 'pt_questions', 'main_table_primary' => 'qid', "url" => base_url() . 'index.php/' . __CLASS__ . '/' . __FUNCTION__ . '/', // path to method
            'ajax_loader' => base_url() . 'ajax-loader.gif', // path to ajax-loader image. It's an optional parameter
            'segment_name' => 'Your_segment_name' // It's an optional parameter. by default "get_items"
        );
        $categories = new gc_dependent_select($crud, $fields, $config);

// first method:
//$output = $categories->render();
// the second method:
        $js = $categories->get_js();
        $output = $crud->render();
        $output->output.= $js;
        $this->_example_output($output);
    }
///////////////////Functions of Review Module///////////////////
	function review_marks($test_id, $member_id,$schedule_id)
	{
		
	
		$hidden = array('test_id' => $test_id, 'member_id' =>$member_id,'schedule_id' =>$schedule_id);
		$this->load->view('review_marks.php', $hidden);
		
	}
	
	function review_button_click()
	{
		
		$output="";
		$test_id=$_POST['test_id'];
		$member_id=$_POST['member_id'];
		$review_comment=$_POST['review_comment'];
		$review_status=$_POST['review_status'];
		$review_rating=$_POST['review_rating'];
		$schedule_id=$_POST['schedule_id'];
		$reviwer = $this->tank_auth->get_user_id();

				$this->load->model('review_model');
				$this->load->helper('path');
				$this->load->helper('form');
     
		$data=array('review_status'=>"$review_status", 'review_rating'=> "$review_rating",'review_comment'=> "$review_comment",'member_id'=> "$member_id",'test_id'=> "$test_id",'schedule_id' =>"$schedule_id");
	
        $ret = $this->review_model->review_exec($test_id,$member_id,$review_comment,$review_status,$review_rating,$reviwer);
		
		$result = $this->input->post();

        $output.= "<hr/><p align=center><b><font color=blue>";

        if ($ret) {
            $output = "<center><h1>Updated Successfully</h1>";
        } 
		else {
            $output = "error occurred";
			}
			if($review_status=="1")
			{
		        $this->email_student($test_id,$member_id);
			}
			else if($review_status=="0")
			$this->email_expert($test_id,$member_id);
			echo "$output";

			//$this ->add_marks($test_id, $member_id, $schedule_id, $un = 0);

	}
	function toppers_ans_booklets()
	{
		$expertid = $this->tank_auth->get_user_id();
		$TAB_HOME ="visionias_data/assets/home/resources/toppers_ans_booklets";
		$crud = new grocery_CRUD();
		$crud->set_theme('datatables');
		$crud->set_table('toppers_ans_booklets');
		$crud->columns('name','rank','notification_year','res_loc','subject_id','test_id');
		$crud->display_as('res_loc','Choose File');
		$crud->display_as('subject_id','Subject');
		$crud->display_as('notification_year','CSE Year');
		$crud->unset_columns('created_by','time_of_creation');
		$crud->set_subject("Toppers' Answer Booklets");
		$crud->set_relation('subject_id', 'subjects', '{subject_id}-{subject_name}');
		$crud->field_type('created_by', 'hidden', $expertid);
		$crud->field_type('view_count', 'hidden');
		$crud->field_type('time_of_creation', 'hidden', date('Y-m-d H:i:s'));
		$crud->set_field_upload('res_loc',"$TAB_HOME");
		$crud->callback_before_insert(array($this,'move_resource_file'));
		
		$output = $crud->render();
		$this->_example_output($output);   
	}
	function move_resource_file($post_array) 
	{	
		$structure="visionias_data/assets/home/resources/toppers_ans_booklets/".$post_array['notification_year'];
		if (!file_exists($structure)) 
		{
			mkdir("$structure", 0777);
		}
	   $old_path = "visionias_data/assets/home/resources/toppers_ans_booklets/".$post_array['res_loc'];
	   $new_path = "visionias_data/assets/home/resources/toppers_ans_booklets/".$post_array['notification_year']."/".$post_array['res_loc'];
	   rename($old_path,$new_path);
	   
		return $post_array;
	}
	function email_student($test_id,$member_id)
	{
		
		    if ($member_id != '') {
            $this->load->library('email');
            $this->load->model('admin_model');

            $email_student = $this->admin_model->get_student_email($member_id);
           
           
            $this->email->from('sneha@visionias.in', 'Vision IAS Copy Management Team');
            $this->email->to($email_student);
			
            $this->email->bcc('sneha@visionias.in,info@visionias.in,admin@visionias.in,ashvani.kumar@gmail.com,abhay.mlvtec@gmail.com');
			$this->email->subject("VISION IAS TEST: $test_id Student: $member_id");
            $msg = "Dear Student,<br> ";
            $msg.="<br> checked copy of Student Id $member_id Test $test_id  has been uploaded on Vision IAS student zone.";
            $msg.="<br>In case of any discrepancy please contact sneha@visionias.in ";
            $msg.="<br><br>Regard<br><br> VisionIAS Admin Team ";
			$this->email->message($msg);
            $this->email->send();
			//echo "Send Successfully";
        }
	}
	function email_expert($test_id,$member_id)
	{
		
		  if ($member_id != '') {
          $this->load->library('email');
          $this->load->model('admin_model');

           // $rt = $this->admin_model->get_expert_email($member_id,$test_id);
            //$expert_email = $rt['email'];

            $this->email->from('sneha@visionias.in', 'Vision IAS Copy Management Team');
            //$this->email->to($expert_email);
            $this->email->to('info@visionias.in');
			$this->email->bcc('sneha@visionias.in,info@visionias.in,admin@visionias.in,ashvani.kumar@gmail.com,abhay.mlvtec@gmail.com');
            $this->email->subject('Copy Evaluation Rejected ');
            $msg = "Dear Expert,<br> ";
            $msg.="<br> checked copy of Student Id $member_id Test $test_id  has been rejected by Vision IAS Copy Management Team.";
            $msg.="<br>In case of any discrepancy please contact sneha@visionias.in ";
            $msg.="<br><br>Regard</br> </br>VisionIAS Admin Team ";

            $this->email->message($msg);
            $this->email->send();
			
        }
	}
	////////////////////////////////END Review Module/////
	////////////////////select query for user who dont have permission to directly import data from database
function Selectquery()
{
	$this->load->helper('form');  
	//$this->load->model('admin_model');
	/*  $data = $this->admin_model->notice($package);
    $this->load->view('notice_package',$package); */
	//$this->data['posts'] = $this->admin_model->getRecords(); // calling Post model method getPosts()
   $this->load->view('query.php'); // load the view file , we are

} 
function generate_coupon()
{
	$crud = new grocery_CRUD();
	//$this->load->library('controllers/admin');
	$crud->set_theme('datatables');
    $crud->set_table('coupon_code');
	$crud->set_subject('Coupon Code');
	$output = $crud->render();
	$state = $crud->getState();
    $state_info = $crud->getStateInfo();
 
    if($state == 'add')
    {
      //$this->load->view('generate_coupon_code.php'); 
	$this->generate_coupon_code(); 
    }
    else
    {
        $this->_example_output($output);
    }
}
function generate_coupon_code()
{
		$this->load->helper('string');
		$expertid = $this->tank_auth->get_user_id();
		$coupon_code= random_string('alpha', 12);
		$this->load->helper('form');  
		$this->load->view('generate_coupon_code'); // load the view file , we are
} 
function generate_coupon_code_exec()
{
	$this->load->helper('string');
	$expertid = $this->tank_auth->get_user_id();
	if(isset($_REQUEST['coupon_code']))
	{
	$coupon_code =$_REQUEST['coupon_code'];
	}
	$Coupon_type=$_REQUEST['Coupon_type'];
	if(isset($_REQUEST['email']))
	{
		$email=$_REQUEST['email'];
	}
	$sdate =date('Y-m-d',strtotime("$_REQUEST[startdate]")); 
	$edate =date('Y-m-d',strtotime("$_REQUEST[enddate]"));
	
	if($Coupon_type=="special")//TODO: check by coupon type
	{
	
			$coupon_code= random_string('alpha', 12);
			$coupon_code_cond= random_string('alpha', 12);
			while($coupon_code_cond!=="")
			{
				$check_coupon_query="select * from coupon_code where code ='$coupon_code'";
					try { 
							$query=$this->db->query($check_coupon_query);
							if($query->num_rows() > 0)
							{
								$coupon_code= random_string('alpha', 12);
								$coupon_code_cond = random_string('alpha', 12);
							}
							else
							{
								$coupon_code_cond="";
							}
						}
					catch (Exception $e) {
						var_dump($e->getMessage());
									}
			}
			$generate_coupon_code_query="insert into coupon_code (code,start_date,end_date,discount_per,email,status,max_disc_value,created_by) values('$coupon_code','$sdate','$edate','$_REQUEST[discount_per]','$_REQUEST[email]','1','$_REQUEST[max_disc_value]','$expertid')";
				try{
					$this->db->query($generate_coupon_code_query);
					//$this->email_coupon_code($email,$expertid);
					//TODO: error handling
					$string = array('message'=>"Coupon code $coupon_code is generated successfully.");
					echo json_encode($string);
				}
				catch (Exception $e) {
						var_dump($e->getMessage());
									}
		//TODO: check for unique value of code, insert in coupon_code table,send mail on email and to code generator
		//TODO: design template of mail
	}
	else
	{
		$coupon_query="select * from coupon_code where code='$coupon_code'";
		try
		{
			$query=$this->db->query($coupon_query);
			//TODO: error handling with try/catch
			if ($query->num_rows() > 0)
			{
				foreach($query->result() as $row)
				{
					$s_date= $row->start_date;
					$e_date= $row->end_date;
					$disc= $row->discount_per;
					$status=$row->status;
					$max_value=$row->max_disc_value;
					if($s_date>=$sdate && $e_date<=$edate)
					{
								
					}
					else if($s_date<=$sdate && $e_date<=$edate)	
					{
								
					}
					else if($s_date>=$sdate && $e_date>=$edate)	
					{
								
					}
					else if($s_date<=$sdate && $e_date>=$edate)	
					{
								
					}
					else
					{
									$generate_coupon_code_query="insert into coupon_code (code,start_date,end_date,discount_per,status,max_disc_value,created_by) values('$_REQUEST[coupon_code]','$sdate','$edate','$_REQUEST[discount_per]','1','$_REQUEST[max_disc_value]','$expertid')";
									try{
										$this->db->query($generate_coupon_code_query);
										//TODO: error handling
										$string = array('message'=>"Coupon code $_REQUEST[coupon_code] is generated successfully.");
										echo json_encode($string);
									}
									catch (Exception $e) {
											var_dump($e->getMessage());
														}
						return false;
					}						
					$string = array('s_date'=>$s_date,'e_date'=>$e_date,'disc'=>$disc,'status'=>$status,'max_value'=>$max_value,'message'=>'This coupon code already exists with following details:');
				echo json_encode($string);
				}
				
			}
			else
			{
				$generate_coupon_code_query="insert into coupon_code (code,start_date,end_date,discount_per,status,max_disc_value,created_by) values('$_REQUEST[coupon_code]','$sdate','$edate','$_REQUEST[discount_per]','1','$_REQUEST[max_disc_value]','$expertid')";
				try{
					$this->db->query($generate_coupon_code_query);
					//TODO: error handling
					$string = array('message'=>"Coupon code $_REQUEST[coupon_code] is generated successfully.");
					echo json_encode($string);
				}
				catch (Exception $e) {
						var_dump($e->getMessage());
									}
			}
		}
		catch (Exception $e) {
						var_dump($e->getMessage());
							}
	}
}
function email_coupon_code($email,$expertid)
{
		$expertid="14";
		$email="abhay.mlvtec@gmail.com";
		
		 if ($email != '') {
            $this->load->library('email');
            $this->load->model('admin_model');
            $rt= $this->admin_model->expert_email($expertid);
			die();
			$expert_email = $rt['email'];
            $this->email->from('dispatch@visionias.in','Vision IAS Registration Team');
            $this->email->to($email_student);
			
            $this->email->bcc('info@visionias.in,admin@visionias.in,ashvani.kumar@gmail.com,abhay.mlvtec@gmail.com');
			$this->email->subject("Vision IAS:Discount on new course");
            $msg = "Dear Student,<br> ";
            $msg.="<br> .";
            $msg.="<br>In case of any discrepancy please contact dispatch@visionias.in ";
            $msg.="<br><br>Regard<br><br> VisionIAS Admin Team ";
			$this->email->message($msg);
            $this->email->send();
		}
}
function executequery()
{
$filename = "excel";        
$sql = $_REQUEST['comments'];
//$sql = "Select * from members";

$sql1 = explode(" ",$sql);
//echo $sql1[0];
if (strcasecmp($sql1[0], select) == 0) 
{
ob_end_clean();
$result =mysql_query($sql) or die("Couldn't execute query:<br>" . mysql_error(). "<br>" . mysql_errno());    
$file_ending = "csv";
header("Content-Type: application/csv");    
header("Content-Disposition: attachment; filename=$filename.csv");  
header("Pragma: no-cache"); 
header("Expires: 0");
/*******Start of Formatting for Excel*******/   
//define separator (defines columns in excel & tabs in word)
$sep = ","; //tabbed character
//start of printing column names as names of MySQL fields
for ($i = 0; $i < mysql_num_fields($result); $i++) {
echo mysql_field_name($result,$i) . "," ."           ";
}
print("\n");    
//end of printing column names  
//start while loop to get data
    while($row = mysql_fetch_row($result))
    {
        $schema_insert = "";
        for($j=0; $j<mysql_num_fields($result);$j++)
        {
            if(!isset($row[$j]))
                $schema_insert .= "NULL".$sep;
            elseif ($row[$j] != "")
                $schema_insert .= "$row[$j]".$sep;
            else
                $schema_insert .= "".$sep;
        }
        $schema_insert = str_replace($sep."$", "", $schema_insert);
        $schema_insert = preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert) ."           ";
        $schema_insert .= "\t" ."           ";
        print(trim($schema_insert));
        print "\n";
    }  
}
else 
{
	echo "Only Update query can execute here!!!";
}	
}
}