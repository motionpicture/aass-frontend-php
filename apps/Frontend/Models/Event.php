<?php
namespace Aass\Frontend\Models;

class Event extends \Aass\Common\Models\Event
{

    public function getLoginUser($userId, $password)
    {
        $statement = $this->db->prepare('SELECT * FROM event WHERE user_id = :userId AND password = :password');
        $statement->execute([
            'userId' => $userId,
            'password' => $password
        ]);

        return $statement->fetch();
    }
}