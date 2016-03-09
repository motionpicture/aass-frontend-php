<?php
namespace Aass\Backend\Models;

class Admin extends \Aass\Common\Models\Admin
{
    public function getLoginUser($userId, $password)
    {
        $statement = $this->db->prepare('SELECT * FROM admin WHERE user_id = :userId AND password = :password');
        $statement->execute([
            'userId' => $userId,
            'password' => $password
        ]);

        return $statement->fetch();
    }
}