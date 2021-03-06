<?php
namespace app\api\model;
use think\Model;
use think\Db;

//主要用于消息保存和离线消息的处理
class Message extends Model
{
    //读一个月内的未读信息
    public function getUnreadMessage($fromUserId, $toUserId, $groupId=null, $time = 1){
        $timeAfter = date('Y-m-d', strtotime('-'.$time.' month')); 
        if($groupId == null){//单聊消息
            $messageList = Db::name('chart')->where('from_user_id', $fromUserId)
                ->where('to_user_id', $toUserId)
                ->where('send_time', 'gt', $timeAfter)                        
                ->where('is_receive', 0)    //读取未发送
                ->order('id')
                ->select();
            Db::name('chart')->where('from_user_id', $fromUserId)//变更未发送的信息状态
                        ->where('to_user_id', $toUserId)
                        ->where('is_receive', 0)   
                        ->setField('is_receive', 1);
            
        }else{  //群组消息
            $messageList = Db::name('chart')->where('group_id', $groupId)
                ->where('to_user_id', $toUserId)
                ->where('send_time', 'gt', $timeAfter)                        
                ->where('is_receive', 0)    //读取未发送
                ->order('id')
                ->select();
            Db::name('chart')->where('group_id', $groupId)//变更未发送的信息状态
                        ->where('to_user_id', $toUserId)
                        ->where('is_receive', 0)
                        ->setField('is_receive', 1);
        }
        return $messageList;
    }

    //读取历史消息,默认最近50条
    public function getHistoryMessage($fromUserId, $toUserId, $groupId=null, $firstMessageId = 1, $limit = 50){
        if($firstMessageId == 1){ //第一次通信，获取最大id
            $firstMessageId = Db::name('chart')->max('id');
        }
        if($groupId == null){//单聊消息
            $messageList =  Db::name('chart')
                            ->where(function($query) use($fromUserId, $toUserId, $firstMessageId)  {
                                $query->where(['from_user_id'=>$fromUserId, 'to_user_id'=> $toUserId, ])
                                    ->where('id','lt', $firstMessageId);
                            })->whereOr(function($query) use($fromUserId, $toUserId, $firstMessageId) {
                                $query->where(['from_user_id'=>$toUserId, 'to_user_id'=> $fromUserId])
                                    ->where('id','lt', $firstMessageId);
                            })
                        ->order('id desc')
                        ->limit($limit)
                        ->select();
            echo Db::name('chart')->getLastSql();
        }else{  //群组消息
            //dump('读取群组历史消息');
            $messageList = Db::name('chart')->where('group_id', $groupId)
            ->where('to_user_id', $toUserId)
            ->where('id','lt', $firstMessageId)
            ->order('id desc')
            ->limit($limit)
            ->select();
        }
        return $messageList;
    }

   
    //保存消息
    public function saveMessage($fromUserId, $toUserId, $message, $type="text", $groupId = null, $isRead = 0){
        $data['from_user_id'] = $fromUserId;
        $data['to_user_id'] = $toUserId;
        $data['content'] = $message;
        $data['send_time'] = date('Y-m-d H:i:s');
        $data['is_receive'] = $isRead;
        $data['type'] = $type;
        if($groupId != null){
            $data['group_id'] = $groupId;
        }
        $res = Db::name('chart')->insertGetId($data);
        return $res;
    }

    //修改已读消息
    public function receiveMessage($id, $isRead = 1){
        return Db::name('chart')->where('id', $id)->setField('is_receive', $isRead);//变更信息状态
    }

    //获取有未读消息的用户列表
    //$uid:用户id
    //$time:时间限制，如一个月内
    public function getHaveUnreadUserList($uid, $time=1){
        $timeAfter = date('Y-m-d', strtotime('-'.$time.' month'));
        $unreadMessageList = Db::name('chart')->field('from_user_id')
                            ->where('to_user_id', $uid)
                            ->where('is_receive', 0)
                            ->where('send_time', 'gt', $timeAfter)
                            ->group('from_user_id')->order('send_time desc')->select();
        return $unreadMessageList ;
    }

    //获取最近联系人列表
    //$uid:用户id
    //$time:时间限制，如一个月内
    //$uidNotIn 不在此列表内的uid
    public function getRecentConnectUserList($uid, $time=1, $uidNotIn=[]){
        $timeAfter = date('Y-m-d', strtotime('-'.$time.' month'));
        $recentConnectUserList = Db::name('chart')->field('from_user_id')
                            ->where('to_user_id', $uid)
                            ->where('from_user_id', 'not in', $uidNotIn)
                            ->where('send_time', 'gt', $timeAfter)
                            ->group('from_user_id')->order('send_time desc')->select();
        return $recentConnectUserList ;
    }


    //当前联系人有多少条未读信息
    public function unReadCount($uid, $time=1){
        $timeAfter = date('Y-m-d', strtotime('-'.$time.' month'));
        $unReadCount = Db::name('chart')->field('id')
                            ->where('to_user_id', $uid)
                            ->where('send_time', 'gt', $timeAfter)
                            ->where('is_receive', 0)//读取已发送
                            ->count();
        return $unReadCount ;
    }
}