<?php
class SettingAction extends BaseAction {

    function index(){
        $this->display();
    }

    function group_list(){
        $this->admin_allowed();
        $groups = D("UserGroups")->getTeamGroups($this->login_user['team_id']);
        $this->assign("groups", $groups);
        $this->display();
    }

    function group_edit() {
        $this->admin_allowed();
        $group_id = $this->_param("group_id");
        if($group_id){
            $group = M("UserGroups")->find($group_id);
            $this->assign('group',$group);
        }
        $this->assign("title", L('edit_auth'));
        $html = $this->fetch('group_edit_dialog');
        $data = array(
            array("data" => $html, "type" => "dialog"),
            array("data" => "dialog_validator()", "type" => "eval")
        );
        json($data, "mix");
    }

    function group_submit(){
        $this->admin_allowed();
        $userGroupModel = D("UserGroups");
        if($userGroupModel->create()){
            $group_id = $this->_param("id");
            if($group_id){
                $userGroupModel->save();
            }else{
                $userGroupModel->create_user_id = $this->login_user['id'];
                $userGroupModel->create_time = date('Y-m-d H:i:s');
                $group_id = $userGroupModel->add();

                $param = array(
                    "team_id" => $this->login_user['team_id'],
                    "user_group_id" => $group_id,
                    );
                M("UserGroupTeamMapping")->add($param);
            }
        }
        Session::set("success", L("update_success"));
        redirect("/setting/group_list?hl=" . $group_id);
    }

    function group_delete(){
        $this->admin_allowed();
        $group_id = $this->_param("group_id");
        if($group_id){
            M("UserGroups")->delete($group_id);
            $user_ids = M("UserGroupMapping")->getByUserGroupId($group_id);
            if($user_ids){
                $user_ids = Utility::GetColumn("user_id");
                M("Users")->setField($user_ids);
                M("UserGroupMapping")->where("user_group_id=$group_id")->delete();
            }
            Session::set("success", L("update_success"));
            json(null, "refresh");
        }
    }

    function user_list(){
        $this->admin_allowed();
        $group_id = $this->_param("group_id");

        if($group_id){
            $group_ids = $group_id;
            $this->assign("group_id", $group_id);
        }else{
            $groups = D("UserGroups")->getTeamGroups($this->login_user['team_id']);
            $group_ids = Utility::GetColumn($groups);
        }
        $users = D("Users")->getGroupUsers($group_ids);
        $count = count($users);
        list($pagesize, $page_num, $pagestring) = pagestring($count, 20);
        $users = D("Users")->getGroupUsers($group_ids, $page_num, $pagesize);
        $this->assign("users", $users);
        $this->assign("pagestring", $pagestring);
        $all_team_groups = D("UserGroups")->getTeamGroups($this->login_user['team_id']);
        $this->assign("groups", $all_team_groups);
        $this->display();
    }

    function user_edit(){
        $this->admin_allowed();
        $user_id = $this->_param("user_id");
        if($user_id){
            $user = M("Users")->find($user_id);
            $this->assign('user',$user);
            $group_id = D("Users")->getUserGroupId($user_id, $this->login_user['team_id']);
            $this->assign("group_id", $group_id);
        }
        $all_team_groups = D("UserGroups")->getTeamGroups($this->login_user['team_id']);
        $this->assign("groups", $all_team_groups);
        $this->assign("title", L('edit_user'));
        $html = $this->fetch('user_edit_dialog');
        $data = array(
            array("data" => $html, "type" => "dialog"),
            array("data" => "dialog_validator()", "type" => "eval")
        );
        json($data, "mix");
    }


    function user_submit(){
        $this->admin_allowed();
        $userModel = D("Users");
        if($userModel->create()){
            $user_id = $this->_param("id");
            $password = $this->_param("password");
            if($user_id){
                if(empty($password)){
                    $old_user = M("Users")->find($user_id);
                    $userModel->password = $old_user['password'];
                }
                $userModel->save();
                $group_id = $this->_param("group");
                D("UserGroupMapping")->where("user_id=$user_id")->setField("user_group_id", $group_id);
            }else{
                $user_id = $userModel->add();
                $group_id = $this->_param("group");
                D("UserGroupMapping")->add(array("user_id" => $user_id, "user_group_id" => $group_id));
            }
            Session::set("success", L("update_success"));
        }else{
            Session::set("error", L("update_fail"));
        }
        redirect("/setting/user_list");
    }

    function ajax_user_delete() {
        D("Users")->delete($this->_get('user_id'));
        json(null, 'refresh');        
    }

