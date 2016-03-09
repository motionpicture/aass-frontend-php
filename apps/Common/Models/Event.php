<?php
namespace Aass\Common\Models;

class Event extends Base
{
    public $id;
    public $userId;
    public $email; // メールアドレス
    public $password; // パスワード
    public $heldAt; // 上映日時
    public $place; // 上映場所
}