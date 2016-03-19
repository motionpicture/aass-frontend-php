<?php
namespace Aass\Frontend\Forms;

use Phalcon\Forms\Form;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Hidden;
use Phalcon\Forms\Element\Textarea;
use Phalcon\Forms\Element\File;
use Phalcon\Forms\Element\Select;
use Phalcon\Validation\Validator\PresenceOf;

class MediaForm extends Form
{
    public function initialize()
    {
        $element = new Hidden(
            'id',
            [
                'placeholder' => 'ID'
            ]
        );
        $this->add($element);

        $element = new Text(
            'title',
            [
                'placeholder' => '動画名'
            ]
        );
        $element->addValidators([
            new PresenceOf(
                [
                    'message' => '動画名を入力してください'
                ]
            )
        ]);
        $this->add($element);

        $element = new Textarea(
            'description',
            [
                'placeholder' => '動画概要'
        ]);
        $element->addValidator(
            new PresenceOf(
                [
                    'message' => '動画概要を入力してください'
                ]
            )
        );
        $this->add($element);

        $element = new Text(
            'uploaded_by',
            [
                'placeholder' => '動画登録者名'
            ]);
        $element->addValidator(
            new PresenceOf(
                [
                    'message' => '動画登録者名を入力してください'
                ]
            )
        );
        $this->add($element);

        $element = new File(
            'file',
            [
                'placeholder' => 'ファイル'
            ]
        );
        $this->add($element);

        $choices = [];
        for ($i=0; $i<10; $i++) {
            $times = $i + 1;
            $mb = $times * $this->getUserOption('blockMaxSize') / 1024 / 1024;
            $choices[$times * $this->getUserOption('blockMaxSize')] = "{$mb}mb";
        }
        $element = new Select(
            'chunk_size',
            $choices,
            [
            ]
        );
        $element->setLabel('分割サイズ(開発環境のみ変更可能)');
        $this->add($element);
    }

    public function setDefaults($params)
    {
        foreach ($params as $key => $value) {
            if ($this->has($key)) {
                $this->get($key)->setDefault($value);
            }
        }
    }
}