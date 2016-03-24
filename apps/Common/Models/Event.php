<?php
namespace Aass\Common\Models;

class Event extends Base
{
    public $id;
    public $user_id;
    public $email; // メールアドレス
    public $password; // パスワード
    public $held_from; // 上映日時
    public $held_to; // 上映日時
    public $place; // 上映場所
    public $remarks;// 備考
}