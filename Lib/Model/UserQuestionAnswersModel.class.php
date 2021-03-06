<?php

class UserQuestionAnswersModel extends BaseModel {

    public function getByUserId($cms_user_id=0, $type_id, $display_key_prefix='') {        
        if($type_id==1) {
            $f['recommend_id'] = $cms_user_id;
        } else {
            $f['cms_user_id'] = $cms_user_id;
        }       
        $f['type_id'] = $type_id;
        $raw = $this->where($f)->select();
        foreach ($raw as $k => $v) {
            $res[$display_key_prefix.$v['question_id']] = $v['answer'];
        }

        return $res;
    }

    public function getQuestionAnswers($cms_user_id=0, $type_id) {
        if($type_id==1) {
            $f['recommend_id'] = $cms_user_id;
        } else {
            $f['cms_user_id'] = $cms_user_id;
        }

        $raw = Utility::AssColumn($this->where($f)->select(), 'question_id');

        $questions = D("UserTypeQuestions")->gets($type_id,true);

        foreach ($questions as $q) {
            // 如果问题已经被删除，并且没有对应的填写过的答案，忽略之
            if($q['is_deleted'] && !$raw[$q['id']]['answer']) {
                continue;
            }
            $res[$q['question']] = $raw[$q['id']]['answer'];
        }

        return $res;
    }    
}
?>