    function password_edit(){
        $this->assign('title','密码修改');
        $this->assign('modal_style' ,"style='width:590px'");
        $html = $this->fetch('password_edit_dialog');
        $data = array(
            array("data" => $html, "type" => "dialog"),
            array("data" => "init()", "type" => "eval")
        );
        json($data, "mix");
    }
    function password_submit(){
        $password = $this->_param("password");
        if(!empty($password)){
            $password = D("Users")->genPassword($password);
            $user = D("Users")->getById($this->login_user['id']);
            if($user){
                // if($user['password_history']){
                //     $old_pass_array = unserialize($user['password_history']);
                //     foreach ($old_pass_array as $key => $old_pass) {
                //         if($password == $old_pass['password']){
                //             Session::Set("error", "新密码不能和最近三次密码相同");
                //             redirect("/setting");
                //         }
                //     }
                //     $old_pass_array[] = array("date" => date('Y-m-d H:i:s'), "password" => $password);
                //     if(count($old_pass_array) > 3){
                //         $old_pass_array = array_slice($old_pass_array, 1);
                //     }
                // }else{
                //     $old_pass_array[] = array("date" => date('Y-m-d H:i:s'), "password" => $password);
                // }
                // D("Users")->where("id=" . $this->login_user['id'])->setField("password",$password);
                // $user['password_history'] = serialize($old_pass_array);
                $user['password'] = $password;
                D("Users")->save($user);
                Login::logout();
            }
        }
        Session::set("success", L("update_success"));
        redirect("/setting");
    }


    //TYPE
    function common_types() {
        $type_name = $this->_get("type");
        if($type_name) {
            $type = M('CommonTypes')->getsByKey($type_name);
        }
        if($type) {
            $data['edit_type'] = $type;
            foreach($type as $v) {
                if($v['key_name']) {
                    $data['edit_type_name'] = $v['key_name'];
                    break;
                }
            }
        }

        $all = M('CommonTypes')->select();
        foreach ($all as $v) {
            if($v['key_name']) {
                $types[$v['key']]['key_name'] = $v['key_name'];
            }
            $types[$v['key']]['display_value'] .= $v['name'] . ', ';
        }

        $data['types'] = $types;

        $this->assign($data);
        $this->display();
    }

    function add_common_type() {
        if($this->_post('name')) {
            M('CommonTypes')->add($_POST);
        }
        redirect($_SERVER['HTTP_REFERER']);
    }


    function ajax_remove_common_type() {
        $data['id'] = $this->_get('id');
        $data['enabled'] = 0;
        M('CommonTypes')->save($data);
        json(null, 'refresh');
    }


    // 邀请码

    function invite_codes() {
         $this->invite_codes = D("InviteCodes")->order('id desc')->select();

         $this->display();
    }

    function add_invite_code() {
        $data = $_POST;
        $data['create_time'] = date('Y-m-d H:i:s');
        M("InviteCodes")->add($data);
        redirect('/Setting/invite_codes');
    }

    function ajax_delete_invite_code() {
        M("InviteCodes")->delete(I('id'));
        json(null, "refresh");
    }


    function finance_codes() {
         $this->finance_codes = D("FinanceCodes")->order('code')->select();

         $this->display();
    }

    function add_finance_codes() {
        $data = $_POST;
        M("FinanceCodes")->add($data);
        redirect('/Setting/finance_codes');
    }

    function ajax_delete_finance_code() {
        M("FinanceCodes")->delete(I('id'));
        json(null, "refresh");
    }

    /**
     * 问答题编辑
     * @return [type]
     */
    function questions() 
    {
        $this->user_types = D("UserTypes")->getField('id,name',true);
        $this->questions = D("UserTypeQuestions")->gets();

        $this->hilighttypeid = $this->_get('hilighttypeid')?$this->_get('hilighttypeid'):1;

        $this->display();
    }

    function question_edit()
    {
        $this->user_types = D("UserTypes")->getField('id,name',true);

        $id = intval($this->_get('id'));
        if($id) {
            $this->question = D("UserTypeQuestions")->find($id);
        }

        $this->modal_style = "style='width:624px'";
        $this->assign("title", ($id>0?'编辑':'新建') . '问答题');

        $html = $this->fetch('question_edit_dialog');
        $data = array(
            array("data" => $html, "type" => "dialog"),
            array("data" => "dialog_validator()", "type" => "eval")
        );
        json($data, "mix");
    }

    public function question_submit()
    {
        $data = $_POST;
        $last_id = D("UserTypeQuestions")->saveOrUpdate($data);

        redirect('/setting/questions?hilighttypeid='.$data['type_id']);
    }

    public function ajax_delete_question() {
        M("UserTypeQuestions")->where('id='.I('id'))->setField("is_deleted",1);
        json(null, "refresh");        
    }


    function add_result_codes() 
    {
        $data = $_POST;
        M("ResultCodes")->add($data);
        redirect('/Setting/result_codes');
    }

    function ajax_delete_result_code() {
        M("ResultCodes")->delete(I('id'));
        json(null, "refresh");
    }


    function apply_intro() {
        if($_POST['apply_intro']) {
            D("Options")->update('apply_intro', $_POST['apply_intro']);
        }
        if($_POST['candidate_intro']) {
            D("Options")->update('candidate_intro', $_POST['candidate_intro']);
        }
        if($_POST['recommend_intro']) {
            D("Options")->update('recommend_intro', $_POST['recommend_intro']);
        }
        $this->apply_intro = D("Options")->getOption('apply_intro');
        $this->candidate_intro = D("Options")->getOption('candidate_intro');
        $this->recommend_intro = D("Options")->getOption('recommend_intro');
        $this->display();
    }

