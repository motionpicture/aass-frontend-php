<?php
namespace Aass\Common\Models;

class Show extends Base
{
    public $partitionKey;
    public $rowKey; // ユーザーID
    public $email; // メールアドレス
    public $password; // パスワード
    public $date; // 上映日時
    public $place; // 上映場所
}