<?php
namespace Aass\Frontend\Forms;

use Phalcon\Forms\Form;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Password;
use Phalcon\Validation\Validator\PresenceOf;

class LoginForm extends Form
{
    public function initialize()
    {
        $element = new Text(
            'user_id',
            [
                'placeholder' => 'ユーザID'
            ]
        );
        $element->addValidators([
            new PresenceOf(
                [
                    'message' => 'ユーザIDを入力してください'
                ]
            )
        ]);
        $this->add($element);

        $element = new Password(
            'password',
            [
                'placeholder' => 'パスワード'
        ]);
        $element->addValidator(
            new PresenceOf(
                [
                    'message' => 'パスワードを入力してください'
                ]
            )
        );
        $this->add($element);
    }
}