    // email setting related
    function email() 
    {
        $audit_user_ststuses = $this->audit_user_ststuses = D("UserStatuses")->getsByWithAudit(1);
        if($_POST) {
            // D("Options")->update('audit_email_enable_daily_notice', intval($_POST['audit_email_enable_daily_notice']));
            // D("Options")->update('audit_success_notify_email', $_POST['audit_success_notify_email']);

            // // D("Options")->update('audit_email_success_subject', trim($_POST['audit_email_success_subject']));
            // // D("Options")->update('audit_email_success', trim($_POST['audit_email_success']));
            
            // // D("Options")->update('audit_email_failed_subject', trim($_POST['audit_email_failed_subject']));
            // // D("Options")->update('audit_email_failed', trim($_POST['audit_email_failed']));

            foreach ($audit_user_ststuses as $one) {
                $up['success_email_subject'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_success_subject_'.$one['id']]));
                $up['success_email_body'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_success_body_'.$one['id']]));
                $up['success_email_to'] = $_POST['audit_email_success_to_'.$one['id']];

                $up['fail_email_subject'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_fail_subject_'.$one['id']]));
                $up['fail_email_body'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_fail_body_'.$one['id']]));
                $up['fail_email_to'] = $_POST['audit_email_fail_to_'.$one['id']];

                $up['neededit_email_subject'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_neededit_subject_'.$one['id']]));
                $up['neededit_email_body'] = trim(str_replace('&nbsp;', '', $_POST['audit_email_neededit_body_'.$one['id']]));
                $up['neededit_email_to'] = $_POST['audit_email_neededit_to_'.$one['id']];

                M("UserStatuses")->where('id=%d',$one['id'])->save($up);
            }

            redirect("/setting/email");
        }

        $this->audit_email_enable_daily_notice = D("Options")->getOption('audit_email_enable_daily_notice');
        $this->audit_success_notify_email = D("Options")->getOption('audit_success_notify_email');


        $tmp =  D("UserStatuses")->select();
        foreach ($tmp as $k => $v) {
            $success_subjects[$v['id']] = $v['success_email_subject'];
            $success_bodys[$v['id']] = $v['success_email_body'];
            $success_tos[$v['id']] = $v['success_email_to'];

            $fail_subjects[$v['id']] = $v['fail_email_subject'];
            $fail_bodys[$v['id']] = $v['fail_email_body'];
            $fail_tos[$v['id']] = $v['fail_email_to'];

            $neededit_subjects[$v['id']] = $v['neededit_email_subject'];
            $neededit_bodys[$v['id']] = $v['neededit_email_body'];
            $neededit_tos[$v['id']] = $v['neededit_email_to'];

        }
        $this->success_subjects = $success_subjects;
        $this->success_bodys = $success_bodys;
        $this->success_tos = $success_tos;

        $this->fail_subjects = $fail_subjects;
        $this->fail_bodys = $fail_bodys;
        $this->fail_tos = $fail_tos;

        $this->neededit_subjects = $neededit_subjects;
        $this->neededit_bodys = $neededit_bodys;
        $this->neededit_tos = $neededit_tos;

        $this->display();
    }

    function lingxiform() {
        if($_POST) {
            for($i=0;$i<200;$i++) {
                if($_POST['name_'.$i] && $_POST['id_'.$i]) {
                    $res[] = array('name'=>$_POST['name_'.$i], 'id'=>$_POST['id_'.$i]);
                }
            }
            D("Options")->update('sync_lingxi_forms', serialize($res));
            redirect('/setting/lingxiform');
        }

        $this->forms = unserialize(D("Options")->getOption('sync_lingxi_forms'));
        $this->display();
    }


    // tasks start 
    function tasks() {
        $otypes = D("UserTypes")->getField('alias,name');
        $otypes['project'] = '项目';
        $this->object_types = $otypes;
        $this->type = $type = $this->_get('type')?$this->_get('type'):'laoshi';

        $this->tasks = D("Tasks")->where('type="%s"', $type)->order('sequence desc')->select();

        $this->display();
    }


    function render_task_edit() {

        $otypes = D("UserTypes")->getField('alias,name');
        $otypes['project'] = '项目';
        $this->object_types = $otypes;
        $this->type = $type = $this->_get('type')?$this->_get('type'):'laoshi';

        $id = intval($this->_get('id'));

        if($id) {
            $this->task = D("Tasks")->find($id);
        }

        $this->modal_style = "style='width:660px'";
        $this->assign("title", ($id>0?'编辑':'新建') . '任务');

        $html = $this->fetch('task_edit_dialog');
        $data = array(
            array("data" => $html, "type" => "dialog"),
            array("data" => "dialog_validator()", "type" => "eval")
        );
        json($data, "mix");
    }

    function task_submit() 
    {
        $data = $_POST;
        $last_id = D("Tasks")->saveOrUpdate($data);
        redirect('/setting/tasks?type='.$data['type']);
    }

    function ajax_delete_task()
    {
        M("Tasks")->delete(I('id'));
        json(null, "refresh");
    }

    // tasks end
}
